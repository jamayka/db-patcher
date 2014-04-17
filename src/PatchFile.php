<?php

namespace DBPatcher;

/**
 * Class PatchFile
 * @property-read string $filename
 * @property-read string $name
 * @property-read string $md5
 * @property-read string $status
 * @property-read string $extension
 * @package DBPatcher
 */
class PatchFile
{

    const STATUS_NEW = 1;
    const STATUS_INSTALLED = 2;
    const STATUS_ERROR = 3;
    const STATUS_CHANGED = 4;

    /**
     * @var string
     */
    private $_filename;

    /**
     * @var string
     */
    private $_name;

    /**
     * @var string
     */
    private $_md5;

    /**
     * @var string
     */
    private $_extension;

    /**
     * @var integer
     */
    private $_status;

    private function __construct()
    {
        $this->_status = self::STATUS_NEW;
    }

    /**
     * @param string $name
     * @param string $basedir
     * @return PatchFile|null
     */
    public static function createFromFS($name, $basedir)
    {
        $filename = rtrim($basedir, '/') . '/' . ltrim($name);

        if (file_exists($filename) && is_readable($filename)) {
            $patchFile = new self();
            $patchFile->_filename = $filename;
            $patchFile->_name = $name;
            $patchFile->_md5 = md5_file($filename);

            $fileInfo = new \SplFileInfo($filename);
            $patchFile->_extension = $fileInfo->getExtension();

            return $patchFile;
        }

        return null;
    }

    /**
     * @param self $patchFile
     * @param integer $status
     * @return self
     */
    public static function copyWithNewStatus($patchFile, $status)
    {
        $copy = clone $patchFile;

        $statuses = array(self::STATUS_NEW, self::STATUS_INSTALLED, self::STATUS_ERROR, self::STATUS_CHANGED);
        if (in_array($status, $statuses)) {
            $copy->_status = intval($status);
        }

        return $copy;
    }

    /**
     * For use in unit tests only
     * @param string $name
     * @param string $filename
     * @param string $md5
     * @param string $extension
     * @param integer $status
     * @return self
     */
    public static function _createForTest($name, $filename, $md5, $extension, $status = PatchFile::STATUS_NEW)
    {
        $patchFile = new self();
        $patchFile->_name = $name;
        $patchFile->_filename = $filename;
        $patchFile->_md5 = $md5;
        $patchFile->_extension = $extension;
        $patchFile->_status = $status;

        return $patchFile;
    }

    /**
     * @param string $name
     * @return null|string
     */
    public function __get($name)
    {
        switch ($name) {
            case 'filename':
            case 'name':
            case 'md5':
            case 'status':
            case 'extension':
                return $this->{'_' . $name};
        }

        return null;
    }

    public function __set($name, $value)
    {
        throw new \ErrorException('Readonly!');
    }

}
