<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\forms;


use Converter\components\Config;
use Converter\components\drivers\Driver;
use Converter\components\Form;

class VideoForm extends Form
{
    public $filePath;
    public $callback;
    public $preset;
    
    /**
     * @return Driver|boolean
     */
    protected function getProcessDriver()
    {
        $presets = Config::getInstance()->get('presets');
        if (empty($presets[$this->preset])) {
            $this->setErrors('Preset not found.');
            return false;
        }
        $preset = $presets[$this->preset];
        if (!class_exists($preset['driver'])) {
            $this->setErrors('Driver not found.');
            return false;
        }
    
        return new $preset['driver']($this->preset, $preset);
    }
    
    public function processLocalFile($inputFile)
    {
        $rules = [
            'required' => ['callback', 'preset'],
            'url' => ['callback'],
        ];
    
        if (!$this->validate($rules)) {
            return false;
        }
    
        $preset = $this->validatePreset();
        if ($preset === false) {
            return false;
        }
    
        $driver = $this->getProcessDriver();
        if ($driver === false) {
            return false;
        }
    
        $fileUrl = Config::getInstance()->get('baseUrl') . '/upload/' . basename($inputFile);
        $processId = $driver->processVideo($fileUrl, $this->callback);
        return $processId;
    }
    
    public function processExternalFile()
    {
        $rules = [
            'required' => ['filePath', 'callback', 'preset'],
            'url' => ['filePath', 'callback'],
        ];
    
        if (!$this->validate($rules)) {
            return false;
        }
    
        $driver = $this->getProcessDriver();
        if ($driver === false) {
            return false;
        }
        
        
        $processId = $driver->processVideo($this->filePath, $this->callback);
        return $processId;
    }
    
    /**
     * @return string
     */
    public function getLocalPath()
    {
        $localStoragePath = PUBPATH . '/upload/' . md5(time()) . rand(0, 999999);
        
        return $localStoragePath;
    }
}