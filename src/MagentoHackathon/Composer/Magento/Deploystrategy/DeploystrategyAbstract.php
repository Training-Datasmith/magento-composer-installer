<?php

declare(strict_types=1);
/**
 * Composer Magento Installer
 */

namespace MagentoHackathon\Composer\Magento\Deploystrategy;

use Laminas\Stdlib\Glob;

/**
 * Abstract deploy strategy
 */
abstract class DeploystrategyAbstract
{
    /**
     * The path mappings to map project's directories to magento's directory structure
     *
     * @var array
     */
    protected $mappings = [];

    /**
     * The current mapping of the deployment iteration
     *
     * @var array
     */
    protected $currentMapping = [];

    /**
     * The List of entries which files should not get deployed
     *
     * @var array
     */
    protected $ignoredMappings = [];

    /**
     * If set overrides existing files
     *
     * @var bool
     */
    protected $isForced = false;

    /**
     * Constructor
     *
     * @param string $sourceDir
     * @param string $destDir
     */
    public function __construct(
        /**
         * The module's base directory
         */
        protected $sourceDir,
        /**
         * The magento installation's base directory
         */
        protected $destDir
    ) {
    }

    /**
     * Executes the deployment strategy for each mapping
     *
     * @return \MagentoHackathon\Composer\Magento\Deploystrategy\DeploystrategyAbstract
     */
    public function deploy()
    {
        foreach ($this->getMappings() as $data) {
            [$source, $dest] = $data;
            $this->setCurrentMapping($data);
            $this->create($source, $dest);
        }
        return $this;
    }

    /**
     * Removes the module's files in the given path from the target dir
     *
     * @return \MagentoHackathon\Composer\Magento\Deploystrategy\DeploystrategyAbstract
     */
    public function clean()
    {
        foreach ($this->getMappings() as $data) {
            [$source, $dest] = $data;
            $this->remove($source, $dest);
            $this->rmEmptyDirsRecursive(dirname((string) $dest), $this->getDestDir());
        }
        return $this;
    }

    /**
     * Returns the destination dir of the magento module
     *
     * @return string
     */
    protected function getDestDir()
    {
        return $this->destDir;
    }

    /**
     * Returns the current path of the extension
     *
     * @return mixed
     */
    protected function getSourceDir()
    {
        return $this->sourceDir;
    }

    /**
     * If set overrides existing files
     *
     * @return bool
     */
    public function isForced()
    {
        return $this->isForced;
    }

    /**
     * Setter for isForced property
     *
     * @param bool $forced
     */
    public function setIsForced($forced = true): void
    {
        $this->isForced = (bool) $forced;
    }

    /**
     * Returns the path mappings to map project's directories to magento's directory structure
     *
     * @return array
     */
    public function getMappings()
    {
        return $this->mappings;
    }

    /**
     * Sets path mappings to map project's directories to magento's directory structure
     */
    public function setMappings(array $mappings): void
    {
        $this->mappings = $mappings;
    }

    /**
     * Gets the current mapping used on the deployment iteration
     *
     * @return array
     */
    public function getCurrentMapping()
    {
        return $this->currentMapping;
    }

    /**
     * Sets the current mapping used on the deployment iteration
     *
     * @param array $mapping
     */
    public function setCurrentMapping($mapping): void
    {
        $this->currentMapping = $mapping;
    }

    /**
     * sets the current ignored mappings
     *
     * @param $ignoredMappings
     */
    public function setIgnoredMappings($ignoredMappings): void
    {
        $this->ignoredMappings = $ignoredMappings;
    }

    /**
     * gets the current ignored mappings
     *
     * @return array
     */
    public function getIgnoredMappings()
    {
        return $this->ignoredMappings;
    }

