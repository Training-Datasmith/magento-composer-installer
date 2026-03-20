<?php

declare (strict_types=1);
/**
 * Composer Magento Installer
 */
namespace Magento_Hackathon\Composer\Magento;

/**
 * Parsers modman files
 */
class Modman_Parser extends Path_Translation_Parser
{
    /**
     * @var string Path to vendor module dir
     */
    protected $_module_dir;
    /**
     * @var \SplFileObject The modman file
     */
    protected $_file;
    /**
     * Constructor
     *
     * @param string $moduleDir
     */
    public function __construct($module_dir = null, $translations = [], $path_suffix = '')
    {
        parent::__construct($translations, $path_suffix);
        $this->set_module_dir($module_dir);
        $this->set_file($this->get_modman_file());
    }
    /**
     * Sets the module directory where to search for the modman file
     *
     * @param string $moduleDir
     */
    public function set_module_dir($module_dir): static
    {
        // Remove trailing slash
        if ($module_dir !== null) {
            $module_dir = rtrim($module_dir, '\/');
        }
        $this->_module_dir = $module_dir;
        return $this;
    }
    /**
     * @return string
     */
    public function get_module_dir()
    {
        return $this->_module_dir;
    }
    /**
     * @param string|SplFileObject $file
     */
    public function set_file($file): static
    {
        if (is_string($file)) {
            $file = new \Spl_File_Object($file);
        }
        $this->_file = $file;
        return $this;
    }
    /**
     * @return \SplFileObject
     */
    public function get_file()
    {
        return $this->_file;
    }
    /**
     * @return string
     */
    public function get_modman_file(): ?\Spl_File_Object
    {
        if ($this->_module_dir !== null) {
            return new \Spl_File_Object($this->_module_dir . '/modman');
        }
        return null;
    }
    /**
     * @return array
     * @throws \ErrorException
     */
    public function get_mappings()
    {
        $file = $this->get_file();
        if (!$file->is_readable()) {
            throw new \ErrorException(sprintf('modman file "%s" not readable', $file->get_pathname()));
        }
        $map = $this->_parse_mappings();
        return $this->translate_path_mappings($map);
    }
    /**
     * @throws \ErrorException
     */
    protected function _parse_mappings(): array
    {
        $map = [];
        $line = 0;
        foreach ($this->_file as $row) {
            $line++;
            $row = trim($row);
            if ('' === $row) {
                continue;
            }
            if (in_array($row[0], ['#', '@'])) {
                continue;
            }
            $parts = preg_split('/\s+/', $row, 2, PREG_SPLIT_NO_EMPTY);
            if (count($parts) != 2) {
                throw new \ErrorException(sprintf('Invalid row on line %d has %d parts, expected 2', $line, count($row)));
            }
            $map[] = $parts;
        }
        return $map;
    }
}