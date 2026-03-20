<?php

declare (strict_types=1);
/**
 * Composer Magento Installer
 */
namespace Magento_Hackathon\Composer\Magento\Deploystrategy;

/**
 * Symlink deploy strategy
 */
class Symlink extends Deploystrategy_Abstract
{
    /**
     * Creates a symlink with lots of error-checking
     *
     * @param string $source
     * @param string $dest
     * @return bool
     * @throws \ErrorException
     */
    public function create_delegate($source, $dest)
    {
        $source_path = $this->get_source_dir() . DIRECTORY_SEPARATOR . $this->remove_trailing_slash($source);
        $dest_path = $this->get_dest_dir() . DIRECTORY_SEPARATOR . $this->remove_trailing_slash($dest);
        if (!is_file($source_path) && !is_dir($source_path)) {
            throw new \ErrorException("Could not find path '{$source_path}'");
        }
        /*
        Assume app/etc exists, app/etc/a does not exist unless specified differently
        OK dir app/etc/a  --> link app/etc/a to dir
                OK dir app/etc/   --> link app/etc/dir to dir
                OK dir app/etc    --> link app/etc/dir to dir
        OK dir/* app/etc     --> for each dir/$file create a target link in app/etc
                OK dir/* app/etc/    --> for each dir/$file create a target link in app/etc
                OK dir/* app/etc/a   --> for each dir/$file create a target link in app/etc/a
                OK dir/* app/etc/a/  --> for each dir/$file create a target link in app/etc/a
        OK file app/etc    --> link app/etc/file to file
                OK file app/etc/   --> link app/etc/file to file
                OK file app/etc/a  --> link app/etc/a to file
                OK file app/etc/a  --> if app/etc/a is a file throw exception unless force is set, in that case rm and see above
                OK file app/etc/a/ --> link app/etc/a/file to file regardless if app/etc/a existst or not
        */
        // Symlink already exists
        if (is_link($dest_path)) {
            if (realpath(readlink($dest_path)) == realpath($source_path)) {
                // .. and is equal to current source-link
                return true;
            }
            unlink($dest_path);
        }
        // Create all directories up to one below the target if they don't exist
        $dest_dir = dirname($dest_path);
        if (!file_exists($dest_dir)) {
            mkdir($dest_dir, 0755, true);
        }
        // Handle source to dir linking,
        // e.g. Namespace_Module.csv => app/locale/de_DE/
        // Namespace/ModuleDir => Namespace/
        // Namespace/ModuleDir => Namespace/, but Namespace/ModuleDir may exist
        // Namespace/ModuleDir => Namespace/ModuleDir, but ModuleDir may exist
        if (file_exists($dest_path) && is_dir($dest_path)) {
            if (basename($source_path) === basename($dest_path)) {
                if ($this->is_forced()) {
                    $this->rmdir_recursive($dest_path);
                } else {
                    throw new \ErrorException("Target {$dest} already exists (set extra.magento-force to override)");
                }
            } else {
                $dest_path .= '/' . basename($source);
            }
            return $this->create($source, substr($dest_path, strlen($this->get_dest_dir()) + 1));
        }
        // From now on $destPath can't be a directory, that case is already handled
        // If file exists and force is not specified, throw exception unless FORCE is set
        // existing symlinks are already handled
        if (file_exists($dest_path)) {
            if ($this->is_forced()) {
                unlink($dest_path);
            } else {
                throw new \ErrorException("Target {$dest} already exists and is not a symlink (set extra.magento-force to override)");
            }
        }
        // Windows doesn't allow relative symlinks
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $source_path = $this->get_relative_path($dest_path, $source_path);
        }
        // Create symlink
        if (false === symlink($source_path, $dest_path)) {
            throw new \ErrorException('An error occured while creating symlink' . $source_path);
        }
        // Check we where able to create the symlink
        if (false === $dest_path = readlink($dest_path)) {
            throw new \ErrorException("Symlink {$dest_path} points to target {$dest_path}");
        }
        return true;
    }
    /**
     * Returns the relative path from $from to $to
     * This is utility method for symlink creation.
     *
     * @param string $from
     * @param string $to
     */
    public function get_relative_path($from, $to): string
    {
        $from = str_replace(['/./', '//', '\\'], '/', $from);
        $to = str_replace(['/./', '//', '\\'], '/', $to);
        if (is_file($from)) {
            $from = dirname($from);
        } else {
            $from = rtrim($from, '/');
        }
        $dir = explode('/', $from);
        $file = explode('/', $to);
        while ($file && $dir && $dir[0] == $file[0]) {
            array_shift($file);
            array_shift($dir);
        }
        // magento_dir/targetdir/childdir => ../../module_dir/sourcedir/childdir
        $relative_path = str_repeat('../', count($dir)) . implode('/', $file);
        return $relative_path;
    }
}