<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\forms;


use Aws\ElasticTranscoder\ElasticTranscoderClient;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Converter\components\Config;
use Converter\components\Form;
use Converter\components\Redis;

class AmazonForm extends Form
{
    public $filePath;
    public $callback;
    public $processId;
    
    public function addQueue()
    {
        $rules = [
            'required' => ['filePath', 'callback'],
            'url' => ['filePath', 'callback'],
        ];
    
        if (!$this->validate($rules)) {
            return false;
        }
        $processId = uniqid() . time();
        Redis::getInstance()->publish('au', json_encode([
            'processId' => $processId,
            'callback' => $this->callback,
            'filePath' => $this->filePath
        ]));
        return $processId;
    }
    
    public function processVideo()
    {
        $pathParts = pathinfo($this->filePath);
        $keyName = '/temp_video/' . uniqid(parse_url($this->filePath, PHP_URL_HOST), true) . '.' . $pathParts['extension'];
        $amazonConfig = Config::getInstance()->get('amazon');
        
        $s3Client = new S3Client([
            'version' => 'latest',
            'region'  => $amazonConfig['s3']['region'],
            'credentials' => [
                'key' => $amazonConfig['s3']['key'],
                'secret' => $amazonConfig['s3']['secret']
            ]
        ]);
    
        try {
            $s3Client->putObject([
                'Bucket' => $amazonConfig['s3']['bucket'],
                'Key' => $keyName,
                'SourceFile' => $this->filePath,
            ]);
        } catch (S3Exception $e) {
            $this->setErrors($e->getMessage());
            return false;
        }
    
        $dir = substr($this->processId, 0, 1) . '/' . substr($this->processId, 0, 2) . '/' . substr($this->processId, 0, 3) . '/' . $this->processId;
        
        $transcoderClient = new ElasticTranscoderClient([
            'version' => 'latest',
            'region' => $amazonConfig['transcoder']['region'],
            'credentials' => [
                'key' => $amazonConfig['transcoder']['key'],
                'secret' => $amazonConfig['transcoder']['secret'],
            ]
        ]);
        try {
            $job = $transcoderClient->createJob([
                'PipelineId'      => $amazonConfig['transcoder']['pipeline'],
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
                        'PresetId' => $amazonConfig['transcoder']['preset'],
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            $this->setErrors($e->getMessage());
            return false;
        }
    
        $job = (array)$job->get('Job');
        if (strtolower($job['Status']) == 'submitted') {
            Redis::getInstance()->sAdd('amazon:queue', json_encode([
                'jobId' => $job['Id'],
                'processId' => $this->processId,
                'callback' => $this->callback
            ]));
        }
    }
}