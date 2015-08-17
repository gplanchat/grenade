<?php

namespace Luni\Console\Grenade;

use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

class CommandFailureException
    extends RuntimeException
{
    private $commandLine;

    public function __construct(Process $process, \Throwable $previous = null)
    {
        parent::__construct($process->getErrorOutput(), $process->getExitCode(), $previous);
        $this->commandLine = $process->getCommandLine();
    }

    /**
     * @return string
     */
    public function getCommandLine()
    {
        return $this->commandLine;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf('%s' . PHP_EOL . '%s returned code %d' . PHP_EOL . '%s',
            $this->getMessage(), $this->getCommandLine(), $this->getCode(), $this->getTraceAsString());
    }
}