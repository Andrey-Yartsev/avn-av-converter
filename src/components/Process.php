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
     * @return bool
     */
    public static function start($processId)
    {
        $queue = Redis::getInstance()->get('queue:' . $processId);
        if ($queue) {
            $queue = json_decode($queue, true);
            $driver = self::getDriver($queue);
            if (!$driver) {
                return false;
            }
            $watermark = $queue['watermark'] ?? [];
    
            switch ($queue['fileType']) {
                case FileHelper::TYPE_VIDEO:
                    $driver->processVideo($queue['filePath'], $queue['callback'], $processId, $watermark);
                    break;
                case FileHelper::TYPE_IMAGE:
                    $driver->processPhoto($queue['filePath'], $queue['callback'], $processId, $watermark);
                    break;
                case FileHelper::TYPE_AUDIO:
                    $driver->processAudio($queue['filePath'], $queue['callback'], $processId, $watermark);
                    break;
                default:
                    return false;
            }
            $hasResult = $driver->getResult();
            if ($hasResult) {
                $resultBody = [
                    'processId' => $processId,
                    'files'     => $driver->getResult()
                ];
                Logger::send('converter.callback.result', [
                    'id' => $processId,
                    'resultBody' => json_encode($resultBody)
                ]);
    
                try {
                    $client = new Client();
                    $response = $client->request('POST', $queue['callback'], [
                        'json' => $resultBody
                    ]);
                    Logger::send('converter.callback.response', [
                        'id' => $processId,
                        'response' => $response->getBody()
                    ]);
                    Redis::getInstance()->del('queue:' . $processId);
                } catch (\Exception $e) {
                    Logger::send('converter.callback.send', [
                        'id' => $processId,
                        'error' => $e->getMessage()
                    ], LogLevel::ERROR);
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