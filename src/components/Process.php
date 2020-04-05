<?php
/**
 * User: pel
 * Date: 24/10/2018
 */

namespace Converter\components;


use Converter\components\drivers\Driver;
use Converter\helpers\FileHelper;
use Converter\response\StatusResponse;
use GuzzleHttp\Client;

class Process
{
    protected $id;
    protected $data = [];
    protected $presetSettings = [];
    
    public function __construct($id, $data)
    {
        $this->data = $data;
        $this->id = $id;
        $presets = Config::getInstance()->get('presets');
        if (empty($presets[$data['presetName']])) {
            $this->presetSettings = false;
        }
        $preset = $presets[$data['presetName']];
        if (empty($preset[$data['fileType']])) {
            $this->presetSettings = false;
        }
        $this->presetSettings = $preset;
    }
    
    /**
     * @param $processId
     * @return bool|Process
     */
    public static function find($processId)
    {
        $queue = Redis::getInstance()->get('queue:' . $processId);
        if ($queue) {
            return new self($processId, json_decode($queue, true));
        }
        return false;
    }
    
    /**
     * @param array $params
     * @param null $processId
     * @return string|null
     */
    public static function createQueue($params = [], $processId = null)
    {
        $processId = $processId ? $processId : uniqid() .  substr(md5(time()), 8, 8);
        Redis::getInstance()->set('queue:' . $processId, json_encode($params));
        return $processId;
    }
    
    /**
     * @param $processId
     * @return bool|StatusResponse
     */
    public static function status($processId)
    {
        $process = self::find($processId);
        if ($process) {
            $driver = $process->getDriver();
            if ($driver) {
                return $driver->getStatus($processId);
            }
        }
    }
    
    /**
     * @param $processId
     * @param $presetName
     * @return bool
     */
    public static function restart($processId, $presetName)
    {
        $process = self::find($processId);
        if ($process) {
            $queue = $process->getData();
            $process->log("Change preset {$queue['presetName']} => {$presetName}");
            $queue['presetName'] = $presetName;
            Redis::getInstance()->set('queue:' . $processId, json_encode($queue));
            return self::start($processId);
        }
        return false;
    }
    
    public function delete()
    {
        Redis::getInstance()->del('queue:' . $this->getId());
    }
    
    /**
     * @param $processId
     * @return bool
     */
    public static function start($processId)
    {
        $process = self::find($processId);
        if ($process) {
            $process->log('Start convert');
            $queue = $process->getData();
            $driver = $process->getDriver();
            if (!$driver) {
                return false;
            }
            $watermark = $queue['watermark'] ?? [];
    
            try {
                switch ($queue['fileType']) {
                    case FileHelper::TYPE_VIDEO:
                        $process->log('Start process video');
                        $driver->processVideo($queue['filePath'], $queue['callback'], $processId, $watermark);
                        break;
                    case FileHelper::TYPE_IMAGE:
                        $process->log('Start process image');
                        $driver->processPhoto($queue['filePath'], $queue['callback'], $processId, $watermark);
                        break;
                    case FileHelper::TYPE_AUDIO:
                        $process->log('Start process audio');
                        $driver->processAudio($queue['filePath'], $queue['callback'], $processId, $watermark);
                        break;
                    default:
                        return false;
                }
            } catch (\Throwable $e) {
                $process->log('Error on convert', ['error' => $e->getMessage()]);
                $process->sendCallback([
                    'error' => $e->getMessage(),
                ]);
            }
            
            $hasResult = $driver->getResult();
            $process->log('Convert done', ['result' => $hasResult]);
            if ($hasResult) {
                $process->sendCallback([
                    'files' => $driver->getResult()
                ]);
            }
            
            return true;
        } else {
            $process->log('Process not found');
        }
        return false;
    }
    
    /**
     * @param $processId
     * @return bool
     */
    public static function exists($processId)
    {
        return Redis::getInstance()->exists('queue:' . $processId);
    }
    
    public function sendCallback($data)
    {
        $data['processId'] = $this->getId();
        $data['baseUrl'] = Config::getInstance()->get('baseUrl');
        $data['preset'] = $this->getPresetName();
    
        try {
            $client = new Client();
            $response = $client->request('POST', $this->getCallbackUrl(), [
                'json' => $data
            ]);
            $this->log('Send to callback, code:' . $response->getStatusCode());
            $this->delete();
            return true;
        } catch (\Throwable $e) {
            $this->log('Error send callback', [
                'error' => $e->getMessage()
            ]);
            Redis::getInstance()->set('retry:' . $this->getId(), json_encode([
                'url'       => $this->getCallbackUrl(),
                'processId' => $this->getId(),
                'body'      => $data
            ]));
            Redis::getInstance()->incr('retry:' . $this->getId() . ':count');
            return false;
        }
    }
    
    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * @return string
     */
    public function getFilePath()
    {
        return $this->data['filePath'] ?? '';
    }
    
    /**
     * @return array
     */
    public function getFile()
    {
        return $this->data['file'] ?? [];
    }
    
    /**
     * @return string
     */
    public function getCallbackUrl()
    {
        return $this->data['callback'] ?? '';
    }
    
    /**
     * @return string
     */
    public function getFileType()
    {
        return $this->data['fileType'] ?? '';
    }
    
    /**
     * @return array
     */
    public function getWatermark()
    {
        return $this->data['watermark'] ?? [];
    }
    
    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
    
    /**
     * @return bool|Driver
     */
    public function getDriver()
    {
        $preset = $this->getPreset();
        $fileType = $this->getFileType();
        if (empty($preset[$fileType])) {
            return false;
        }
        
        $driver = Driver::loadByConfig($this->getPresetName(), $preset[$fileType]);
        if ($driver === null) {
            return false;
        }
        return $driver;
    }
    
    /**
     * @return string
     */
    public function getPresetName()
    {
        return $this->data['presetName'] ?? '';
    }
    
    /**
     * @return bool|array
     */
    public function getPreset()
    {
        return $this->presetSettings;
    }
    
    /**
     * @param $step
     * @param array $data
     */
    public function log($step, $data = [])
    {
        Logger::send('process', ['processId' => $this->getId(), 'step' => $step, 'data' => $data]);
    }
}