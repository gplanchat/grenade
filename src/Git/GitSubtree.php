<?php

namespace Luni\Console\Grenade\Git;

use Symfony\Component\Process\Process;

/**
 * Class GitSubtree
 * @package Luni\Console\Grenade\Git
 * @see http://opensource.apple.com/source/Git/Git-37/src/git/contrib/subtree/git-subtree.sh
 */
class GitSubtree
    implements ProcessBuilderProxyInterface
{
    use ProcessBuilderProxyTrait;

    private $sqash = false;

    /**
     * @param null|string $cwd The working directory
     * @param float|null $timeout
     */
    public function __construct($cwd = null, $timeout = null)
    {
        $this->initProcessBuilder(['git', 'subtree'], $cwd, $timeout);
    }

    /**
     * Disables squash commits
     *
     * @return GitSubtree
     */
    public function disableSquash(): GitSubtree
    {
        $this->sqash = false;

        return $this;
    }

    /**
     * Enables squash commits
     *
     * @return GitSubtree
     */
    public function enableSquash(): GitSubtree
    {
        $this->sqash = true;

        return $this;
    }

    /**
     * @param string $commit
     * @param string $prefix
     * @param callable|null $callback
     * @return Process
     */
    public function addFromCommit(string $commit, string $prefix, callable $callback = null): Process
    {
        $this->processBuilder->setArguments(['add', '-P', $prefix, $commit]);

        if ($this->sqash) {
            $this->processBuilder->add('--squash');
        }

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }

    /**
     * @param string $repository
     * @param string $ref
     * @param string $prefix
     * @param callable|null $callback
     * @return Process
     */
    public function addFromRepository(string $repository, string $ref, string $prefix, callable $callback = null): Process
    {
        $this->processBuilder->setArguments(['add', '-P', $prefix, $repository, $ref]);

        if ($this->sqash) {
            $this->processBuilder->add('--squash');
        }

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }

    /**
     * @param string $repository
     * @param string $ref
     * @param string $prefix
     * @param callable|null $callback
     * @return Process
     */
    public function pull(string $repository, string $ref, string $prefix, callable $callback = null): Process
    {
        $this->processBuilder->setArguments(['pull', '-P', $prefix, $repository, $ref]);

        if ($this->sqash) {
            $this->processBuilder->add('--squash');
        }

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }

    /**
     * @param string $repository
     * @param string $ref
     * @param string $prefix
     * @param callable|null $callback
     * @return Process
     */
    public function push(string $repository, string $ref, string $prefix, callable $callback = null): Process
    {
        $this->processBuilder->setArguments(['push', '-P', $prefix, $repository, $ref]);

        if ($this->sqash) {
            $this->processBuilder->add('--squash');
        }

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }

    /**
     * @param string $commit
     * @param string $prefix
     * @param callable|null $callback
     * @return Process
     */
    public function merge(string $commit, string $prefix, callable $callback = null): Process
    {
        $this->processBuilder->setArguments(['merge', '-P', $prefix, $commit]);

        if ($this->sqash) {
            $this->processBuilder->add('--squash');
        }

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }

    /**
     * @param string $branch
     * @param string $prefix
     * @param string $rejoin
     * @param string $onto
     * @param bool|false $ignoreJoins
     * @param string|null $annotation
     * @param callable|null $callback
     * @return Process
     */
    public function split(
        string $branch,
        string $prefix,
        string $rejoin = null,
        string $onto = null,
        bool $ignoreJoins = false,
        string $annotation = null,
        callable $callback = null
    ): Process
    {
        $this->processBuilder->setArguments(['split', '-P', $prefix, '-b', $branch]);

        if ($rejoin !== null) {
            $this->processBuilder->add(sprintf('--rejoin=%s', $rejoin));

            if ($ignoreJoins) {
                $this->processBuilder->add('--ignore-joins');
            }
        }

        if ($onto !== null) {
            $this->processBuilder->add(sprintf('--onto=%s', $onto));
        }

        if ($annotation !== null) {
            $this->processBuilder->add(sprintf('--annotate="%s"', escapeshellarg($annotation)));
        }

        $process = $this->processBuilder->getProcess();

        $process->run($callback);

        return $process;
    }
}