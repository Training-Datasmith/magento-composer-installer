<?php

declare(strict_types=1);
/**
 * Composer Magento Installer
 */

namespace MagentoHackathon\Composer\Magento;

class MapParser extends PathTranslationParser
{
    protected $_mappings = [];

    public function __construct($mappings, $translations = [], $pathSuffix = '')
    {
        parent::__construct($translations, $pathSuffix);

        $this->setMappings($mappings);
    }

    public function setMappings($mappings): void
    {
        $this->_mappings = $this->translatePathMappings($mappings);
    }

    public function getMappings()
    {
        return $this->_mappings;
    }

}
