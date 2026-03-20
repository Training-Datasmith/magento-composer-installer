<?php

declare (strict_types=1);
/**
 *
 *
 *
 *
 */
namespace Magento_Hackathon\Composer\Magento;

use Composer\IO\Io_Interface;
use Magento_Hackathon\Composer\Magento\Deploy\Manager\Entry;
use Magento_Hackathon\Composer\Magento\Deploystrategy\Copy;
class Deploy_Manager
{
    /**
     * @var Entry[]
     */
    protected $packages = [];
    /**
     * @var IOInterface
     */
    protected $io;
    /**
     * an array with package names as key and priorities as value
     *
     * @var array
     */
    protected $sort_priority = [];
    /**
     * High priority
     *
     * An array of packages that must have high priority for deployment
     * For packages that need to be deployed before all other packages
     */
    private array $high_priority = ['magento/magento2-base' => 10];
    public function __construct(Io_Interface $io)
    {
        $this->io = $io;
    }
    public function add_package(Entry $package): void
    {
        $this->packages[] = $package;
    }
    public function set_sort_priority($priorities): void
    {
        $this->sort_priority = $priorities;
    }
    /**
     * Uses the sortPriority Array to sort the packages.
     *
     * Highest priority first.
     * Copy gets per default higher priority then others
     *
     * @return array
     */
    protected function sort_packages()
    {
        usort($this->packages, function (\Magento_Hackathon\Composer\Magento\Deploy\Manager\Entry $a, \Magento_Hackathon\Composer\Magento\Deploy\Manager\Entry $b): int {
            $a_priority = $this->get_package_priority($a);
            $b_priority = $this->get_package_priority($b);
            return $b_priority <=> $a_priority;
        });
        return $this->packages;
    }
    public function do_deploy(): void
    {
        $this->sort_packages();
        /** @var Entry $package */
        foreach ($this->packages as $package) {
            if ($this->io->is_debug()) {
                $this->io->write('start magento deploy for ' . $package->get_package_name());
            }
            try {
                $package->get_deploy_strategy()->deploy();
            } catch (\ErrorException $e) {
                if ($this->io->is_debug()) {
                    $this->io->write($e->get_message());
                }
            }
        }
    }
    /**
     * Determine the priority in which the package should be deployed
     *
     * @return int
     */
    private function get_package_priority(Entry $package)
    {
        $result = 100;
        $max_priority = max(array_merge($this->sort_priority, [100, 101]));
        if (isset($this->high_priority[$package->get_package_name()])) {
            $package_priority = $this->high_priority[$package->get_package_name()];
            $result = intval($max_priority) + intval($package_priority);
        } elseif (isset($this->sort_priority[$package->get_package_name()])) {
            $result = $this->sort_priority[$package->get_package_name()];
        } elseif ($package->get_deploy_strategy() instanceof Copy) {
            $result = 101;
        }
        return $result;
    }
}