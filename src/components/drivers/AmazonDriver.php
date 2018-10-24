<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\components\drivers;


use Aws\ElasticTranscoder\ElasticTranscoderClient;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Converter\components\Redis;
use GuzzleHttp\Client;

class AmazonDriver implements Driver
{
    public $url;
    public $s3;
    public $transcoder;
    public $presetName;
    
    public function __construct($presetName, $config = [])
    {
        $this->presetName = $presetName;
        foreach ($config as $name => $value) {
            $this->$name = is_string($value) ? trim($value) : $value;
        }
    }
    
    /**
     * @param $filePath
     * @param $callback
     * @param null $processId
     * @return null|string
     */
    public function processVideo($filePath, $callback, $processId = null)
    {
        $processId = $processId ? $processId : uniqid() . time();
        Redis::getInstance()->sAdd('amazon:upload', json_encode([
            'presetName' => $this->presetName,
            'processId' => $processId,
            'callback' => $callback,
            'filePath' => $filePath
        ]));
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
    
    /**
     * @return ElasticTranscoderClient
     */
    public function getTranscoderClient()
    {
        return new ElasticTranscoderClient([
            'version' => 'latest',
            'region' => $this->transcoder['region'],
            'credentials' => [
                'key' => $this->transcoder['key'],
                'secret' => $this->transcoder['secret'],
            ]
        ]);
    }
    
    /**
     * @param $filePath
     * @param $callback
     * @param $processId
     * @return bool
     */
    public function createJob($filePath, $callback, $processId)
    {
        $pathParts = pathinfo($filePath);
        $keyName = '/temp_video/' . parse_url($filePath, PHP_URL_HOST) . '/' . uniqid('', true) . '.' . $pathParts['extension'];
    
        $s3Client = new S3Client([
            'version' => 'latest',
            'region'  => $this->s3['region'],
            'credentials' => [
                'key' => $this->s3['key'],
                'secret' => $this->s3['secret']
            ]
        ]);
    
        try {
            $client = new Client();
            $response = $client->get($filePath);
            $s3Client->putObject([
                'Bucket' => $this->s3['bucket'],
                'Key' => $keyName,
                'Body' => $response->getBody(),
            ]);
            $filePath = PUBPATH . '/upload/' . basename($filePath);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        } catch (S3Exception $e) {
            return false;
        }
    
        $dir = substr($processId, 0, 1) . '/' . substr($processId, 0, 2) . '/' . substr($processId, 0, 3) . '/' . $processId;
    
        $transcoderClient = $this->getTranscoderClient();
        try {
            $job = $transcoderClient->createJob([
                'PipelineId'      => $this->transcoder['pipeline'],
                'OutputKeyPrefix' => 'files/',
                'Input'           => [
                    'Key'         => $keyName,
                    'FrameRate'   => 'auto',
                    'Resolution'  => 'auto',
                    'AspectRatio' => 'auto',
                    'Interlaced'  => 'auto',
                    'Container'   => 'auto',
                ],
                'Outputs'         => [
                    [
                        'Key'      => $dir . '.mp4',
                        'Rotate'   => 'auto',
                        'PresetId' => $this->transcoder['preset'],
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return false;
        }
    
        $job = (array)$job->get('Job');
        if (strtolower($job['Status']) == 'submitted') {
            Redis::getInstance()->sAdd('amazon:queue', json_encode([
                'jobId' => $job['Id'],
                'processId' => $processId,
                'callback' => $callback,
                'presetName' => $this->presetName
            ]));
            return true;
        }
    }
}