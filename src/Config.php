<?php

namespace Luni\Console\Grenade;

class Config
{
    /**
     * @var string
     */
    private $workingDirectory;

    /**
     * @var array
     */
    private $repositoriesConfig;

    /**
     * @param string $cwd
     */
    public function __construct(string $cwd)
    {
        $this->workingDirectory = $cwd;
    }

    public function exists()
    {
        return file_exists($this->workingDirectory . '/.grenade.json');
    }

    /**
     * @return bool
     */
    public function read(): bool
    {
        if ($this->exists()) {
            $this->repositoriesConfig = json_decode(file_get_contents($this->workingDirectory . '/.grenade.json'), true);

            if ($this->repositoriesConfig === null) {
                throw new RuntimeException('Invalid .grenade.json data.');
            }

            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function save(): bool
    {
        if ($this->repositoriesConfig === null) {
            throw new RuntimeException('Empty config, aborting .grenade.json overwriting.');
        }

        return 0 < file_put_contents($this->workingDirectory . '/.grenade.json',
            json_encode($this->repositoriesConfig, JSON_PRETTY_PRINT));
    }

    /**
     * @return \Generator
     */
    public function walkProjects(): \Generator
    {
        foreach ($this->repositoriesConfig as $projectName => $projectConfig) {
            yield $projectName => $projectConfig['origin'];
        }
    }

    /**
     * @param string $project
     * @return \Generator
     */
    public function walkRepositories(string $project): \Generator
    {
        if (!isset($this->repositoriesConfig[$project])) {
            throw new RuntimeException('Invalid .grenade.json data.');
        }

        foreach ($this->repositoriesConfig[$project]['repositories'] as $repositoryName => $repositoryConfig) {
            yield $repositoryName => $repositoryConfig;
        }
    }

    /**
     * @param string $project
     * @param string $repository
     * @param string $branch
     * @param string $hash
     */
    public function updateRepositoryBranch(string $project, string $repository, string $branch, string $hash)
    {
        if (!isset($this->repositoriesConfig[$project])) {
            throw new RuntimeException('Invalid project name.');
        }
        if (!isset($this->repositoriesConfig[$project][$repository])) {
            throw new RuntimeException('Invalid repository name.');
        }
        if (!isset($this->repositoriesConfig[$project][$repository]['heads'])) {
            $this->repositoriesConfig[$project][$repository]['heads'] = [];
        }

        $this->repositoriesConfig[$project][$repository]['heads'][$branch] = $hash;
    }
}