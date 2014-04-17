<?php

namespace DBPatcher;

use \Mockery as m;
use org\bovigo\vfs\vfsStream;

class PatchFileTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructsWithCorrectProperties()
    {
        $patchFile = self::setupPatchFile('testDir', 'test-patch.sql', 'DROP TABLE some');

        $this->assertSame('test-patch.sql', $patchFile->name);
        $this->assertSame(vfsStream::url('testDir/test-patch.sql'), $patchFile->filename);
        $this->assertSame(md5('DROP TABLE some'), $patchFile->md5);
        $this->assertSame('sql', $patchFile->extension);
    }

    /**
     * @param string $property
     * @dataProvider properties
     */
    public function testAllPropertiesAreReadOnly($property)
    {
        $this->setExpectedException('\ErrorException');

        self::setupPatchFile()->{$property} = 'new-value';;
    }

    public function properties()
    {
        return [
            ['name'],
            ['filename'],
            ['md5'],
            ['extension']
        ];
    }

    private static function setupPatchFile($dir = 'testDir', $name = 'test-patch.sql', $content = 'DROP TABLE some')
    {
        vfsStream::setup($dir, null, [$name => $content]);

        return PatchFile::createFromFS($name, vfsStream::url($dir));
    }

}
