<?php

namespace Luni\Console\Grenade;

use Github\Api\CurrentUser;
use Github\Api\Organization;
use Luni\Console\Grenade\Git\Git;
use Luni\Console\Grenade\Git\GitSubtreeProgressBarHelper;
use Luni\Console\Grenade\RuntimeException as GrenadeRuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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

        /** @var Config $config */
        $config = new Config($cwd);

        try {
            if (!$config->read()) {
                $output->writeln('<fg=red>No previous config found, please run grenade:config</fg=red>');
                return -1;
            }

            $output->writeln('<fg=yellow>Previous config found.</fg=yellow>');
            $output->writeln('<fg=yellow>Loading existing config...</fg=yellow> <fg=green>done</fg=green>');
        } catch (GrenadeRuntimeException $e) {
            $output->writeln('<fg=red>failed</fg=red>');
            $output->writeln('<fg=red>An error occured while parsing .grenade.json file.</fg=red>');
            return -1;
        }

        if (!file_exists($cwd . '/repositories')) {
            $output->writeln('<fg=yellow>Repositories folder does not exist, repositories should already be cloned.</fg=yellow>');
            mkdir($cwd . '/repositories', 0755, true);
        }

        if (!file_exists($cwd . '/bundles')) {
            mkdir($cwd . '/bundles', 0755, true);
        }

        /** @var GitSubtreeProgressBarHelper $progressBarHelper */
        $progressBarHelper = new GitSubtreeProgressBarHelper($output);

        foreach ($config->walkProjects() as $projectName => $projectRemote) {
            $repositoryPath = $cwd . '/repositories/' . $projectName;

            $originalGit = new Git($repositoryPath, $timeout);
            $bundleGit = new Git(null, $timeout);
            if (!file_exists($repositoryPath)) {
                $output->writeln(sprintf('<fg=green>Cloning <fg=cyan>%s</fg=cyan> repository.</fg=green>', $projectName));

                $originalGit->setWorkingDirectory($cwd . '/repositories/');
                $process = $originalGit->cloneRepository($projectRemote, $projectName);
                if (!$process->isSuccessful()) {
                    throw new CommandFailureException($process);
                }
                $originalGit->setWorkingDirectory($repositoryPath);
            } else {
                $output->writeln(sprintf('<fg=green>Fetching <fg=cyan>%s</fg=cyan> repository.</fg=green>', $projectName));

                $process = $originalGit->fetch();
                if (!$process->isSuccessful()) {
                    throw new CommandFailureException($process);
                }
            }

            foreach ($config->walkRepositories($projectName) as $repositoryAlias => $repositoryConfig) {
                $output->writeln(sprintf('<fg=green>Analyzing bundle <fg=cyan>%s</fg=cyan>...</fg=green>', $repositoryConfig['name']));

                foreach ($originalGit->branchList(false, true, 'origin') as $branchAlias => $branchName) {
                    $output->writeln(sprintf('  <fg=green>Exporting branch <fg=cyan>%s</fg=cyan>...</fg=green>', $branchName));
                    $process = $originalGit->revParse()->verify('grenade/bundles/' . $repositoryAlias . '/' . $branchAlias);

                    $bundleRepositoryPath = $cwd . '/bundles/' . $repositoryAlias;
                    if (!$process->isSuccessful() || !file_exists($bundleRepositoryPath)) {
                        $output->writeln(sprintf('  <fg=green>Splitting into a new <fg=cyan>%s</fg=cyan> branch.</fg=green>',
                            'grenade/bundles/' . $repositoryAlias . '/' . $branchAlias));

                        $progressBarHelper->reset();
                        $process = $originalGit->subtree()->split(
                            'grenade/bundles/' . $repositoryAlias . '/' . $branchAlias,
                            $repositoryConfig['path'], null, 'origin/' . $branchAlias, false, null, $progressBarHelper);
                        $progressBarHelper->finish();

                        if (!$process->isSuccessful()) {
                            throw new CommandFailureException($process);
                        }

                        $bundleGit->setWorkingDirectory($bundleRepositoryPath);
                        if (!file_exists($bundleRepositoryPath)) {
                            $output->writeln(sprintf('  <fg=green>Initializing git repository for <fg=cyan>%s</fg=cyan> bundle.</fg=green>',
                                $repositoryConfig['name']));

                            mkdir($bundleRepositoryPath, 0755, true);
                            $bundleGit->init();
                        }

                        $output->writeln(sprintf('  <fg=green>Pulling branch <fg=cyan>%s</fg=cyan> in the bundle repository.</fg=green>',
                            'grenade/bundles/' . $repositoryAlias . '/' . $branchName));
                        $bundleGit->pull($repositoryPath, 'grenade/bundles/' . $repositoryAlias . '/' . $branchName);
                    } else {
                        $output->writeln(sprintf('  <fg=green>Updating the <fg=cyan>%s</fg=cyan> bundle\'s code into the <fg=cyan>%s</fg=cyan> branch.</fg=green>',
                            $repositoryConfig['name'], 'grenade/bundles/' . $repositoryAlias));

                        $progressBarHelper->reset();
                        $process = $originalGit->subtree()->push(
                            $bundleRepositoryPath, 'grenade/bundles/' . $repositoryAlias . '/' . $branchName,
                            $repositoryConfig['path'], $progressBarHelper);
                        $progressBarHelper->finish();

                        if (!$process->isSuccessful()) {
                            throw new CommandFailureException($process);
                        }
                        break;
                    }

                    $process = $originalGit->revParse()->verify('grenade/bundles/' . $repositoryAlias . '/' . $branchName);
                    if ($process->isSuccessful()) {
                        $config->updateRepositoryBranch($projectName, $repositoryAlias, $branchName, $process->getOutput());
                    }
                }
            }
        }

        $config->save();

        return 0;
    }
}