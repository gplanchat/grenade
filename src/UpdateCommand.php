<?php

namespace Luni\Console\Grenade;

use Github\Api\CurrentUser;
use Github\Api\Organization;
use Github\Api\Repo;
use Github\Client;
use Luni\Console\Grenade\Git\Git;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\ProcessBuilder;

class UpdateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('grenade:update')
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

        $repositoriesConfig = $this->loadConfig($cwd, $output);
        if ($repositoriesConfig === null) {
            return -1;
        }

        if (!file_exists($cwd . '/repositories')) {
            $output->writeln('<fg=yellow>Repositories folder does not exist, repositories should already be cloned.</fg=yellow>');
            mkdir($cwd . '/repositories', 0755, true);
        }

        if (!file_exists($cwd . '/bundles')) {
            mkdir($cwd . '/bundles', 0755, true);
        }

        foreach ($repositoriesConfig as $repositoryInfo) {
            $repositoryPath = $cwd . '/repositories/' . $repositoryInfo['alias'];

            $originalGit = new Git($repositoryPath, $timeout);
            $bundleGit = new Git(null, $timeout);
            if (!file_exists($repositoryPath)) {
                $output->writeln(sprintf('<fg=green>Cloning <fg=cyan>%s</fg=cyan> repository.</fg=green>', $repositoryInfo['alias']));

                $process = $originalGit->cloneRepository($repositoryInfo['remote'], $repositoryInfo['alias'], $cwd . '/repositories/');
                if (!$process->isSuccessful()) {
                    throw new CommandFailureException($process);
                }
            } else {
                $output->writeln(sprintf('<fg=green>Fetching <fg=cyan>%s</fg=cyan> repository.</fg=green>', $repositoryInfo['alias']));

                $process = $originalGit->fetch();
                if (!$process->isSuccessful()) {
                    throw new CommandFailureException($process);
                }
            }

            foreach ($repositoryInfo['repositories'] as $bundleRepository) {
                $process = $originalGit->revParse()->verify('grenade/bundles/' . $bundleRepository['alias']);

                $bundleRepositoryPath = $cwd . '/bundles/' . $bundleRepository['alias'];
                if (!$process->isSuccessful() || !file_exists($bundleRepositoryPath)) {
                    $output->writeln(sprintf('<fg=green>Splitting the bundle <fg=cyan>%s</fg=cyan> into a new <fg=cyan>%s</fg=cyan> branch.</fg=green>',
                        $bundleRepository['name'], 'grenade/bundles/' . $bundleRepository['alias']));

                    /** @var ProgressBar $progress */
                    $progress = null;

                    $process = $originalGit->subtree()->split(
                        'grenade/bundles/' . $bundleRepository['alias'], $bundleRepository['path'], null, null, false, null,
                        function($type, $buffer) use(&$progress, $output) {
                        if ($type === 'err' && preg_match('/^-n\s*\d+\s*\/\s*(\d+)\s*\((\d+)\)\s*$/', $buffer, $matches)) {
                            if ($progress === null) {
                                $progress = new ProgressBar($output, (int) $matches[1]);
                                $progress->setBarWidth(100);
                                $progress->start();
                            }

                            $progress->advance();
                        }
                    });

                    if ($progress !== null) {
                        $progress->finish();
                        $output->writeln('');
                    }
                    if (!$process->isSuccessful()) {
                        throw new CommandFailureException($process);
                    }

                    $bundleGit->setWorkingDirectory($bundleRepositoryPath);
                    if (!file_exists($bundleRepositoryPath)) {
                        $output->writeln(sprintf('<fg=green>Initializing git repository for <fg=cyan>%s</fg=cyan> bundle.</fg=green>',
                            $bundleRepository['name']));

                        mkdir($bundleRepositoryPath, 0755, true);
                        $bundleGit->init();
                    }

                    $bundleGit->pull($repositoryPath, 'grenade/bundles/' . $bundleRepository['alias']);
                } else {
                    $output->writeln(sprintf('<fg=green>Updating the <fg=cyan>%s</fg=cyan> bundle\'s code into the <fg=cyan>%s</fg=cyan> branch.</fg=green>',
                        $bundleRepository['name'], 'grenade/bundles/' . $bundleRepository['alias']));

                    $process = $originalGit->subtree()->push(
                        $bundleRepositoryPath, 'grenade/bundles/' . $bundleRepository['alias'], $bundleRepository['path'],
                        function($type, $buffer) use(&$progress, $output) {
                        if ($type === 'err' && preg_match('/^-n\s*\d+\s*\/\s*(\d+)\s*\((\d+)\)\s*$/', $buffer, $matches)) {
                            if ($progress === null) {
                                $progress = new ProgressBar($output, (int) $matches[1]);
                                $progress->setBarWidth(100);
                                $progress->start();
                            }

                            $progress->advance();
                        }
                    });

                    if ($progress !== null) {
                        $progress->finish();
                        $output->writeln('');
                    }

                    if (!$process->isSuccessful()) {
                        throw new CommandFailureException($process);
                    }

                    continue;
                }
            }
        }
        return 0;
    }

    private function loadConfig($cwd, OutputInterface $output)
    {
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
            $output->writeln('<fg=red>No previous config found, please run grenade:config</fg=red>');
            return null;
        }

        return $repositoriesData;
    }
}