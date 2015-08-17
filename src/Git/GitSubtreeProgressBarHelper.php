<?php

namespace Luni\Console\Grenade\Git;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class GitSubtreeProgressBarHelper
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var ProgressBar|null
     */
    private $progressBar;

    /**
     * @var int
     */
    private $updates;

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param string $type
     * @param string $buffer
     */
    public function __invoke(string $type, string $buffer)
    {
        if ($type === 'err' && preg_match('/^-n\s*\d+\s*\/\s*(\d+)\s*\((\d+)\)\s*$/', $buffer, $matches)) {
            if ($this->progressBar === null) {
                $this->progressBar = new ProgressBar($this->output, (int) $matches[1]);
                $this->progressBar->setBarWidth(100);
                $this->progressBar->start();
            }

            $this->progressBar->advance();
            $this->updates = $matches[2];
        }
    }

    public function finish()
    {
        if ($this->progressBar !== null) {
            $this->progressBar->finish();
            $this->output->writeln('');
        }
    }

    public function reset()
    {
        $this->progressBar = null;
        $this->updates = null;
    }

    /**
     * @return int
     */
    public function updatesCount()
    {
        return $this->updates;
    }
}