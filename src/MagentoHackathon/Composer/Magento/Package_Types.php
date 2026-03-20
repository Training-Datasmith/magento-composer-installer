<?php

declare (strict_types=1);
namespace Magento_Hackathon\Composer\Magento;

/**
 * Class PackageTypes
 * @package MagentoHackathon\Composer\Magento
 */
class Package_Types
{
    /**
     * Package Types supported by Installer
     * @var array
     */
    public static $package_types = ['magento2-module' => '/app/code/', 'magento2-theme' => '/app/design/', 'magento2-library' => '/lib/internal/', 'magento2-language' => '/app/i18n/', 'magento2-component' => './'];
}