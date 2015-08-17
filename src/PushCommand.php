<?php

namespace Luni\Console\Grenade;

use Github\Api\CurrentUser;
use Github\Api\Organization;
use Github\Api\Repo;
use Github\Client;
use Luni\Console\Grenade\Git\Git;
use Luni\Console\Grenade\RuntimeException as GrenadeRuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\ProcessBuilder;

class PushCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('grenade:push')
            ->setDescription('')
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
        $cwd = getcwd() . '/' . $input->getOption('working-dir');
        $timeout = (int) $input->getOption('timeout');

        /** @var QuestionHelper $question */
        $question = $this->getHelperSet()->get('question');

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

        $github = new Client();
        /** @var Repo $repoApi */
        $repoApi = $github->api('repo');
        /** @var CurrentUser $userApi */
        $userApi = $github->api('me');
        /** @var Organization $orgsApi */
        $orgsApi = $github->api('organization');

        /** @var Git $git */
        $git = new Git(null, $timeout);

        $failures = 0;
        $githubUsername = $question->ask($input, $output, new Question('<fg=green>Your Github account:</fg=green> '));
        while (true) {
            $githubPassword = $question->ask($input, $output, (new Question('<fg=green>Your Github password:</fg=green> '))->setHidden(true));
            $github->authenticate($githubUsername, $githubPassword, Client::AUTH_HTTP_PASSWORD);

            try {
                $userApi->emails()->all();
                break;
            } catch (\Github\Exception\RuntimeException $e) {
                $output->writeln(sprintf('<fg=red>%s</fg=red>', $e->getMessage()));
            }

            if (++$failures >= 3) {
                $output->writeln('<fg=red>Too many connection failures, please check your credentials.</fg=red>');
                return -1;
            }
        }

        if (!file_exists($cwd . '/bundles')) {
            $output->writeln('<fg=red>Bundles folder does not exist, some repositories should exist.</fg=red>');
            return -1;
        }

        foreach ($config->walkProjects() as $projectAlias => $projectConfig) {
            foreach ($config->walkBundles($projectAlias) as $bundleAlias => $bundleRepository) {
                $output->writeln(sprintf('<fg=green>Uploading <fg=cyan>%s</fg=cyan>.</fg=green>', $bundleAlias));

                $bundleRepositoryPath = $cwd . '/bundles/' . $bundleAlias;
                if (($organization = $config->getBundleRemoteOrganization($projectAlias, $bundleAlias)) !== null) {
                    $repositoriesList = $orgsApi->repositories($organization);
                } else {
                    $repositoriesList = $userApi->repositories();
                }

                $repositoryInfo = null;
                foreach ($repositoriesList as $remoteRepositoryInfo) {
                    if ($remoteRepositoryInfo['name'] == $bundleAlias) {
                        $repositoryInfo = $remoteRepositoryInfo;
                        break;
                    }
                }

                $git->setWorkingDirectory($bundleRepositoryPath);
                if ($repositoryInfo === null) {
                    $output->writeln(sprintf('<fg=green>Creating a new repository <fg=cyan>%s</fg=cyan>.</fg=green>', $bundleAlias));
                    $repositoryInfo = $repoApi->create($bundleAlias,
                        sprintf('[READONLY] %s mirror repository', $config->getBundleName($projectAlias, $bundleAlias)),
                        '', true, $organization);

                    if (isset($repositoryInfo['ssh_url'])) {
                        $git->remote()->add('origin', $repositoryInfo['ssh_url']);
                    }
                }

                if (!isset($repositoryInfo['ssh_url'])) {
                    throw new RuntimeException('The repository info has not returned a git URL.');
                }

                $git->pushToMirror('origin');
            }
        }
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
    private function process($commandline, $cwd = null, $timeout = 60, $callback = null)
    {
        $process = (new ProcessBuilder($commandline))
            ->setTimeout($timeout)
            ->setWorkingDirectory($cwd)
            ->getProcess()
        ;
/*
        if (($code = $process->run($callback)) !== 0)= {
            throw new RuntimeException(sprintf('Command %s returned code [%d].', $process->getCommandLine(), $code));
        }
*/
        $process->run($callback);

        if (!$process->isSuccessful()) {
            var_dump($process->getWorkingDirectory());
            var_dump($process->getCommandLine());
            throw new \RuntimeException($process->getErrorOutput());
        }

        return $process;
    }
}