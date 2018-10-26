<?php
/**
 * User: pel
 * Date: 08/10/2018
 */

namespace Converter\components\storages;


use Converter\components\Config;

abstract class FileStorage
{
    
    public function __construct($config = [])
    {
        foreach ($config as $name => $value) {
            $this->$name = is_string($value) ? trim($value) : $value;
        }
    }
    
    abstract public function hasError();
    
    abstract public function getError();
    
    abstract public function upload($sourcePath, $savedPath);
    
    abstract public function delete($hash);
    
    abstract public function generatePath($fileName);
    
    /**
     * @param $presetName
     * @return null|FileStorage
     */
    public static function loadByPreset($presetName)
    {
        $presents = Config::getInstance()->get('presets');
        if (empty($presents[$presetName])) {
            return null;
        }
        $preset = $presents[$presetName];
        if (!empty($preset['storage']) && class_exists($preset['storage'])) {
            $storageName = $preset['storage']['driver'];
            unset($preset['storage']['driver']);
            return new $storageName($preset['storage']);
        }
        return null;
    }
}