<?php

declare (strict_types=1);
/**
 * Composer Magento Installer
 */
namespace Magento_Hackathon\Composer\Magento\Deploystrategy;

use Laminas\Stdlib\Glob;
/**
 * Abstract deploy strategy
 */
abstract class Deploystrategy_Abstract
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
    protected $current_mapping = [];
    /**
     * The List of entries which files should not get deployed
     *
     * @var array
     */
    protected $ignored_mappings = [];
    /**
     * If set overrides existing files
     *
     * @var bool
     */
    protected $is_forced = false;
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
        protected $source_dir,
        /**
         * The magento installation's base directory
         */
        protected $dest_dir
    )
    {
    }
    /**
     * Executes the deployment strategy for each mapping
     *
     * @return \MagentoHackathon\Composer\Magento\Deploystrategy\DeploystrategyAbstract
     */
    public function deploy()
    {
        foreach ($this->get_mappings() as $data) {
            [$source, $dest] = $data;
            $this->set_current_mapping($data);
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
        foreach ($this->get_mappings() as $data) {
            [$source, $dest] = $data;
            $this->remove($source, $dest);
            $this->rm_empty_dirs_recursive(dirname((string) $dest), $this->get_dest_dir());
        }
        return $this;
    }
    /**
     * Returns the destination dir of the magento module
     *
     * @return string
     */
    protected function get_dest_dir()
    {
        return $this->dest_dir;
    }
    /**
     * Returns the current path of the extension
     *
     * @return mixed
     */
    protected function get_source_dir()
    {
        return $this->source_dir;
    }
    /**
     * If set overrides existing files
     *
     * @return bool
     */
    public function is_forced()
    {
        return $this->is_forced;
    }
    /**
     * Setter for isForced property
     *
     * @param bool $forced
     */
    public function set_is_forced($forced = true): void
    {
        $this->is_forced = (bool) $forced;
    }
    /**
     * Returns the path mappings to map project's directories to magento's directory structure
     *
     * @return array
     */
    public function get_mappings()
    {
        return $this->mappings;
    }
    /**
     * Sets path mappings to map project's directories to magento's directory structure
     */
    public function set_mappings(array $mappings): void
    {
        $this->mappings = $mappings;
    }
    /**
     * Gets the current mapping used on the deployment iteration
     *
     * @return array
     */
    public function get_current_mapping()
    {
        return $this->current_mapping;
    }
    /**
     * Sets the current mapping used on the deployment iteration
     *
     * @param array $mapping
     */
    public function set_current_mapping($mapping): void
    {
        $this->current_mapping = $mapping;
    }
    /**
     * sets the current ignored mappings
     *
     * @param $ignoredMappings
     */
    public function set_ignored_mappings($ignored_mappings): void
    {
        $this->ignored_mappings = $ignored_mappings;
    }
    /**
     * gets the current ignored mappings
     *
     * @return array
     */
    public function get_ignored_mappings()
    {
        return $this->ignored_mappings;
    }
    /**
     * @param string $destination
     *
     * @return bool
     */
    protected function is_destination_ignored($destination)
    {
        $destination = '/' . $destination;
        $destination = str_replace('/./', '/', $destination);
        $destination = str_replace('//', '/', $destination);
        foreach ($this->ignored_mappings as $ignored) {
            if (str_starts_with((string) $ignored, $destination)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Add a key value pair to mapping
     */
    public function add_mapping($key, $value): void
    {
        $this->mappings[] = [$key, $value];
    }
    protected function remove_trailing_slash($path)
    {
        return rtrim((string) $path, ' \/');
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
        if ($this->is_destination_ignored($dest)) {
            return;
        }
        $source_path = $this->get_source_dir() . DIRECTORY_SEPARATOR . ltrim((string) $this->remove_trailing_slash($source), DIRECTORY_SEPARATOR);
        $dest_path = $this->get_dest_dir() . DIRECTORY_SEPARATOR . $dest;
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
        if (!file_exists($dest_path) && in_array(substr($dest_path, -1), ['/', '\\']) && !is_dir($source_path)) {
            mkdir($dest_path, 0755, true);
            $dest_path = $this->remove_trailing_slash($dest_path);
        }
        // If source doesn't exist, check if it's a glob expression, otherwise we have nothing we can do
        if (!file_exists($source_path)) {
            // Handle globing
            $matches = Glob::glob($source_path);
            if ($matches) {
                foreach ($matches as $match) {
                    $new_dest = substr($dest_path . '/' . basename($match), strlen($this->get_dest_dir()));
                    $new_dest = ltrim($new_dest, ' \/');
                    $this->create(substr($match, strlen((string) $this->get_source_dir()) + 1), $new_dest);
                }
                return true;
            }
            // Source file isn't a valid file or glob
            throw new \ErrorException("Source {$source_path} does not exist");
        }
        return $this->create_delegate($source, $dest);
    }
    /**
     * Remove (unlink) the destination file
     *
     * @param string $source
     * @throws \ErrorException
     */
    public function remove($source, string $dest): void
    {
        if ($this->is_destination_ignored($dest)) {
            return;
        }
        $source_path = $this->get_source_dir() . '/' . $this->remove_trailing_slash($source);
        $dest_path = $this->get_dest_dir() . '/' . $dest;
        // If source doesn't exist, check if it's a glob expression, otherwise we have nothing we can do
        if (!file_exists($source_path)) {
            $this->remove_content_of_category($source_path, $dest_path);
            return;
        }
        // If source doesn't exist, check if it's a glob expression, otherwise we have nothing we can do
        if (is_dir($source_path)) {
            $this->remove_content_of_category($source_path . '/*', $dest_path);
            @rmdir($dest_path);
            return;
        }
        // MP Avoid removing whole folders in case the modman file is not 100% well-written
        // e.g. app/etc/modules/Testmodule.xml  app/etc/modules/ installs correctly, but would otherwise delete the whole app/etc/modules folder!
        if (basename($source_path) !== basename($dest_path)) {
            $dest_path .= '/' . basename($source);
        }
        self::rmdir_recursive($dest_path);
    }
    /**
     * Search and remove content of category
     *
     * @param string $sourcePath
     * @throws \ErrorException
     */
    protected function remove_content_of_category($source_path, string $dest_path)
    {
        $source_path = preg_replace('#/\*$#', '/{,.}*', $source_path);
        $matches = Glob::glob($source_path, Glob::GLOB_BRACE);
        if ($matches) {
            foreach ($matches as $match) {
                if (preg_match("#/\\.{1,2}\$#", $match)) {
                    continue;
                }
                $new_dest = substr($dest_path . '/' . basename($match), strlen($this->get_dest_dir()));
                $new_dest = ltrim($new_dest, ' \/');
                $this->remove(substr($match, strlen((string) $this->get_source_dir()) + 1), $new_dest);
            }
            return;
        }
        // Source file isn't a valid file or glob
        throw new \ErrorException("Source {$source_path} does not exist");
    }
    /**
     * Remove an empty directory branch up to $stopDir, or stop at the first non-empty parent.
     *
     * @param string $stopDir
     */
    public function rm_empty_dirs_recursive(string $dir, $stop_dir = null): void
    {
        $absolute_dir = $this->get_dest_dir() . '/' . $dir;
        if (is_dir($absolute_dir)) {
            $iterator = new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator($absolute_dir), \Recursive_Iterator_Iterator::CHILD_FIRST);
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
            if (@rmdir($absolute_dir)) {
                // If the parent directory doesn't match the $stopDir and it's empty, remove it, too
                $parent_dir = dirname($dir);
                $absolute_parent_dir = $this->get_dest_dir() . '/' . $parent_dir;
                if (!isset($stop_dir) || realpath($stop_dir) !== realpath($absolute_parent_dir)) {
                    // Remove the parent directory if it is empty
                    $this->rm_empty_dirs_recursive($parent_dir);
                }
            }
        }
    }
    /**
     * Recursively removes the specified directory or file
     *
     * @param $dir
     */
    public static function rmdir_recursive($dir): void
    {
        $fs = new \Composer\Util\Filesystem();
        if (is_dir($dir)) {
            $result = $fs->remove_directory($dir);
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
    abstract protected function create_delegate($source, $dest);
}