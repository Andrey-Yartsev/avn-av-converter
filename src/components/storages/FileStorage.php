<?php
/**
 * User: pel
 * Date: 08/10/2018
 */

namespace Converter\components\storages;


use Converter\components\Config;

abstract class FileStorage
{
    protected $error;
    
    public function __construct($config = [])
    {
        foreach ($config as $name => $value) {
            $this->$name = is_string($value) ? trim($value) : $value;
        }
    }
    
    public function hasError()
    {
        return (bool) $this->error;
    }
    
    public function getError()
    {
        return $this->error;
    }
    
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
        if (!empty($preset['storage']['driver']) && class_exists($preset['storage']['driver'])) {
            $storageName = $preset['storage']['driver'];
            unset($preset['storage']['driver']);
            return new $storageName($preset['storage']);
        }
        return null;
    }
}