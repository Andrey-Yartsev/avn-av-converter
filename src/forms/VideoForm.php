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
    
    public function process()
    {
        $rules = [
            'required' => ['filePath', 'callback', 'preset'],
            'url' => ['filePath', 'callback'],
        ];
    
        if (!$this->validate($rules)) {
            return false;
        }
        
        $presets = Config::getInstance()->get('presets');
        if (empty($presets[$this->preset])) {
            $this->setErrors('Preset not found.');
            return false;
        }
        $preset = $presets[$this->preset];
        if (class_exists($preset['driver'])) {
            $this->setErrors('Driver not found.');
            return false;
        }
        /** @var Driver $driver */
        $driver = new $preset['driver']($preset);
        $processId = $driver->processVideo($this->filePath, $this->callback);
        return $processId;
    }
}