<?php
/**
 * User: pel
 * Date: 24/10/2018
 */

namespace Converter\components;


use Converter\components\drivers\Driver;
use Converter\exceptions\BadRequestHttpException;
use Converter\exceptions\NotFoundHttpException;
use Converter\helpers\FileHelper;
use GuzzleHttp\Client;
use Psr\Log\LogLevel;

class Process
{
    /**
     * @param $callback
     * @param $filePath
     * @param $presetName
     * @param $fileType
     * @return string
     */
    public static function createQueue($callback, $filePath, $presetName, $fileType)
    {
        $processId = uniqid() . time();
        Redis::getInstance()->set('queue:' . $processId, json_encode([
            'callback' => $callback,
            'filePath' => $filePath,
            'presetName' => $presetName,
            'fileType' => $fileType
        ]));
        return $processId;
    }
    
    public static function start($processId)
    {
        $queue = Redis::getInstance()->get('queue:' . $processId);
        if ($queue) {
            $queue = json_decode($queue, true);
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
    
            switch ($queue['fileType']) {
                case FileHelper::TYPE_VIDEO:
                    $driver->processVideo($queue['filePath'], $queue['callback'], $processId);
                    break;
                case FileHelper::TYPE_IMAGE:
                    $driver->processPhoto($queue['filePath'], $queue['callback'], $processId);
                    break;
                case FileHelper::TYPE_AUDIO:
                    $driver->processAudio($queue['filePath'], $queue['callback'], $processId);
                    break;
                default:
                    return false;
            }
            $resultBody = [
                'processId' => $processId,
                'files'     => $driver->getResult()
            ];
            Logger::send('converter.callback.result', [
                'resultBody' => json_encode($resultBody)
            ]);
    
            try {
                $client = new Client();
                $response = $client->request('POST', $queue['callback'], [
                    'json' => $resultBody
                ]);
                Logger::send('converter.callback.response', [
                    'response' => $response->getBody()
                ]);
                Redis::getInstance()->del('queue:' . $processId);
            } catch (\Exception $e) {
                Logger::send('converter.callback.send', [
                    'error' => $e->getMessage()
                ], LogLevel::ERROR);
            }
            
            return true;
        }
        return false;
    }
}