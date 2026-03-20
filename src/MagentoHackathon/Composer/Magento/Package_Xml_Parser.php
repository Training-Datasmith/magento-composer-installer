<?php

declare (strict_types=1);
/**
 * Composer Magento Installer
 */
namespace Magento_Hackathon\Composer\Magento;

/**
 * Parses Magento Connect 2.0 package.xml files
 */
class Package_Xml_Parser extends Path_Translation_Parser
{
    /**
     * @var string Path to vendor module dir
     */
    protected $_module_dir;
    /**
     * @var \SplFileObject The package.xml file
     */
    protected $_file;
    /**
     * @var array Map of package content types to path prefixes
     */
    protected $_targets = [];
    /**
     * Constructor
     *
     * @param string $moduleDir
     * @param string $packageXmlFile
     * @param array $translations
     * @param string $pathSuffix
     */
    public function __construct($module_dir, $package_xml_file, $translations = [], $path_suffix = '')
    {
        parent::__construct($translations, $path_suffix);
        $this->set_module_dir($module_dir);
        $this->set_file($this->get_module_dir() . '/' . $package_xml_file);
    }
    /**
     * Sets the module directory where to search for the package.xml file
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
     * @return array
     * @throws \ErrorException
     */
    public function get_mappings()
    {
        $file = $this->get_file();
        if (!$file->is_readable()) {
            throw new \ErrorException(sprintf('Package file "%s" not readable', $file->get_pathname()));
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
        /** @var $package SimpleXMLElement */
        $package = simplexml_load_file($this->get_file()->get_pathname());
        foreach ($package->xpath('//contents/target') as $target) {
            $base_path = $this->get_target_path($target);
            foreach ($target->children() as $child) {
                foreach ($this->get_element_paths($child) as $element_path) {
                    $relative_path = $base_path . '/' . $element_path;
                    $map[] = [$relative_path, $relative_path];
                }
            }
        }
        return $map;
    }
    /**
     * @return string
     * @throws RuntimeException
     */
    protected function get_target_path(\Simple_Xml_Element $target)
    {
        $name = (string) $target->attributes()->name;
        $targets = $this->get_targets_definitions();
        if (!isset($targets[$name])) {
            throw new RuntimeException('Invalid target type ' . $name);
        }
        return $targets[$name];
    }
    /**
     * @return array
     */
    protected function get_targets_definitions()
    {
        if (!$this->_targets) {
            $targets = simplexml_load_file(__DIR__ . '/../../../../res/target.xml');
            foreach ($targets as $target) {
                $attributes = $target->attributes();
                $this->_targets["{$attributes->name}"] = "{$attributes->uri}";
            }
        }
        return $this->_targets;
    }
    /**
     * @throws RuntimeException
     */
    protected function get_element_paths(\Simple_Xml_Element $element): array
    {
        $type = $element->get_name();
        $name = $element->attributes()->name;
        $element_paths = [];
        switch ($type) {
            case 'dir':
                if ($element->children()) {
                    foreach ($element->children() as $child) {
                        foreach ($this->get_element_paths($child) as $element_path) {
                            $element_paths[] = $name == '.' ? $element_path : $name . '/' . $element_path;
                        }
                    }
                } else {
                    $element_paths[] = $name;
                }
                break;
            case 'file':
                $element_paths[] = $name;
                break;
            default:
                throw new RuntimeException('Unknown path type: ' . $type);
        }
        return $element_paths;
    }
    /**
     * @return SimpleXMLElement
     */
    protected function get_first_child(\Simple_Xml_Element $element)
    {
        foreach ($element->children() as $child) {
            return $child;
        }
    }
}