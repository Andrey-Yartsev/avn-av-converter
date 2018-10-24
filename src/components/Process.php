<?php
/**
 * User: pel
 * Date: 24/10/2018
 */

namespace Converter\components;


use Converter\components\drivers\Driver;
use Converter\exceptions\BadRequestHttpException;
use Converter\exceptions\NotFoundHttpException;

class Process
{
    /**
     * @param $callback
     * @param $filePath
     * @param $presetName
     * @return string
     */
    public static function createQueue($callback, $filePath, $presetName)
    {
        $processId = uniqid() . time();
        Redis::getInstance()->set('queue:' . $processId, json_encode([
            'callback' => $callback,
            'filePath' => $filePath,
            'presetName' => $presetName,
        ]));
        return $processId;
    }
    
    public static function start($processId)
    {
        $queue = Redis::getInstance()->get('queue:' . $processId);
        if ($queue) {
            $queue = json_decode($queue, true);
            $presets = Config::getInstastatnce()->get('presets');
            if (empty($presets[$queue['presetName']])) {
                throw new BadRequestHttpException('Invalid preset.');
            }
            $preset = $presets[$queue['presetName']];
            if (!class_exists($preset['driver'])) {
                throw new BadRequestHttpException('Driver not found.');
            }
            /** @var Driver $driver */
            $driver = new $preset['driver']($queue['presetName'], $preset);
            $driver->processVideo($queue['filePath'], $queue['callback'], $processId);
            Redis::getInstance()->del('queue:' . $processId);
            return true;
        } else {
            throw new NotFoundHttpException('Process not found');
        }
    }
}