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

class CloudConvertDriver extends Driver
{
    /** @var Api */
    protected $client;
    public $token;
    public $outputFormat;
    public $command;
    
    public function __construct($presetName, $config = [])
    {
        Logger::send('CC.init');
        parent::__construct($presetName, $config = []);
        $this->client = new Api($this->token);
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
        $process->start([
            'outputformat' => $this->outputFormat,
            'converteroptions' => [
                'command' => $this->command,
            ],
            'input' => 'download',
            'file' => $filePath,
            'callback' => Config::getInstance()->get('baseUrl') . '/video/cloudconvert/callback?processId=' . $processId
        ]);
        $processId = $processId ? $processId : $process->id;
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
    
    public function processAudio($filePath, $callback, $processId = null)
    {
        throw new \Exception('Not implemented');
    }
    
    public function processPhoto($filePath, $callback, $processId = null)
    {
        throw new \Exception('Not implemented');
    }
}