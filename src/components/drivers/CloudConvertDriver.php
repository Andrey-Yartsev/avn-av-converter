<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\components\drivers;


use CloudConvert\Api;
use Converter\components\Config;
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
        $this->presetName = $presetName;
        foreach ($config as $name => $value) {
            $this->$name = is_string($value) ? trim($value) : $value;
        }
        $this->client = new Api($this->token);
    }
    
    /**
     * @param $filePath
     * @param $callback
     * @return null|object
     * @throws \CloudConvert\Exceptions\ApiException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function processVideo($filePath, $callback)
    {
        $pathParts = pathinfo($filePath);
        $process = $this->client->createProcess([
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
            'callback' => Config::getInstance()->get('baseUrl') . '/video/cloudconvert/callback'
        ]);
        Redis::getInstance()->set('cc:' . $process->id, json_encode([
            'callback' => $callback,
            'presetName' => $this->presetName,
        ]));
        return $process->id;
    }
}