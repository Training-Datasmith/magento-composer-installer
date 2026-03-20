<?php

declare(strict_types=1);

namespace MagentoHackathon\Composer\Magento\Deploystrategy;

use org\bovigo\vfs\vfsStream;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

class NoneTest extends \PHPUnit\Framework\TestCase
{
    public const URL_VFS_ROOT = 'vfsroot';

    protected function _getVfsUrl($input)
    {
        return vfsStream::url(self::URL_VFS_ROOT . DS . $input);
    }

    protected function setUp(): void
    {
        vfsStream::setup(self::URL_VFS_ROOT);
        $this->sourceDir = $this->_getVfsUrl('sourceDir');
        $this->destDir = $this->_getVfsUrl('destDir');
        $this->strategy = new None($this->sourceDir, $this->destDir);
    }

    public function testCreate()
    {
        $src = 'test1';
        $dest = 'test2';

        //create the source directory
        mkdir($this->_getVfsUrl('sourceDir' . DS . $src), 0755, true);

        $this->assertTrue(is_dir($this->_getVfsUrl('sourceDir' . DS . $src)));
        $this->assertFalse(is_dir($this->_getVfsUrl('destDir' . DS . $dest)));

        //run the none deploy strategy
        $this->strategy->create($src, $dest);

        //check that everything is still the same
        $this->assertTrue(is_dir($this->_getVfsUrl('sourceDir' . DS . $src)));
        $this->assertFalse(is_dir($this->_getVfsUrl('destDir' . DS . $dest)));
    }

}
