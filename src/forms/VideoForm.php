<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\forms;


use Converter\components\Config;
use Converter\components\drivers\Driver;
use Converter\components\Form;
use Converter\components\Process;

class VideoForm extends Form
{
    public $filePath;
    public $callback;
    public $preset;
    public $isDelay = false;
    
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
    
        $driver = $this->getProcessDriver();
        if ($driver === false) {
            return false;
        }
    
        $fileUrl = Config::getInstance()->get('baseUrl') . '/upload/' . basename($inputFile);
        return $this->isDelay ? Process::createQueue($this->callback, $fileUrl, $this->preset) : $driver->processVideo($fileUrl, $this->callback);
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
    
        return $this->isDelay ? Process::createQueue($this->callback, $this->filePath, $this->preset) : $driver->processVideo($this->filePath, $this->callback);
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