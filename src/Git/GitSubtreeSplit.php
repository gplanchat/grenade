<?php
/**
 * Created by PhpStorm.
 * User: gplanchat
 * Date: 17/08/2015
 * Time: 16:59
 */

namespace Luni\Console\Grenade\Git;


use Symfony\Component\Process\ProcessBuilder;

class GitSubtreeSplit
{
    public function __invoke(
        string $branch,
        string $prefix,
        string $rejoin = null,
        string $onto = null,
        bool $ignoreJoins = false,
        string $annotation = null,
        callable $callback = null
    ) {
        $processBuilder = new ProcessBuilder();
        $cache = [];

        if ($onto !== null) {
            $processBuilder->setArguments(['git', 'rev-list', $onto]);
            $process = $processBuilder->getProcess();


            $process->run();

            foreach (explode(PHP_EOL, $process->getOutput()) as $revision) {
                $cache[$revision] = $revision;
            };
        }

        if ($ignoreJoins !== false) {
            $this->findExistingSplits($prefix, $branch);
        }
    }

    private function findExistingSplits(string $prefix, string $branch)
    {
        $processBuilder = new ProcessBuilder();
        $processBuilder->setArguments(['git', 'log', '--grep="^git-subtree-dir: ' . $prefix . '/*\$"',
            '--pretty=format:"START %H%n%s%n%n%b%nEND%n"', $branch]);

        $process = $processBuilder->getProcess();

        foreach (explode(PHP_EOL, $process->getOutput()) as $revision) {
            if (preg_match('#^([^\\s]+)\\s+([^\\s]+)[^\\n]*$#', $revision))
            $cache[$revision] = $revision;
        };
    }
}