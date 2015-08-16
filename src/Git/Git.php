<?php

namespace Luni\Console\Grenade\Git;

use Symfony\Component\Process\Process;

class Git
    implements ProcessBuilderProxyInterface
{
    use ProcessBuilderProxyTrait;

    /**
     * @param null|string $cwd The working directory
     * @param float|null $timeout
     */
    public function __construct($cwd = null, $timeout = null)
    {
        $this->initProcessBuilder(['git'], $cwd, $timeout);
    }

    /**
     * @param string $message
     * @param callable|null $callback
     * @return Process
     */
    public function commit(string $message, callable $callback = null): Process
    {
        $this->processBuilder->setArguments(['commit']);

        if ($message !== null) {
            $this->processBuilder->add('-m');
            $this->processBuilder->add($message);
        }

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }

    /**
     * @param callable|null $callback
     * @return Process
     */
    public function init(callable $callback = null): Process
    {
        $this->processBuilder->setArguments(['init']);

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }

    /**
     * @param string $remoteRepository
     * @param string $destination
     * @param callable $callback
     * @return Process
     */
    public function cloneRepository($remoteRepository, $destination = null, callable $callback = null): Process
    {
        $this->processBuilder->setArguments(['clone', $remoteRepository]);

        if ($destination !== null) {
            $this->processBuilder->add($destination);
        }

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }

    /**
     * @param string $remoteRepository
     * @param callable $callback
     * @return Process
     */
    public function cloneBare($remoteRepository, callable $callback = null): Process
    {
        $this->processBuilder->setArguments(['clone', $remoteRepository, '--bare']);

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }

    /**
     * @param string $remoteRepository
     * @param callable $callback
     * @return Process
     */
    public function fetch(string $remoteRepository = null, callable $callback = null): Process
    {
        $this->processBuilder->setArguments(['fetch']);

        if ($remoteRepository !== null) {
            $this->processBuilder->add($remoteRepository);
        }

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }

    /**
     * @param string|null $remoteRepository
     * @param string|null $branch
     * @param callable|null $callback
     * @return Process
     */
    public function pull(string $remoteRepository = null, string $branch = null, callable $callback = null): Process
    {
        $this->processBuilder->setArguments(['pull']);

        if ($remoteRepository !== null) {
            $this->processBuilder->add($remoteRepository);

            if ($branch !== null) {
                $this->processBuilder->add($branch);
            }
        }

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }

    /**
     * @return GitRevParse
     */
    public function revParse(): GitRevParse
    {
        return new GitRevParse($this->getWorkingDirectory(), $this->getTimeout());
    }

    /**
     * @return GitSubtree
     */
    public function subtree(): GitSubtree
    {
        return new GitSubtree($this->getWorkingDirectory(), $this->getTimeout());
    }

/*
    public function config(callable $callback = null): Process {}
    public function add(callable $callback = null): Process {}
    public function rm(callable $callback = null): Process {}
    public function status(callable $callback = null): Process {}
    public function branch(callable $callback = null): Process {}
    public function checkout(callable $callback = null): Process {}
    public function merge(callable $callback = null): Process {}
    public function reset(callable $callback = null): Process {}
    public function stash(callable $callback = null): Process {}
    public function tag(callable $callback = null): Process {}
    public function push(callable $callback = null): Process {}
    public function remote(callable $callback = null): Process {}
    public function show(callable $callback = null): Process {}
    public function lsTree(callable $callback = null): Process {}
    public function catFile(callable $callback = null): Process {}
    public function grep(callable $callback = null): Process {}
    public function diff(callable $callback = null): Process {}
    public function archive(callable $callback = null): Process {}
    public function gc(callable $callback = null): Process {}
    public function fsck(callable $callback = null): Process {}
    public function prune(callable $callback = null): Process {}
*/
}