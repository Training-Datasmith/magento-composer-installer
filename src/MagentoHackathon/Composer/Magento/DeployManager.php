<?php

declare(strict_types=1);
/**
 *
 *
 *
 *
 */

namespace MagentoHackathon\Composer\Magento;

use Composer\IO\IOInterface;
use MagentoHackathon\Composer\Magento\Deploy\Manager\Entry;
use MagentoHackathon\Composer\Magento\Deploystrategy\Copy;

class DeployManager
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
    protected $sortPriority = [];

    /**
     * High priority
     *
     * An array of packages that must have high priority for deployment
     * For packages that need to be deployed before all other packages
     */
    private array $highPriority = [
        'magento/magento2-base' => 10,
    ];

    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }

    public function addPackage(Entry $package): void
    {
        $this->packages[] = $package;
    }

    public function setSortPriority($priorities): void
    {
        $this->sortPriority = $priorities;
    }

    /**
     * Uses the sortPriority Array to sort the packages.
     *
     * Highest priority first.
     * Copy gets per default higher priority then others
     *
     * @return array
     */
    protected function sortPackages()
    {
        usort(
            $this->packages,
            function (\MagentoHackathon\Composer\Magento\Deploy\Manager\Entry $a, \MagentoHackathon\Composer\Magento\Deploy\Manager\Entry $b): int {
                $aPriority = $this->getPackagePriority($a);
                $bPriority = $this->getPackagePriority($b);
                return $bPriority <=> $aPriority;
            }
        );

        return $this->packages;
    }

    public function doDeploy(): void
    {
        $this->sortPackages();

        /** @var Entry $package */
        foreach ($this->packages as $package) {
            if ($this->io->isDebug()) {
                $this->io->write('start magento deploy for ' . $package->getPackageName());
            }
            try {
                $package->getDeployStrategy()->deploy();
            } catch (\ErrorException $e) {
                if ($this->io->isDebug()) {
                    $this->io->write($e->getMessage());
                }
            }
        }
    }

    /**
     * Determine the priority in which the package should be deployed
     *
     * @return int
     */
    private function getPackagePriority(Entry $package)
    {
        $result = 100;
        $maxPriority = max(array_merge($this->sortPriority, [100, 101]));

        if (isset($this->highPriority[$package->getPackageName()])) {
            $packagePriority = $this->highPriority[$package->getPackageName()];
            $result = intval($maxPriority) + intval($packagePriority);
        } elseif (isset($this->sortPriority[$package->getPackageName()])) {
            $result = $this->sortPriority[$package->getPackageName()];
        } elseif ($package->getDeployStrategy() instanceof Copy) {
            $result = 101;
        }

        return $result;
    }

}
