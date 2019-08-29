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
use Psr\Log\LogLevel;

class Process
{
    /**
     * @param array $params
     * @return string
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
        $queue = Redis::getInstance()->get('queue:' . $processId);
        if ($queue) {
            $queue = json_decode($queue, true);
            $driver = self::getDriver($queue);
            if (!$driver) {
                return false;
            }
            return $driver->getStatus($processId);
        }
        return new StatusResponse([
            'id' => $processId
        ]);
    }
    
    /**
     * @param $processId
     * @param $presetName
     * @return bool
     */
    public static function restart($processId, $presetName)
    {
        $queue = Redis::getInstance()->get('queue:' . $processId);
        if ($queue) {
            $queue = json_decode($queue, true);
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
        $queue = Redis::getInstance()->get('queue:' . $processId);
        if ($queue) {
            Logger::send('process', ['processId' => $processId, 'step' => 'Start convert']);
            $queue = json_decode($queue, true);
            $driver = self::getDriver($queue);
            Logger::send('process', ['processId' => $processId, 'step' => 'debug', 'queue' => $queue]);
            if (!$driver) {
                return false;
            }
            $watermark = $queue['watermark'] ?? [];
    
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
     * @param $queue
     * @return bool|Driver
     */
    public static function getDriver($queue)
    {
        $presets = Config::getInstance()->get('presets');
        if (empty($presets[$queue['presetName']])) {
            return false;
        }
        $preset = $presets[$queue['presetName']];
        if (empty($preset[$queue['fileType']])) {
            return false;
        }
    
        $driver = Driver::loadByConfig($queue['presetName'], $preset[$queue['fileType']]);
        if ($driver === null) {
            return false;
        }
        return $driver;
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