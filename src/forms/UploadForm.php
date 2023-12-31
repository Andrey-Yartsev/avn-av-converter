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
use Converter\components\storages\FileStorage;
use Converter\components\storages\S3Storage;
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
    public $additional = [];
    public $file = [];
    protected $mimeType;
    protected $thumbs = [];
    protected $sourceUrl;
    
    /**
     * @return Driver|bool
     */
    protected function getProcessDriver()
    {
        $presets = Config::getInstance()->get('presets');
        if (empty($presets[$this->preset])) {
            $this->setErrors('Preset "' . $this->preset . '" not found.');
            return false;
        }
        $preset = $presets[$this->preset];
        if (!$this->fileType) {
            if ($this->file && is_array($this->file)) {
                $s3Storage = FileStorage::loadByPreset($this->preset);
                if ($s3Storage instanceof S3Storage) {
                    $s3Client = $s3Storage->getClient();
                    $allowedBuckets = [
                        'of2transcoder',
                        'avnstars-media',
                        'avnsocial-dev',
                    ];
                    if (!in_array($this->file['Bucket'] ?? '', $allowedBuckets)) {
                        $this->setErrors('Invalid input.');
                        return false;
                    }
                    $response = $s3Client->headObject([
                        'Bucket' => $this->file['Bucket'],
                        'Key' => $this->file['Key'],
                    ]);
                    Logger::send('debug', ['step' => 'HeadObject', 'data' => $response->toArray()]);
                    if (isset($response['ContentLength']) && $response['ContentLength'] < 1) {
                        $this->setErrors('File is empty.');
                        return false;
                    }
                    $this->file['ContentType'] = $response['ContentType'] ?? null;
                    $this->fileType = isset($response['ContentType']) ? FileHelper::getTypeByMimeType($response['ContentType']) : 'None';
                    $allowedHosts = [
                        'https://of2transcoder.s3-accelerate.amazonaws.com/upload/',
                        'https://avnstars-media.s3-accelerate.amazonaws.com/upload/',
                        'https://avnsocial-dev.s3-accelerate.amazonaws.com/upload/'
                    ];
                    $host = substr($this->file['Location'], 0, strpos($this->file['Location'], '/upload/')+8);
                    if (!in_array($host, $allowedHosts)) {
                        $this->setErrors('Invalid input.');
                        return false;
                    }
    
                    $command = $s3Client->getCommand('GetObject', [
                        'Bucket' => $this->file['Bucket'],
                        'Key'    => $this->file['Key'],
                    ]);
                    $request = $s3Client->createPresignedRequest($command, '+1 week');
                    $this->sourceUrl = (string) $request->getUri();
                }
            } else {
                $this->fileType = FileHelper::getTypeFile($this->filePath);
                if ($this->fileType == false) {
                    $this->setErrors('Error identifying file type.');
                    return false;
                }
            }
        }
        
        if (empty($preset[$this->fileType])) {
            $this->setErrors(ucfirst($this->fileType) . '   can\'t handle.');
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
            'required' => ['preset'],
            'url'      => ['callback'],
        ];
        
        if (!$this->validate($rules)) {
            return false;
        }
        
        if (!$this->filePath && empty($this->file)) {
            $this->setErrors('File not uploaded.');
            return false;
        }
        
        $driver = $this->getProcessDriver();
        if ($driver === false) {
            return false;
        }
        
        if ($this->file) {
            $fileUrl = $this->file['Location'];
        } else {
            $fileUrl = str_replace(PUBPATH, Config::getInstance()->get('baseUrl'), FileHelper::getLocalPath($this->filePath));
        }
        Logger::send('process', ['step' => 'init', 'data' => $this->getAttributes()]);
        if ($this->isDelay) {
            if ($this->needThumbs) {
                if ($this->fileType == FileHelper::TYPE_VIDEO) {
                    $this->thumbs = $driver->createThumbsFormVideo($fileUrl);
                } elseif ($this->fileType == FileHelper::TYPE_IMAGE) {
                    $this->thumbs = $driver->createPhotoThumbs($fileUrl);
                }
            }
            
            $process = [
                'callback'   => $this->callback,
                'filePath'   => $fileUrl,
                'presetName' => $this->preset,
                'fileType'   => $this->fileType,
                'watermark'  => $this->watermark,
                'file'       => $this->file
            ];
    
            $processId = Process::createQueue($process, $processId);
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
    
    public function getSourceUrl()
    {
        return $this->sourceUrl;
    }
    
    /**
     * @return string
     */
    public function getLocalPath()
    {
        return PUBPATH . '/upload/' . md5(time()) . rand(0, 999999);
    }
}