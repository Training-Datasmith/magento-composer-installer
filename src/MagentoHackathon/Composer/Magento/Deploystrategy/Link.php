<?php

declare (strict_types=1);
/**
 * Composer Magento Installer
 */
namespace Magento_Hackathon\Composer\Magento\Deploystrategy;

/**
 * Hardlink deploy strategy
 */
class Link extends Deploystrategy_Abstract
{
    /**
     * Creates a hardlink with lots of error-checking
     *
     * @param string $source
     * @param string $dest
     * @return bool
     * @throws \ErrorException
     */
    public function create_delegate($source, $dest)
    {
        $source_path = $this->get_source_dir() . '/' . $this->remove_trailing_slash($source);
        $dest_path = $this->get_dest_dir() . '/' . $this->remove_trailing_slash($dest);
        // Create all directories up to one below the target if they don't exist
        $dest_dir = dirname($dest_path);
        if (!file_exists($dest_dir)) {
            mkdir($dest_dir, 0777, true);
        }
        // Handle source to dir link,
        // e.g. Namespace_Module.csv => app/locale/de_DE/
        if (file_exists($dest_path) && is_dir($dest_path)) {
            if (basename($source_path) === basename($dest_path)) {
                // copy/link each child of $sourcePath into $destPath
                foreach (new \Directory_Iterator($source_path) as $item) {
                    $item = (string) $item;
                    if (!strcmp($item, '.')) {
                        continue;
                    }
                    if (!strcmp($item, '..')) {
                        continue;
                    }
                    $child_source = $source . '/' . $item;
                    $this->create($child_source, substr($dest_path, strlen($this->get_dest_dir()) + 1));
                }
                return true;
            }
            $dest_path .= '/' . basename($source);
            return $this->create($source, substr($dest_path, strlen($this->get_dest_dir()) + 1));
        }
        // From now on $destPath can't be a directory, that case is already handled
        // If file exists and force is not specified, throw exception unless FORCE is set
        if (file_exists($dest_path)) {
            if ($this->is_forced()) {
                unlink($dest_path);
            } else {
                throw new \ErrorException("Target {$dest} already exists (set extra.magento-force to override)");
            }
        }
        // File to file
        if (!is_dir($source_path)) {
            if (is_dir($dest_path)) {
                $dest_path .= '/' . basename($source_path);
            }
            return link($source_path, $dest_path);
        }
        // Copy dir to dir
        // First create destination folder if it doesn't exist
        if (file_exists($dest_path)) {
            $dest_path .= '/' . basename($source_path);
        }
        mkdir($dest_path, 0777, true);
        $iterator = new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator($source_path), \Recursive_Iterator_Iterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $sub_dest_path = $dest_path . '/' . $iterator->get_sub_path_name();
            if ($item->is_dir()) {
                if (!file_exists($sub_dest_path)) {
                    mkdir($sub_dest_path, 0777, true);
                }
            } else {
                link($item, $sub_dest_path);
            }
            if (!is_readable($sub_dest_path)) {
                throw new \ErrorException("Could not create {$sub_dest_path}");
            }
        }
        return true;
    }
}