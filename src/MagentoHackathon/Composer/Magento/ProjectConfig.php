<?php

declare (strict_types=1);
/**
 *
 *
 *
 *
 */
namespace Magento_Hackathon\Composer\Magento;

class Project_Config
{
    protected $library_path;
    protected $library_packages;
    public function __construct(array $extra)
    {
        $this->apply_deprecated_root_configs($extra);
        if (isset($extra['magento-project'])) {
            $this->apply_magento_config($extra['magento-project']);
        }
    }
    protected function fetch_var_from_config_array(array $array, $key, $default = null)
    {
        return $array[$key] ?? $default;
    }
    protected function apply_deprecated_root_configs($root_config)
    {
    }
    protected function apply_magento_config($config)
    {
        $this->library_path = $this->fetch_var_from_config_array($config, 'libraryPath');
        $this->library_packages = $this->fetch_var_from_config_array($config, 'libraries');
    }
    public function get_library_path()
    {
        return $this->library_path;
    }
    public function get_library_config_by_packagename($packagename)
    {
        return $this->fetch_var_from_config_array($this->library_packages, $packagename);
    }
}