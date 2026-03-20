<?php

declare (strict_types=1);
/**
 * Composer Magento Installer
 */
namespace Magento_Hackathon\Composer\Magento\Deploystrategy;

/**
 * None deploy strategy
 */
class None extends Deploystrategy_Abstract
{
    /**
     * Deploy nothing
     *
     * @param string $source
     * @param string $dest
     */
    public function create_delegate($source, $dest): bool
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