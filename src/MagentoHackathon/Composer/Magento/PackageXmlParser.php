<?php

declare(strict_types=1);
/**
 * Composer Magento Installer
 */

namespace MagentoHackathon\Composer\Magento;

/**
 * Parses Magento Connect 2.0 package.xml files
 */
class PackageXmlParser extends PathTranslationParser
{
    /**
     * @var string Path to vendor module dir
     */
    protected $_moduleDir;

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
    public function __construct($moduleDir, $packageXmlFile, $translations = [], $pathSuffix = '')
    {
        parent::__construct($translations, $pathSuffix);
        $this->setModuleDir($moduleDir);
        $this->setFile($this->getModuleDir() . '/' . $packageXmlFile);
    }

    /**
     * Sets the module directory where to search for the package.xml file
     *
     * @param string $moduleDir
     */
    public function setModuleDir($moduleDir): static
    {
        // Remove trailing slash
        if ($moduleDir !== null) {
            $moduleDir = rtrim($moduleDir, '\\/');
        }

        $this->_moduleDir = $moduleDir;
        return $this;
    }

    /**
     * @return string
     */
    public function getModuleDir()
    {
        return $this->_moduleDir;
    }

    /**
     * @param string|SplFileObject $file
     */
    public function setFile($file): static
    {
        if (is_string($file)) {
            $file = new \SplFileObject($file);
        }
        $this->_file = $file;
        return $this;
    }

    /**
     * @return \SplFileObject
     */
    public function getFile()
    {
        return $this->_file;
    }

    /**
     * @return array
     * @throws \ErrorException
     */
    public function getMappings()
    {
        $file = $this->getFile();

        if (!$file->isReadable()) {
            throw new \ErrorException(sprintf('Package file "%s" not readable', $file->getPathname()));
        }

        $map = $this->_parseMappings();
        return $this->translatePathMappings($map);
    }

    /**
     * @throws \ErrorException
     */
    protected function _parseMappings(): array
    {
        $map = [];

        /** @var $package SimpleXMLElement */
        $package = simplexml_load_file($this->getFile()->getPathname());
        foreach ($package->xpath('//contents/target') as $target) {
            $basePath = $this->getTargetPath($target);
            foreach ($target->children() as $child) {
                foreach ($this->getElementPaths($child) as $elementPath) {
                    $relativePath = $basePath . '/' . $elementPath;
                    $map[] = [$relativePath, $relativePath];
                }
            }
        }
        return $map;
    }

    /**
     * @return string
     * @throws RuntimeException
     */
    protected function getTargetPath(\SimpleXMLElement $target)
    {
        $name = (string) $target->attributes()->name;
        $targets = $this->getTargetsDefinitions();
        if (! isset($targets[$name])) {
            throw new RuntimeException('Invalid target type ' . $name);
        }
        return $targets[$name];
    }

    /**
     * @return array
     */
    protected function getTargetsDefinitions()
    {
        if (! $this->_targets) {

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
    protected function getElementPaths(\SimpleXMLElement $element): array
    {
        $type = $element->getName();
        $name = $element->attributes()->name;
        $elementPaths = [];

        switch ($type) {
            case 'dir':
                if ($element->children()) {
                    foreach ($element->children() as $child) {
                        foreach ($this->getElementPaths($child) as $elementPath) {
                            $elementPaths[] = $name == '.' ? $elementPath : $name . '/' . $elementPath;
                        }
                    }
                } else {
                    $elementPaths[] = $name;
                }
                break;

            case 'file':
                $elementPaths[] = $name;
                break;

            default:
                throw new RuntimeException('Unknown path type: ' . $type);
        }

        return $elementPaths;
    }

    /**
     * @return SimpleXMLElement
     */
    protected function getFirstChild(\SimpleXMLElement$element)
    {
        foreach ($element->children() as $child) {
            return $child;
        }
    }
}
