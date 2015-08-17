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
            json_encode($this->repositoriesConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param string $alias
     * @param string $origin
     */
    public function addProject(string $alias, string $origin)
    {
        $this->repositoriesConfig[$alias] = [
            'origin'  => $origin,
            'alias'   => $alias,
            'bundles' => []
        ];
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
     * @return int
     */
    public function projectCount(): int
    {
        return count($this->repositoriesConfig);
    }

    /**
     * @param string $projectAlias
     * @param string $alias
     * @param string $bundleName
     * @param string $path
     */
    public function addBundle(string $projectAlias, string $alias, string $bundleName, string $path)
    {
        if (!isset($this->repositoriesConfig[$projectAlias])) {
            throw new RuntimeException(sprintf('Project %s does not exist.', $projectAlias));
        }
        if (!isset($this->repositoriesConfig[$projectAlias]['bundles'])) {
            $this->repositoriesConfig[$projectAlias]['bundles'] = [];
        }

        $this->repositoriesConfig[$projectAlias]['bundles'][$alias] = [
            'alias'             => $alias,
            'name'              => $bundleName,
            'path'              => $path,
            'heads'             => []
        ];
    }

    /**
     * @param string $projectAlias
     * @param string $bundleAlias
     * @return null|string
     */
    public function getBundleName(string $projectAlias, string $bundleAlias): string
    {
        if (!isset($this->repositoriesConfig[$projectAlias]['bundles'][$bundleAlias])) {
            throw new RuntimeException(sprintf('Bundle %s was not configured in project %s.',
                $bundleAlias, $projectAlias));
        }
        if (!isset($this->repositoriesConfig[$projectAlias]['bundles'][$bundleAlias]['name'])) {
            return null;
        }

        return $this->repositoriesConfig[$projectAlias]['bundles'][$bundleAlias]['name'];
    }

    /**
     * @param string $projectAlias
     * @param string $bundleAlias
     * @param string $organizationName
     */
    public function setBundleRemoteOrganization(string $projectAlias, string $bundleAlias, string $organizationName)
    {
        if (!isset($this->repositoriesConfig[$projectAlias]['bundles'])) {
            throw new RuntimeException('Invalid .grenade.json data.');
        }
        if (!isset($this->repositoriesConfig[$projectAlias]['bundles'][$bundleAlias])) {
            throw new RuntimeException(sprintf('Bundle %s was not configured in project %s.', $bundleAlias, $projectAlias));
        }
        $this->repositoriesConfig[$projectAlias]['bundles'][$bundleAlias]['organization'] = $organizationName;
    }

    /**
     * @param string $projectAlias
     * @param string $bundleAlias
     * @return null|string
     */
    public function getBundleRemoteOrganization(string $projectAlias, string $bundleAlias): string
    {
        if (!isset($this->repositoriesConfig[$projectAlias]['bundles'][$bundleAlias])) {
            throw new RuntimeException(sprintf('Bundle %s was not configured in project %s.',
                $bundleAlias, $projectAlias));
        }
        if (!isset($this->repositoriesConfig[$projectAlias]['bundles'][$bundleAlias]['organization'])) {
            return null;
        }

        return $this->repositoriesConfig[$projectAlias]['bundles'][$bundleAlias]['organization'];
    }

    /**
     * @param string $projectAlias
     * @param string $bundleAlias
     * @param string $remoteRepository
     */
    public function setBundleRemoteRepository(string $projectAlias, string $bundleAlias, string $remoteRepository)
    {
        if (!isset($this->repositoriesConfig[$projectAlias]['bundles'])) {
            throw new RuntimeException('Invalid .grenade.json data.');
        }
        if (!isset($this->repositoriesConfig[$projectAlias]['bundles'][$bundleAlias])) {
            throw new RuntimeException(sprintf('Bundle %s was not configured in project %s.', $bundleAlias, $projectAlias));
        }
        $this->repositoriesConfig[$projectAlias]['bundles'][$bundleAlias]['remote-repository'] = $remoteRepository;
    }

    /**
     * @param string $projectAlias
     * @param string $bundleAlias
     * @return string
     */
    public function getBundleRemoteRepository(string $projectAlias, string $bundleAlias): string
    {
        if (!isset($this->repositoriesConfig[$projectAlias]['bundles'][$bundleAlias]['remote-repository'])) {
            throw new RuntimeException(sprintf('Bundle %s\'s remote repository was not configured in project %s.',
                $bundleAlias, $projectAlias));
        }

        return $this->repositoriesConfig[$projectAlias]['bundles'][$bundleAlias]['remote-repository'];
    }

    /**
     * @param string $projectAlias
     * @return \Generator
     */
    public function walkBundles(string $projectAlias): \Generator
    {
        if (!isset($this->repositoriesConfig[$projectAlias]['bundles'])) {
            throw new RuntimeException('Invalid .grenade.json data.');
        }

        foreach ($this->repositoriesConfig[$projectAlias]['bundles'] as $repositoryName => $repositoryConfig) {
            yield $repositoryName => $repositoryConfig;
        }
    }

    /**
     * @return int
     */
    public function repositoriesCount(string $project): int
    {
        if (!isset($this->repositoriesConfig[$project]['bundles'])) {
            return 0;
        }

        return count($this->repositoriesConfig[$project]['bundles']);
    }

    /**
     * @param string $project
     * @param string $repository
     * @param string $branch
     * @param string $hash
     */
    public function updateBundleBranch(string $project, string $repository, string $branch, string $hash)
    {
        if (!isset($this->repositoriesConfig[$project])) {
            throw new RuntimeException('Invalid project name.');
        }
        if (!isset($this->repositoriesConfig[$project]['bundles'])) {
            throw new RuntimeException('Uninitialized project.');
        }
        if (!isset($this->repositoriesConfig[$project]['bundles'][$repository])) {
            throw new RuntimeException('Invalid repository name.');
        }
        if (!isset($this->repositoriesConfig[$project]['bundles'][$repository]['heads'])) {
            $this->repositoriesConfig[$project]['bundles'][$repository]['heads'] = [];
        }

        $this->repositoriesConfig[$project]['bundles'][$repository]['heads'][$branch] = trim($hash);
    }

    /**
     * @param string $project
     * @param string $repository
     * @param string $branch
     * @param string $hash
     * @return bool
     */
    public function compareBundleBranchHead(string $project, string $repository, string $branch, string $hash): bool
    {
        if (!isset($this->repositoriesConfig[$project])) {
            return false;
        }
        if (!isset($this->repositoriesConfig[$project]['bundles'])) {
            return false;
        }
        if (!isset($this->repositoriesConfig[$project]['bundles'][$repository])) {
            return false;
        }
        if (!isset($this->repositoriesConfig[$project]['bundles'][$repository]['heads'])) {
            return false;
        }

        return $this->repositoriesConfig[$project]['bundles'][$repository]['heads'][$branch] === trim($hash);
    }
}