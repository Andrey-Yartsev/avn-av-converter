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
            $queue['presetName'] = $presetName;
            Redis::getInstance()->set('queue:' . $processId, json_encode($queue));
            return self::start($processId);
        }
        return false;
    }
    
    /**
     * @param $processId
     * @return bool
     */
    public static function start($processId)
    {
        $process = self::find($processId);
        if ($process) {
            Logger::send('process', ['processId' => $processId, 'step' => 'Start convert']);
            $queue = $process->getData();
            $driver = $process->getDriver();
            Logger::send('process', ['processId' => $processId, 'step' => 'debug', 'queue' => $queue]);
            if (!$driver) {
                return false;
            }
            $watermark = $queue['watermark'] ?? [];
    
            try {
                switch ($queue['fileType']) {
                    case FileHelper::TYPE_VIDEO:
                        Logger::send('process', ['processId' => $processId, 'step' => 'Start process video']);
                        $driver->processVideo($queue['filePath'], $queue['callback'], $processId, $watermark);
                        break;
                    case FileHelper::TYPE_IMAGE:
                        Logger::send('process', ['processId' => $processId, 'step' => 'Start process image']);
                        $driver->processPhoto($queue['filePath'], $queue['callback'], $processId, $watermark);
                        break;
                    case FileHelper::TYPE_AUDIO:
                        Logger::send('process', ['processId' => $processId, 'step' => 'Start process audio']);
                        $driver->processAudio($queue['filePath'], $queue['callback'], $processId, $watermark);
                        break;
                    default:
                        return false;
                }
            } catch (\Throwable $e) {
                Logger::send('process', ['processId' => $processId, 'step' => 'error', 'data' => $e->getMessage()]);
                $client = new Client();
                $response = $client->request('POST', $queue['callback'], [
                    'json' => [
                        'processId' => $processId,
                        'baseUrl'   => Config::getInstance()->get('baseUrl'),
                        'error'     => $e->getMessage(),
                        'preset'    => $queue['presetName'] ?? ''
                    ]
                ]);
                Logger::send('converter.callback.response', [
                    'processId' => $processId,
                    'response' => $response->getBody()
                ]);
                Logger::send('process', ['processId' => $processId, 'step' => 'Send to callback']);
                Redis::getInstance()->del('queue:' . $processId);
            }
            
            $hasResult = $driver->getResult();
            Logger::send('process', ['processId' => $processId, 'step' => 'convert done', 'result' => $hasResult]);
            if ($hasResult) {
                $resultBody = [
                    'processId' => $processId,
                    'files'     => $driver->getResult()
                ];
                Logger::send('converter.callback.result', [
                    'processId' => $processId,
                    'resultBody' => json_encode($resultBody)
                ]);
    
                try {
                    $client = new Client();
                    $response = $client->request('POST', $queue['callback'], [
                        'json' => $resultBody
                    ]);
                    Logger::send('converter.callback.response', [
                        'processId' => $processId,
                        'response' => $response->getBody()
                    ]);
                    Logger::send('process', ['processId' => $processId, 'step' => 'Send to callback']);
                    Redis::getInstance()->del('queue:' . $processId);
                } catch (\Exception $e) {
                    $params = [
                        'url'       => $queue['callback'],
                        'processId' => $processId,
                        'body'      => $resultBody
                    ];
                    Logger::send('process', ['processId' => $processId, 'step' => 'Error send callback', 'data' => [
                        'error' => $e->getMessage()
                    ]]);
                    Redis::getInstance()->set('retry:' . $processId, json_encode($params));
                    Redis::getInstance()->incr('retry:' . $processId . ':count');
                }
            }
            
            return true;
        }
        return false;
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
     * @param $processId
     * @return bool
     */
    public static function exists($processId)
    {
        return Redis::getInstance()->exists('queue:' . $processId);
    }
}