    /**
     * @param string $destination
     *
     * @return bool
     */
    protected function isDestinationIgnored($destination)
    {
        $destination = '/'.$destination;
        $destination = str_replace('/./', '/', $destination);
        $destination = str_replace('//', '/', $destination);
        foreach ($this->ignoredMappings as $ignored) {
            if (str_starts_with((string) $ignored, $destination)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add a key value pair to mapping
     */
    public function addMapping($key, $value): void
    {
        $this->mappings[] = [$key, $value];
    }

    protected function removeTrailingSlash($path)
    {
        return rtrim((string) $path, ' \\/');
    }

    /**
     * Normalize mapping parameters using a glob wildcard.
     *
     * Delegate the creation of the module's files in the given destination.
     *
     * @param string $source
     * @throws \ErrorException
     * @return bool
     */
    public function create($source, string $dest)
    {
        if ($this->isDestinationIgnored($dest)) {
            return;
        }

        $sourcePath = $this->getSourceDir() . DIRECTORY_SEPARATOR
            . ltrim((string) $this->removeTrailingSlash($source), DIRECTORY_SEPARATOR);
        $destPath = $this->getDestDir() . DIRECTORY_SEPARATOR . $dest;

        /* List of possible cases, keep around for now, might come in handy again

        Assume app/etc exists, app/etc/a does not exist unless specified differently

        dir app/etc/a/ --> link app/etc/a to dir
        dir app/etc/a  --> link app/etc/a to dir
        dir app/etc/   --> link app/etc/dir to dir
        dir app/etc    --> link app/etc/dir to dir

        dir/* app/etc     --> for each dir/$file create a target link in app/etc
        dir/* app/etc/    --> for each dir/$file create a target link in app/etc
        dir/* app/etc/a   --> for each dir/$file create a target link in app/etc/a
        dir/* app/etc/a/  --> for each dir/$file create a target link in app/etc/a

        file app/etc    --> link app/etc/file to file
        file app/etc/   --> link app/etc/file to file
        file app/etc/a  --> link app/etc/a to file
        file app/etc/a  --> if app/etc/a is a file throw exception unless force is set, in that case rm and see above
        file app/etc/a/ --> link app/etc/a/file to file regardless if app/etc/a exists or not

        */

        // Create target directory if it ends with a directory separator
        if (!file_exists($destPath)
            && in_array(substr($destPath, -1), ['/', '\\'])
            && !is_dir($sourcePath)
        ) {
            mkdir($destPath, 0755, true);
            $destPath = $this->removeTrailingSlash($destPath);
        }

        // If source doesn't exist, check if it's a glob expression, otherwise we have nothing we can do
        if (!file_exists($sourcePath)) {
            // Handle globing
            $matches = Glob::glob($sourcePath);
            if ($matches) {
                foreach ($matches as $match) {
                    $newDest = substr($destPath . '/' . basename($match), strlen($this->getDestDir()));
                    $newDest = ltrim($newDest, ' \\/');
                    $this->create(substr($match, strlen((string) $this->getSourceDir()) + 1), $newDest);
                }
                return true;
            }

            // Source file isn't a valid file or glob
            throw new \ErrorException("Source $sourcePath does not exist");
        }
        return $this->createDelegate($source, $dest);
    }

    /**
     * Remove (unlink) the destination file
     *
     * @param string $source
     * @throws \ErrorException
     */
    public function remove($source, string $dest): void
    {
        if ($this->isDestinationIgnored($dest)) {
            return;
        }

        $sourcePath = $this->getSourceDir() . '/' . $this->removeTrailingSlash($source);
        $destPath = $this->getDestDir() . '/' . $dest;
        // If source doesn't exist, check if it's a glob expression, otherwise we have nothing we can do
        if (!file_exists($sourcePath)) {
            $this->removeContentOfCategory($sourcePath, $destPath);
            return;
        }

        // If source doesn't exist, check if it's a glob expression, otherwise we have nothing we can do
        if (is_dir($sourcePath)) {
            $this->removeContentOfCategory($sourcePath . '/*', $destPath);
            @rmdir($destPath);
            return;
        }

        // MP Avoid removing whole folders in case the modman file is not 100% well-written
        // e.g. app/etc/modules/Testmodule.xml  app/etc/modules/ installs correctly, but would otherwise delete the whole app/etc/modules folder!
        if (basename($sourcePath) !== basename($destPath)) {
            $destPath .= '/' . basename($source);
        }
        self::rmdirRecursive($destPath);
    }

    /**
     * Search and remove content of category
     *
     * @param string $sourcePath
     * @throws \ErrorException
     */
    protected function removeContentOfCategory($sourcePath, string $destPath)
    {
        $sourcePath = preg_replace('#/\*$#', '/{,.}*', $sourcePath);
        $matches = Glob::glob($sourcePath, Glob::GLOB_BRACE);
        if ($matches) {
            foreach ($matches as $match) {
                if (preg_match("#/\.{1,2}$#", $match)) {
                    continue;
                }
                $newDest = substr($destPath . '/' . basename($match), strlen($this->getDestDir()));
                $newDest = ltrim($newDest, ' \\/');
                $this->remove(substr($match, strlen((string) $this->getSourceDir()) + 1), $newDest);
            }
            return;
        }

        // Source file isn't a valid file or glob
        throw new \ErrorException("Source $sourcePath does not exist");
    }

    /**
     * Remove an empty directory branch up to $stopDir, or stop at the first non-empty parent.
     *
     * @param string $stopDir
     */
    public function rmEmptyDirsRecursive(string $dir, $stopDir = null): void
    {
        $absoluteDir = $this->getDestDir() . '/' . $dir;
        if (is_dir($absoluteDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absoluteDir),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                $path = (string) $item;
                if (!strcmp($path, '.')) {
                    continue;
                }
                if (!strcmp($path, '..')) {
                    continue;
                }
                // The directory contains something, do not remove
                return;
            }
            // RecursiveIteratorIterator have opened handle on $absoluteDir
            // that cause Windows to block the directory and not remove it until
            // the iterator will be destroyed.
            unset($iterator);

            // The specified directory is empty
            if (@rmdir($absoluteDir)) {
                // If the parent directory doesn't match the $stopDir and it's empty, remove it, too
                $parentDir = dirname($dir);
                $absoluteParentDir = $this->getDestDir() . '/' . $parentDir;
                if (! isset($stopDir) || (realpath($stopDir) !== realpath($absoluteParentDir))) {
                    // Remove the parent directory if it is empty
                    $this->rmEmptyDirsRecursive($parentDir);
                }
            }
        }
    }

    /**
     * Recursively removes the specified directory or file
     *
     * @param $dir
     */
    public static function rmdirRecursive($dir): void
    {
        $fs = new \Composer\Util\Filesystem();
        if (is_dir($dir)) {
            $result = $fs->removeDirectory($dir);
        } else {
            @unlink($dir);
        }
    }

    /**
     * Create the module's files in the given destination.
     *
     * NOTE: source and dest have to be passed as relative directories, like they are listed in the mapping
     *
     * @param string $source
     * @param string $dest
     * @return bool
     */
    abstract protected function createDelegate($source, $dest);

}
