<?php

declare (strict_types=1);
/**
 * Composer Magento Installer
 */
namespace Magento_Hackathon\Composer\Magento;

class Map_Parser extends Path_Translation_Parser
{
    protected $_mappings = [];
    public function __construct($mappings, $translations = [], $path_suffix = '')
    {
        parent::__construct($translations, $path_suffix);
        $this->set_mappings($mappings);
    }
    public function set_mappings($mappings): void
    {
        $this->_mappings = $this->translate_path_mappings($mappings);
    }
    public function get_mappings()
    {
        return $this->_mappings;
    }
}