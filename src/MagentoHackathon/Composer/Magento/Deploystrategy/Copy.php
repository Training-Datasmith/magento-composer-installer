<?php

declare (strict_types=1);
/**
 * Composer Magento Installer
 */
namespace Magento_Hackathon\Composer\Magento\Deploystrategy;

/**
 * Symlink deploy strategy
 */
class Copy extends Deploystrategy_Abstract
{
    /**
     * copy files
     *
     * @param string $source
     * @param string $dest
     * @return bool
     * @throws \ErrorException
     */
    public function create_delegate($source, $dest)
    {
        [$map_source, $map_dest] = $this->get_current_mapping();
        $map_source = $this->remove_trailing_slash($map_source);
        $map_dest = $this->remove_trailing_slash($map_dest);
        $clean_dest = $this->remove_trailing_slash($dest);
        $source_path = $this->get_source_dir() . DIRECTORY_SEPARATOR . ltrim((string) $this->remove_trailing_slash($source), DIRECTORY_SEPARATOR);
        $dest_path = $this->get_dest_dir() . DIRECTORY_SEPARATOR . ltrim((string) $this->remove_trailing_slash($dest), DIRECTORY_SEPARATOR);
        // Create all directories up to one below the target if they don't exist
        $dest_dir = dirname($dest_path);
        if (!file_exists($dest_dir)) {
            mkdir($dest_dir, 0755, true);
        }
        // Handle source to dir copy,
        // e.g. Namespace_Module.csv => app/locale/de_DE/
        // Namespace/ModuleDir => Namespace/
        // Namespace/ModuleDir => Namespace/, but Namespace/ModuleDir may exist
        // Namespace/ModuleDir => Namespace/ModuleDir, but ModuleDir may exist
        // first iteration through, we need to update the mappings to correctly handle mismatch globs
        if ($map_source == $this->remove_trailing_slash($source) && $map_dest == $this->remove_trailing_slash($dest)) {
            if (basename($source_path) !== basename($dest_path)) {
                $this->set_current_mapping([$map_source, $map_dest . DIRECTORY_SEPARATOR . basename($source)]);
                $clean_dest = $clean_dest . DIRECTORY_SEPARATOR . basename($source);
            }
        }
        if (file_exists($dest_path) && is_dir($dest_path)) {
            $map_source = rtrim((string) $map_source, '*');
            $map_source_len = empty($map_source) ? 0 : strlen($map_source);
            if (strcmp(substr(ltrim((string) $clean_dest, DIRECTORY_SEPARATOR), strlen((string) $map_dest)), substr(ltrim($source, DIRECTORY_SEPARATOR), $map_source_len)) === 0) {
                // copy each child of $sourcePath into $destPath
                foreach (new \Directory_Iterator($source_path) as $item) {
                    $item = (string) $item;
                    if (!strcmp($item, '.')) {
                        continue;
                    }
                    if (!strcmp($item, '..')) {
                        continue;
                    }
                    $child_source = $this->remove_trailing_slash($source) . DIRECTORY_SEPARATOR . $item;
                    $this->create($child_source, substr($dest_path, strlen($this->get_dest_dir()) + 1));
                }
                return true;
            }
            $dest_path = $this->remove_trailing_slash($dest_path) . DIRECTORY_SEPARATOR . basename($source);
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
                $dest_path .= DIRECTORY_SEPARATOR . basename($source_path);
            }
            return copy($source_path, $dest_path);
        }
        // Copy dir to dir
        // First create destination folder if it doesn't exist
        if (file_exists($dest_path)) {
            $dest_path .= DIRECTORY_SEPARATOR . basename($source_path);
        }
        mkdir($dest_path, 0755, true);
        $iterator = new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator($source_path), \Recursive_Iterator_Iterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $sub_dest_path = $dest_path . DIRECTORY_SEPARATOR . $iterator->get_sub_path_name();
            if ($item->is_dir()) {
                if (!file_exists($sub_dest_path)) {
                    mkdir($sub_dest_path, 0755, true);
                }
            } else {
                copy($item, $sub_dest_path);
            }
            if (!is_readable($sub_dest_path)) {
                throw new \ErrorException("Could not create {$sub_dest_path}");
            }
        }
        return true;
    }
}