<?php

declare(strict_types=1);
/**
 * Composer Magento Installer
 */

namespace MagentoHackathon\Composer\Magento;

/**
 * Parsers modman files
 */
class ModmanParser extends PathTranslationParser
{
    /**
     * @var string Path to vendor module dir
     */
    protected $_moduleDir;

    /**
     * @var \SplFileObject The modman file
     */
    protected $_file;

    /**
     * Constructor
     *
     * @param string $moduleDir
     */
    public function __construct($moduleDir = null, $translations = [], $pathSuffix = '')
    {
        parent::__construct($translations, $pathSuffix);

        $this->setModuleDir($moduleDir);
        $this->setFile($this->getModmanFile());
    }

    /**
     * Sets the module directory where to search for the modman file
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
     * @return string
     */
    public function getModmanFile(): ?\SplFileObject
    {
        if ($this->_moduleDir !== null) {
            return new \SplFileObject($this->_moduleDir . '/modman');
        }
        return null;
    }

    /**
     * @return array
     * @throws \ErrorException
     */
    public function getMappings()
    {
        $file = $this->getFile();

        if (!$file->isReadable()) {
            throw new \ErrorException(sprintf('modman file "%s" not readable', $file->getPathname()));
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
