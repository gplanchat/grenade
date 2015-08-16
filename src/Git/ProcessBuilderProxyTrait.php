<?php

namespace Luni\Console\Grenade\Git;

use Symfony\Component\Process\Exception\InvalidArgumentException;
use Symfony\Component\Process\ProcessBuilder;

trait ProcessBuilderProxyTrait
{
    /**
     * @var ProcessBuilder
     */
    private $processBuilder;

    /**
     * @var string
     */
    private $cwd = null;

    /**
     * @var float
     */
    private $timeout = null;

    /**
     * @param string|array $prefix A command prefix or an array of command prefixes
     * @param null|string $cwd The working directory
     * @param float|null $timeout
     */
    private function initProcessBuilder(array $prefix = [], $cwd = null, $timeout = null)
    {
        $this->processBuilder = new ProcessBuilder();
        $this->processBuilder->setPrefix($prefix);

        if ($cwd !== null) {
            $this->setWorkingDirectory($cwd);
        }
        if ($timeout !== null) {
            $this->setTimeout($timeout);
        }
    }

    /**
     * Sets the working directory.
     *
     * @param null|string $cwd The working directory
     *
     * @return ProcessBuilderProxyInterface
     */
    public function setWorkingDirectory(string $cwd): ProcessBuilderProxyInterface
    {
        $this->cwd = $cwd;
        $this->processBuilder->setWorkingDirectory($cwd);

        return $this;
    }

    /**
     * Sets whether environment variables will be inherited or not.
     *
     * @param bool $inheritEnv
     *
     * @return ProcessBuilderProxyInterface
     */
    public function inheritEnvironmentVariables(bool $inheritEnv = true): ProcessBuilderProxyInterface
    {
        $this->processBuilder->inheritEnvironmentVariables($inheritEnv);

        return $this;
    }

    /**
     * Sets an environment variable.
     *
     * Setting a variable overrides its previous value. Use `null` to unset a
     * defined environment variable.
     *
     * @param string      $name  The variable name
     * @param null|string $value The variable value
     *
     * @return ProcessBuilderProxyInterface
     */
    public function setEnv(string $name, string $value): ProcessBuilderProxyInterface
    {
        $this->processBuilder->setEnv($name, $value);

        return $this;
    }

    /**
     * Adds a set of environment variables.
     *
     * Already existing environment variables with the same name will be
     * overridden by the new values passed to this method. Pass `null` to unset
     * a variable.
     *
     * @param array $variables The variables
     *
     * @return ProcessBuilderProxyInterface
     */
    public function addEnvironmentVariables(array $variables): ProcessBuilderProxyInterface
    {
        $this->processBuilder->addEnvironmentVariables($variables);

        return $this;
    }

    /**
     * Sets the input of the process.
     *
     * @param string $input The input as a string
     *
     * @return ProcessBuilderProxyInterface
     *
     * @throws InvalidArgumentException In case the argument is invalid
     *
     * Passing an object as an input is deprecated since version 2.5 and will be removed in 3.0.
     */
    public function setInput(string $input): ProcessBuilderProxyInterface
    {
        $this->processBuilder->setInput($input);

        return $this;
    }

    /**
     * Sets the process timeout.
     *
     * To disable the timeout, set this value to null.
     *
     * @param float|null $timeout
     *
     * @return ProcessBuilderProxyInterface
     *
     * @throws InvalidArgumentException
     */
    public function setTimeout(float $timeout): ProcessBuilderProxyInterface
    {
        $this->timeout = $timeout;
        $this->processBuilder->setTimeout($timeout);

        return $this;
    }

    /**
     * Adds a proc_open option.
     *
     * @param string $name  The option name
     * @param string $value The option value
     *
     * @return ProcessBuilderProxyInterface
     */
    public function setOption(string $name, string $value): ProcessBuilderProxyInterface
    {
        $this->processBuilder->setOption($name, $value);

        return $this;
    }

    /**
     * Disables fetching output and error output from the underlying process.
     *
     * @return ProcessBuilderProxyInterface
     */
    public function disableOutput(): ProcessBuilderProxyInterface
    {
        $this->processBuilder->disableOutput();

        return $this;
    }

    /**
     * Enables fetching output and error output from the underlying process.
     *
     * @return ProcessBuilderProxyInterface
     */
    public function enableOutput(): ProcessBuilderProxyInterface
    {
        $this->processBuilder->enableOutput();

        return $this;
    }

    /**
     * @return string
     */
    protected function getWorkingDirectory(): string
    {
        return $this->cwd;
    }

    /**
     * @return float
     */
    protected function getTimeout(): float
    {
        return $this->timeout;
    }
}