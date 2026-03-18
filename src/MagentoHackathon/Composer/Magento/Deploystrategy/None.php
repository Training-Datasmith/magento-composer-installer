<?php

declare(strict_types=1);
/**
 * Composer Magento Installer
 */

namespace MagentoHackathon\Composer\Magento\Deploystrategy;

/**
 * None deploy strategy
 */
class None extends DeploystrategyAbstract
{
    /**
     * Deploy nothing
     *
     * @param string $source
     * @param string $dest
     */
    public function createDelegate($source, $dest): bool
    {
        return true;
    }

    /**
     * Deploy nothing
     *
     * @param string $source
     * @param string $dest
     */
    public function create($source, $dest): bool
    {
        return true;
    }
}
