<?php
/**
 * User: pel
 * Date: 25/10/2018
 */

namespace Converter\forms;


use Converter\components\Config;
use Converter\components\drivers\Driver;
use Converter\components\Form;
use Converter\components\Logger;
use Converter\components\Process;
use Converter\helpers\FileHelper;

class UploadForm extends Form
{
    public $filePath;
    public $callback;
    public $preset;
    public $isDelay = false;
    public $needThumbs = false;
    public $fileType;
    public $watermark = [];
    protected $mimeType;
    protected $thumbs = [];
    
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
        $this->filePath = rawurldecode($this->filePath);
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
        Logger::send('process', ['step' => 'init', 'data' => $this->getAttributes()]);
        if ($this->isDelay) {
            if ($this->needThumbs && $this->fileType == FileHelper::TYPE_VIDEO) {
                $this->thumbs = $driver->createThumbsFormVideo($fileUrl);
            }
            
            $process = [
                'callback'   => $this->callback,
                'filePath'   => $fileUrl,
                'presetName' => $this->preset,
                'fileType'   => $this->fileType,
                'watermark'  => $this->watermark
            ];
    
            $processId = Process::createQueue($process);
            $process['isDelay'] = true;
            Logger::send('process', ['processId' => $processId, 'step' => 'create', 'data' => $process]);
            
            return $processId;
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
    
            $process = [
                'callback'   => $this->callback,
                'filePath'   => $fileUrl,
                'presetName' => $this->preset,
                'fileType'   => $this->fileType,
                'watermark'  => $this->watermark
            ];
            
            Process::createQueue($process, $processId);
    
            $process['isDelay'] = false;
            Logger::send('process', ['processId' => $processId, 'step' => 'create', 'data' => $process]);
            
            return $processId;
        }
    }
    
    public function getThumbs()
    {
        return $this->thumbs;
    }
    
    /**
     * @return string
     */
    public function getLocalPath()
    {
        return PUBPATH . '/upload/' . md5(time()) . rand(0, 999999);
    }
}