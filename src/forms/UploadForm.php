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
    public $watermark = [];
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
            $this->setErrors(ucfirst($this->fileType) . '   can\'t handle. ' . $mimeType . ' not supported.');
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
    
    public function process($processId = null)
    {
        $rules = [
            'required' => ['preset', 'filePath'],
            'url'      => ['callback'],
        ];
        
        if (!$this->validate($rules)) {
            return false;
        }
        
        if (!$this->filePath) {
            $this->setErrors('File not uploaded.');
            return false;
        }
        
        $driver = $this->getProcessDriver();
        if ($driver === false) {
            return false;
        }
        
        $fileUrl = file_exists($this->filePath) ? Config::getInstance()->get('baseUrl') . '/upload/' . basename($this->filePath) : $this->filePath;
        if ($this->isDelay) {
            $files = [
                FileHelper::getFileResponse($fileUrl, $this->fileType)
            ];
            switch ($this->fileType) {
                case FileHelper::TYPE_VIDEO:
                    $driver->createVideoPreview($fileUrl, $this->watermark);
                    break;
                case FileHelper::TYPE_IMAGE:
                    $driver->createPhotoPreview($fileUrl, $this->watermark);
                    break;
            }
            
            $result = $driver->getResult();
            if ($result) {
                $files = array_merge($files, $result);
            }
            
            return Process::createQueue([
                'callback'   => $this->callback,
                'filePath'   => $fileUrl,
                'presetName' => $this->preset,
                'fileType'   => $this->fileType,
                'files'      => $files,
                'watermark'  => $this->watermark
            ]);
        } else {
            switch ($this->fileType) {
                case FileHelper::TYPE_VIDEO:
                    $processId = $driver->processVideo($fileUrl, $this->callback, $processId, $this->watermark);
                    break;
                case FileHelper::TYPE_IMAGE:
                    $processId = $driver->processPhoto($fileUrl, $this->callback, $processId, $this->watermark);
                    break;
                case FileHelper::TYPE_AUDIO:
                    $processId = $driver->processAudio($fileUrl, $this->callback, $processId, $this->watermark);
                    break;
                default:
                    $this->setErrors(ucfirst($this->fileType) . ' can\'t handle. O.o');
                    return false;
            }
            Process::createQueue([
                'callback'   => $this->callback,
                'filePath'   => $fileUrl,
                'presetName' => $this->preset,
                'fileType'   => $this->fileType,
                'files'      => [],
                'watermark'  => $this->watermark
            ], $processId);
            return $processId;
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