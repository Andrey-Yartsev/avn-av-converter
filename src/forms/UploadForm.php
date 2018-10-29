<?php
/**
 * User: pel
 * Date: 25/10/2018
 */

namespace Converter\forms;


use Converter\components\Config;
use Converter\components\drivers\Driver;
use Converter\components\Form;
use Converter\components\Process;
use Converter\helpers\FileHelper;

class UploadForm extends Form
{
    public $filePath;
    public $callback;
    public $preset;
    public $isDelay = false;
    public $fileType;
    protected $mimeType;
    
    /**
     * @return Driver|bool
     */
    protected function getProcessDriver()
    {
        $presets = Config::getInstance()->get('presets');
        if (empty($presets[$this->preset])) {
            $this->setErrors('Preset not found.');
            return false;
        }
        $preset = $presets[$this->preset];
    
        if (!$this->fileType) {
            $mimeType = mime_content_type($this->filePath);
            if ($mimeType == false) {
                $this->setErrors('Error identifying file type.');
                return false;
            }
            $this->fileType = FileHelper::getTypeFile($mimeType);
        }
        
        if (empty($preset[$this->fileType])) {
            $this->setErrors(ucfirst($this->fileType) . ' can\'t handle.');
            return false;
        }
        
        if (empty($this->callback)) {
            $this->callback = $preset['callback'] ?? null;
        }
        
        $driver = Driver::loadByConfig($this->preset, $preset[$this->fileType]);
        if ($driver === null) {
            $this->setErrors('Driver not founded.');
            return false;
        }
        
        return $driver;
    }

    public function process()
    {
        $rules = [
            'required' => ['preset', 'filePath'],
            'url' => ['callback'],
        ];
        
        if (!$this->validate($rules)) {
            return false;
        }
        
        if (!file_exists($this->filePath)) {
            $this->setErrors('File not uploaded.');
            return false;
        }
        
        $driver = $this->getProcessDriver();
        if ($driver === false) {
            return false;
        }
        
        $fileUrl = Config::getInstance()->get('baseUrl') . '/upload/' . basename($this->filePath);
        if ($this->isDelay) {
            return Process::createQueue($this->callback, $fileUrl, $this->preset, $this->fileType);
        } else {
            switch ($this->fileType) {
                case FileHelper::TYPE_VIDEO:
                    $driver->processVideo($fileUrl, $this->callback);
                    break;
                case FileHelper::TYPE_IMAGE:
                    $driver->processPhoto($fileUrl, $this->callback);
                    break;
                case FileHelper::TYPE_AUDIO:
                    $driver->processAudio($fileUrl, $this->callback);
                    break;
                default:
                    $this->setErrors(ucfirst($this->fileType) . ' can\'t handle. O.o');
                    return false;

            }
            return $driver->getResult();
        }
    }
    
    /**
     * @return string
     */
    public function getLocalPath()
    {
        return PUBPATH . '/upload/' . md5(time()) . rand(0, 999999);
    }
}