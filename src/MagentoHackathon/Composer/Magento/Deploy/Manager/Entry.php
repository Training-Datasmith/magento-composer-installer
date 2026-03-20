<?php

declare (strict_types=1);
/**
 *
 *
 *
 *
 */
namespace Magento_Hackathon\Composer\Magento\Deploy\Manager;

class Entry
{
    protected $package_name;
    /**
     * @var \MagentoHackathon\Composer\Magento\Deploystrategy\DeploystrategyAbstract
     */
    protected $deploy_strategy;
    /**
     * @param mixed $packageName
     */
    public function set_package_name($package_name): void
    {
        $this->package_name = $package_name;
    }
    /**
     * @return mixed
     */
    public function get_package_name()
    {
        return $this->package_name;
    }
    /**
     * @param \MagentoHackathon\Composer\Magento\Deploystrategy\DeploystrategyAbstract $deployStrategy
     */
    public function set_deploy_strategy($deploy_strategy): void
    {
        $this->deploy_strategy = $deploy_strategy;
    }
    /**
     * @return \MagentoHackathon\Composer\Magento\Deploystrategy\DeploystrategyAbstract
     */
    public function get_deploy_strategy()
    {
        return $this->deploy_strategy;
    }
}