<?php

namespace Luni\Console\Grenade;

use Luni\Console\Grenade\Git\Git;
use Luni\Console\Grenade\RuntimeException as GrenadeRuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\ProcessBuilder;

class ConfigureCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('grenade:configure')
            ->setDescription('')
            ->addArgument(
                'project-repository',
                InputArgument::REQUIRED,
                'Path to the git project repository'
            )
            ->addArgument(
                'alias',
                InputArgument::OPTIONAL,
                'Repository alias'
            )
            ->addOption(
                'working-dir',
                'w',
                InputOption::VALUE_OPTIONAL,
                'Path to the working dir',
                '.'
            )
            ->addOption(
                'timeout',
                't',
                InputOption::VALUE_OPTIONAL,
                'Git timeout',
                1800
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repository = $input->getArgument('project-repository');
        $projectAlias = $input->getArgument('alias') ? $input->getArgument('alias') : basename($repository, '.git');
        $cwd = $input->getOption('working-dir');
        if (strpos($cwd, '/') !== 0 && strpos($cwd, ':') === false) {
            $cwd = getcwd() . '/' . $cwd;
        }
        $timeout = max(1, (int) $input->getOption('timeout'));

        if (!file_exists($cwd . '/repositories')) {
            mkdir($cwd . '/repositories', 0755, true);
        }

        if (!file_exists($cwd . '/bundles')) {
            mkdir($cwd . '/bundles', 0755, true);
        }

        $repositoryPath = $cwd . '/repositories/' . $projectAlias;
        $git = new Git($repositoryPath, $timeout);

        if (!file_exists($repositoryPath)) {
            $output->writeln(sprintf('<fg=green>Cloning <fg=cyan>%s</fg=cyan> from <fg=cyan>%s</fg=cyan>...</fg=green> ',
                $projectAlias, $repository));
            $git->setWorkingDirectory($cwd . '/repositories/');
            $git->cloneRepository($repository, $projectAlias);
            $git->setWorkingDirectory($repositoryPath);
        } else {
            $output->writeln(sprintf('<fg=green>Fetching <fg=cyan>%s</fg=cyan>...</fg=green> ', $projectAlias));
            $git->fetch();
        }

        /** @var QuestionHelper $question */
        $question = $this->getHelperSet()->get('question');

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelperSet()->get('formatter');

        /** @var Config $config */
        $config = new Config($cwd);

        try {
            if (!$config->read()) {
                $output->writeln('<fg=red>No previous config found, initializing.</fg=red>');
            } else {
                $output->writeln('<fg=yellow>Previous config found.</fg=yellow>');
                $output->writeln('<fg=yellow>Loading existing config...</fg=yellow> <fg=green>done</fg=green>');
            }
        } catch (GrenadeRuntimeException $e) {
            $output->writeln('<fg=red>failed</fg=red>');
            $output->writeln('<fg=red>An error occured while parsing .grenade.json file.</fg=red>');
            return -1;
        }
        $config->addProject($projectAlias, $repository);

        $codePath = null;
        $bundlesAutocomplete = [];

        if (($count = $config->repositoriesCount($projectAlias)) > 0) {
            if ($count == 1) {
                $output->writeln(sprintf('<fg=green>Found <fg=cyan>%d</fg=cyan> registered bundle:</fg=green> ', $count));
            } else {
                $output->writeln(sprintf('<fg=green>Found <fg=cyan>%d</fg=cyan> registered bundles:</fg=green> ', $count));
            }

            foreach ($config->walkBundles($projectAlias) as $childRepository) {
                $output->writeln(sprintf('<fg=green>  - <fg=cyan>%s</fg=cyan></fg=green>', $childRepository['name']));
            }
        }

        while (true) {
            if ($codePath !== null) {
                $change = 'no' !== $question->ask($input, $output, (new Question('<fg=green>Would you like to change bundles code path? [<fg=yellow>no</fg=yellow>]</fg=green> ', 'no'))->setAutocompleterValues(['yes', 'no']));
                if ($change) {
                    $codePath = null;
                }
            }

            if ($codePath === null) {
                while (true) {
                    $codePath = $question->ask($input, $output, new Question('<fg=green>Enter bundles code path:</fg=green> '));
                    if (!file_exists($repositoryPath . '/' . $codePath)) {
                        $output->writeln(sprintf('<fg=red>Directory <fg=cyan>%s</fg=cyan> does not exist, please enter a valid path.</fg=red>', $codePath));
                        continue;
                    }

                    $bundlesAutocomplete = array_filter(scandir($repositoryPath . '/' . $codePath), function($bundleName) use($repositoryPath, $codePath) {
                        if (strpos($bundleName, 'Bundle') === false || $bundleName === 'Bundle') {
                            return false;
                        }

                        $bundleBootstrapFiles = array_filter(scandir($repositoryPath . '/' . $codePath . '/' . $bundleName), function($fileName) use($bundleName) {
                            return strpos($fileName, $bundleName . '.php') !== false;
                        });
                        if (count($bundleBootstrapFiles) <= 0) {
                            return false;
                        }

                        return true;
                    });

                    if (count($bundlesAutocomplete) <= 0) {
                        $output->writeln(sprintf('<fg=red>No bundles were found in path <fg=cyan>%s</fg=cyan></fg=red>', $codePath));
                        continue;
                    }
                    break;
                }
            }

            while (true) {
                $bundleName = $question->ask($input, $output, (new Question(sprintf('<fg=green>Enter the bundle name: </fg=green> ')))->setAutocompleterValues($bundlesAutocomplete));
                if (!file_exists($repositoryPath . '/' . $codePath . '/' . $bundleName)) {
                    $output->writeln(sprintf('<fg=red>Specified bundle <fg=cyan>%s</fg=cyan> does not exist</fg=red>', $bundleName));
                    continue;
                }
                $bundlesBootstrapFiles = array_filter(scandir($repositoryPath . '/' . $codePath . '/' . $bundleName), function ($item) use ($bundleName) {
                    return strpos($item, $bundleName . '.php') !== false;
                });
                if (count($bundlesBootstrapFiles) <= 0) {
                    $output->writeln(sprintf('<fg=red>Specified bundle <fg=cyan>%s</fg=cyan> doesn\'t seem to be an actual Symfony2 bundle</fg=red>', $bundleName));
                    continue;
                }
                $bundleFullName = basename(current($bundlesBootstrapFiles), '.php');
                break;
            }

            $guessedBundleAlias = preg_replace_callback('/(^[A-Z]|[A-Z])/', function ($matches) {
                return (strlen($matches[1]) > 0 ? '-' : '') . strtolower($matches[1]);
            }, lcfirst($bundleFullName));

            $bundleAlias = $question->ask($input, $output, (new Question(sprintf('<fg=green>Enter a bundle alias: [<fg=yellow>%s</fg=yellow>]</fg=green> ', $guessedBundleAlias)))
                ->setAutocompleterValues([$guessedBundleAlias]));
            if (empty($bundleAlias)) {
                $bundleAlias = $guessedBundleAlias;
            }

            $isOrganization = 'no' !== $question->ask($input, $output, (new Question('<fg=green>Is remote repository owned by an organization? [<fg=yellow>no</fg=yellow>]</fg=green> ', 'no'))->setAutocompleterValues(['yes', 'no']));
            if ($isOrganization) {
                $organizationName = $question->ask($input, $output, new Question('<fg=green>Enter organization name:</fg=green> '));
            } else {
                $organizationName = null;
            }
            $remoteRepository = $question->ask($input, $output, new Question(sprintf('<fg=green>Enter remote repository name: [<fg=yellow>%s</fg=yellow>]</fg=green> ', $bundleAlias)));

            $config->addBundle($projectAlias, $bundleAlias, $bundleFullName, $codePath . '/' . $bundleName);

            if ($organizationName !== null) {
                $config->setBundleRemoteOrganization($projectAlias, $bundleAlias, $organizationName);
            }
            if ($remoteRepository !== null) {
                $config->setBundleRemoteRepository($projectAlias, $bundleAlias, $remoteRepository);
            }

            $continue = 'no' !== $question->ask($input, $output, (new Question('<fg=green>Would you like to add another bundle? [<fg=yellow>no</fg=yellow>]</fg=green> ', 'no'))->setAutocompleterValues(['yes', 'no']));
            if (!$continue) {
                break;
            }
        }

        $config->save();
    }

    private function loadConfig($cwd, OutputInterface $output)
    {
        $repositoriesData = [];
        if (file_exists($cwd . '/.grenade.json')) {
            $output->writeln('<fg=yellow>Previous config found.</fg=yellow>');
            $output->write('<fg=yellow>Loading existing config...</fg=yellow> ');
            $repositoriesData = json_decode(file_get_contents($cwd . '/.grenade.json'), true);
            if ($repositoriesData !== null) {
                $output->writeln('<fg=green>done</fg=green>');
            } else {
                $output->writeln('<fg=red>failed</fg=red>');
                $output->writeln('<fg=red>An error occured while parsing .grenade.json file.</fg=red>');
                return null;
            }
        } else {
            $output->writeln('<fg=yellow>No previous config found, initializing...</fg=yellow>');
        }

        return $repositoriesData;
    }

    private function saveConfig($cwd, array $repositoriesData)
    {
        file_put_contents($cwd . '/.grenade.json', json_encode($repositoriesData, JSON_PRETTY_PRINT));
    }

    /**
     * @param string         $commandline The command line to run
     * @param string|null    $cwd         The working directory or null to use the working dir of the current PHP process
     * @param int|float|null $timeout     The timeout in seconds or null to disable
     *
     * @throws RuntimeException When proc_open is not installed
     *
     * @return Process
     */
    private function process($commandline, $cwd = null, $timeout = 60)
    {
        $process = (new ProcessBuilder($commandline))
            ->setTimeout($timeout)
            ->setWorkingDirectory($cwd)
            ->getProcess()
        ;
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        return $process;
    }
}