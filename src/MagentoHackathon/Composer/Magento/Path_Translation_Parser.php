<?php

declare (strict_types=1);
namespace Magento_Hackathon\Composer\Magento;

/**
 * Parser class supporting translating path mappings according to
 * the composer.json configuration.
 */
abstract class Path_Translation_Parser implements Parser
{
    /**
     * @var array Variants on each prefix that path mappings are checked
     * against.
     */
    protected $path_prefix_variants = ['', './'];
    /**
     * @var array Path mapping prefixes that need to be translated (i.e. to
     * use a public directory as the web server root).
     */
    protected $path_prefix_translations = [];
    /**
     * Constructor. Sets the list of path translations to use.
     *
     * @param array $translations Path translations
     */
    public function __construct($translations, protected $path_suffix)
    {
        $this->path_prefix_translations = $this->create_prefix_variants($translations);
    }
    /**
     * Given an array of path mapping translations, combine them with a list
     * of starting variations. This is so that a translation for 'js' will
     * also match path mappings beginning with './js'.
     *
     * @param $translations
     * @return array
     */
    protected function create_prefix_variants($translations)
    {
        $new_translations = [];
        foreach ($translations as $key => $value) {
            foreach ($this->path_prefix_variants as $variant) {
                $new_translations[$variant . $key] = $value;
            }
        }
        return $new_translations;
    }
    /**
     * Given a list of path mappings, check if any of the targets are for
     * directories that have been moved under the public directory. If so,
     * update the target paths to include 'public/'. As no standard Magento
     * path mappings should ever start with 'public/', and  path mappings
     * that already include the public directory should always have
     * js/skin/media paths starting with 'public/', it should be safe to call
     * multiple times on either.
     *
     * @param $mappings Array of path mappings
     * @return array Updated path mappings
     */
    public function translate_path_mappings($mappings)
    {
        // each element of $mappings is an array with two elements; first is
        // the source and second is the target
        foreach ($mappings as &$mapping) {
            foreach ($this->path_prefix_translations as $prefix => $translate) {
                if (str_starts_with((string) $mapping[1], (string) $prefix)) {
                    // replace the old prefix with the translated version
                    $mapping[1] = $translate . substr((string) $mapping[1], strlen((string) $prefix));
                    // should never need to translate a prefix more than once
                    // per path mapping
                    break;
                }
            }
            //Adding path Suffix to the mapping info.
            $mapping[1] = $this->path_suffix . $mapping[1];
        }
        return $mappings;
    }
}