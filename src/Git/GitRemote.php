<?php

namespace Luni\Console\Grenade\Git;

use Symfony\Component\Process\Process;

class GitRemote
    implements ProcessBuilderProxyInterface
{
    use ProcessBuilderProxyTrait;

    /**
     * @param null|string $cwd The working directory
     * @param float|null $timeout
     */
    public function __construct($cwd = null, $timeout = null)
    {
        $this->initProcessBuilder(['git', 'remote'], $cwd, $timeout);
    }

    /**
     * @param string $branch
     * @param callable|null $callback
     * @return Process
     */
    public function add(string $name, string $path, callable $callback = null): Process
    {
        $this->processBuilder->setArguments(['add', $name, $path]);

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }
}