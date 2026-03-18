<?php

declare(strict_types=1);
/**
 *
 *
 *
 *
 */

namespace MagentoHackathon\Composer\Magento;

class ProjectConfig
{
    protected $libraryPath;
    protected $libraryPackages;

    public function __construct(array $extra)
    {
        $this->applyDeprecatedRootConfigs($extra);
        if (isset($extra['magento-project'])) {
            $this->applyMagentoConfig($extra['magento-project']);
        }
    }

    protected function fetchVarFromConfigArray(array $array, $key, $default = null)
    {
        return $array[$key] ?? $default;
    }

    protected function applyDeprecatedRootConfigs($rootConfig)
    {

    }

    protected function applyMagentoConfig($config)
    {
        $this->libraryPath          = $this->fetchVarFromConfigArray($config, 'libraryPath');
        $this->libraryPackages      = $this->fetchVarFromConfigArray($config, 'libraries');

    }

    public function getLibraryPath()
    {
        return $this->libraryPath;
    }

    public function getLibraryConfigByPackagename($packagename)
    {
        return $this->fetchVarFromConfigArray($this->libraryPackages, $packagename);
    }

}
