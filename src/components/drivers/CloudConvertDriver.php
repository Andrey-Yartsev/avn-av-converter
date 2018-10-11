<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\components\drivers;


use CloudConvert\Api;
use Converter\components\Config;
use Converter\components\Logger;
use Converter\components\Redis;

class CloudConvertDriver implements Driver
{
    /** @var Api */
    protected $client;
    public $token;
    public $outputFormat;
    public $command;
    public $presetName;
    
    public function __construct($presetName, $config = [])
    {
        Logger::send('CC.init');
        $this->presetName = $presetName;
        foreach ($config as $name => $value) {
            $this->$name = is_string($value) ? trim($value) : $value;
        }
        $this->client = new Api($this->token);
    }
    
    /**
     * @param $filePath
     * @param $callback
     * @return string
     */
    public function addDelayQueue($filePath, $callback)
    {
        $processId = uniqid() . time();
        Redis::getInstance()->set('queue:' . $processId, json_encode([
            'callback' => $callback,
            'filePath' => $filePath,
            'presetName' => $this->presetName,
        ]));
        return $processId;
    }
    
    /**
     * @param $filePath
     * @param $callback
     * @param null $processId
     * @return null|object
     * @throws \CloudConvert\Exceptions\ApiException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function processVideo($filePath, $callback, $processId = null)
    {
        $pathParts = pathinfo($filePath);
        $process = $this->client->createProcess( [
            'inputformat' => $pathParts['extension'],
            'outputformat' => $this->outputFormat,
        ]);
        $processId = $processId ? $processId : $process->id;
        $process->start([
            'outputformat' => $this->outputFormat,
            'converteroptions' => [
                'command' => $this->command,
            ],
            'input' => 'download',
            'file' => $filePath,
            'callback' => Config::getInstance()->get('baseUrl') . '/video/cloudconvert/callback'
        ]);
        Redis::getInstance()->set('cc:' . $processId, json_encode([
            'callback' => $callback,
            'presetName' => $this->presetName,
        ]));
        Logger::send('CC.sendToProvider', [
            'callback' => $callback,
            'presetName' => $this->presetName,
            'processId' => $processId
        ]);
        return $processId;
    }
}