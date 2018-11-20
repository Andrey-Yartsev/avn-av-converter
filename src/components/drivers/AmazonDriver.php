<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\components\drivers;


use Aws\ElasticTranscoder\ElasticTranscoderClient;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Converter\components\Logger;
use Converter\components\Process;
use Converter\components\Redis;
use Converter\response\VideoResponse;
use GuzzleHttp\Client;

class AmazonDriver extends Driver
{
    public $url;
    public $s3;
    public $transcoder;
    
    /**
     * @param $filePath
     * @param $callback
     * @param null $processId
     * @param array $watermark
     * @return null|string
     */
    public function processVideo($filePath, $callback, $processId = null, $watermark = [])
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
    
    public function processAudio($filePath, $callback, $processId = null, $watermark = [])
    {
        throw new \Exception('Not implemented');
    }
    
    public function processPhoto($filePath, $callback, $processId = null, $watermark = [])
    {
        throw new \Exception('Not implemented');
    }
    
    public function getStatus($processId)
    {
        throw new \Exception('Not implemented');
    }
    
    public function createPhotoPreview($filePath)
    {
        return;
    }
    
    public function createVideoPreview($filePath)
    {
        return;
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
    
    public function readJob($jobId)
    {
        $transcoderClient = $this->getTranscoderClient();
        $response = $transcoderClient->readJob(['Id' => $jobId]);
        $jobData = (array) $response->get('Job');
        if (strtolower($jobData['Status']) != 'complete') {
            if (strtolower($jobData['Status']) == 'error') {
                Logger::send('converter.aws.readJob', $jobData['Output']);
            }
            return false;
        }
    
        $output = $jobData['Output'];
        $this->result[] = new VideoResponse([
            'name'     => 'source',
            'url'      => $this->url . '/files/' . $output['Key'],
            'width'    => $output['Width'] ?? 0,
            'height'   => $output['Height'] ?? 0,
            'duration' => $output['Duration'] ?? 0,
            'size'     => $output['FileSize'] ?? 0
        ]);
        Logger::send('converter.aws.readJob', $jobData['Output']);
        return true;
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