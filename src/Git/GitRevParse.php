<?php

namespace Luni\Console\Grenade\Git;

use Symfony\Component\Process\Process;

class GitRevParse
    implements ProcessBuilderProxyInterface
{
    use ProcessBuilderProxyTrait;

    /**
     * @param null|string $cwd The working directory
     * @param float|null $timeout
     */
    public function __construct($cwd = null, $timeout = null)
    {
        $this->initProcessBuilder(['git', 'rev-parse'], $cwd, $timeout);
    }

    /**
     * @param string $branch
     * @param callable|null $callback
     * @return Process
     */
    public function verify(string $branch, callable $callback = null): Process
    {
        $this->processBuilder->setArguments(['--verify', $branch]);

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }
}