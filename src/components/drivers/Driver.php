<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\components\drivers;


use Converter\components\storages\FileStorage;

abstract class Driver
{
    public $presetName;
    
    /** @var FileStorage */
    protected $storage;
    protected $result = [];
    
    public function __construct($presetName, $config = [])
    {
        $this->presetName = $presetName;
        foreach ($config as $name => $value) {
            $this->$name = is_string($value) ? trim($value) : $value;
        }
        $this->storage = FileStorage::loadByPreset($presetName);
    }
    
    /**
     * @return bool
     */
    public function hasStorage()
    {
        return $this->storage instanceof FileStorage;
    }
    
    /**
     * @return FileStorage|null
     */
    public function getStorage()
    {
        return $this->storage;
    }
    
    abstract public function processPhoto($filePath, $callback, $processId = null);

    abstract public function processAudio($filePath, $callback, $processId = null);
    
    abstract public function processVideo($filePath, $callback, $processId = null);
    
    abstract public function createPhotoPreview($filePath);
    
    abstract public function createVideoPreview($filePath);
    
    abstract public function getStatus($processId);
    
    /**
     * @param $presetName
     * @param $config
     * @return Driver|null
     */
    public static function loadByConfig($presetName, $config)
    {
        $driverName = $config['driver'] ?? null;
        if ($driverName == null || !class_exists($driverName)) {
            return null;
        }
        unset($config['driver']);
        return new $driverName($presetName, $config);
    }
    
    /**
     * @return array
     */
    public function getResult()
    {
        return $this->result;
    }
}