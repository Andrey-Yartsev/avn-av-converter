<?php
/**
 * User: pel
 * Date: 2020-03-23
 */

namespace Converter\components\drivers;


use Aws\MediaConvert\MediaConvertClient;
use Aws\S3\S3Client;
use Converter\components\Logger;
use Converter\components\Process;
use Converter\components\Redis;
use Converter\helpers\FileHelper;

class MediaConvertDriver extends AmazonDriver
{
    protected $mediaConfig = [];
    
    /**
     * @return MediaConvertClient
     */
    protected function getClient()
    {
        return new MediaConvertClient([
            'version' => 'latest',
            'region' => $this->mediaConfig['region'],
            'profile' => 'default',
            'endpoint' => 'https://q25wbt2lc.mediaconvert.us-east-1.amazonaws.com'
        ]);
    }
    
    /**
     * @param $jobId
     * @param Process $process
     */
    public function readJob($jobId, $process)
    {
        Logger::send('process', ['processId' => $process->getId(), 'step' => __CLASS__ . '::' . __METHOD__]);
        $client = $this->getClient();
        try {
            $job = $client->getJob(['ID' => $jobId]);
            Logger::send('process', ['processId' => $process->getId(), 'debug' => $job->toArray()]);
        } catch (\Throwable $e) {
        
        }
    }
    
    /**
     * @param Process $process
     * @return bool
     */
    public function createJob($process)
    {
        $processId = $process->getId();
        $filePath = $process->getFilePath();
        Logger::send('process', ['processId' => $processId, 'step' =>  __METHOD__]);
    
        $file = $process->getFile();
        if ($file) {
            $keyName = 's3://' . $file['Bucket'] . '/' . $file['Key'];
            Logger::send('process', ['processId' => $processId, 'step' => 'Set keyName', 'keyName' => $keyName]);
        } else {
            Logger::send('process', ['processId' => $processId, 'step' => 'Error']);
            return false;
        }
       
        $s3Client = $this->getS3Client();
        $inputSettings = [
            'FileInput' => $keyName,
            'AudioSelectors' => [
                'Audio Selector 1' => [
                    'Offset' => 0,
                    'DefaultSelection' => 'DEFAULT',
                    'ProgramSelection' => 1,
                ]
            ],
            'VideoSelector' => [
                'ColorSpace' => 'FOLLOW',
                'Rotate' => 'DEGREE_0',
                'AlphaBehavior' => 'DISCARD',
            ]
        ];
    
        $watermarkKey = $this->getWatermark($s3Client, $process->getWatermark());
        if ($watermarkKey) {
            $inputSettings['ImageInserter']['InsertableImages'][] = [
                'ImageX' => 10,
                'ImageY' => 10,
                'Layer' => 10,
                'ImageInserterInput' => $watermarkKey,
                'Opacity' => 0,
            ];
        }
        $client = $this->getClient();
        try {
            list($width, $height) = FileHelper::getVideoDimensions($filePath);
            Logger::send('process', ['processId' => $processId, 'step' => 'Get dimensions', 'data' => "$width X $height"]);
            $outputGroup = [
                'Name' => 'Group',
                'OutputGroupSettings' => [
                    'Type' => 'FILE_GROUP_SETTINGS',
                    'FileGroupSettings' => [
                        'Destination' => 's3://test-of2/123'
                    ]
                ],
                'Outputs' => [],
            ];
            foreach ($this->mediaConfig['presets'] as $presetId => $presetSettings) {
                if ($height && !empty($presetSettings['height']) && $presetSettings['height'] > $height) {
                    Logger::send('process', ['processId' => $processId, 'step' => 'Skip preset with height ' . $presetSettings['height']]);
                    continue;
                }
                $outputGroup['Outputs'][] = [
                    'Preset' => $presetId,
                    'NameModifier' => '_' . $presetSettings['name']
                ];
            }
    
            $jobSettings['OutputGroups'][] = $outputGroup;
            $job = $client->createJob([
                'Role' => $this->mediaConfig['role'],
                'Settings' => [
                    'Inputs' => [$inputSettings],
                    'OutputGroups' => [$outputGroup],
                ],
                'Queue' => $this->mediaConfig['queue'],
            ]);
            Logger::send('process', ['processId' => $process->getId(), 'debug' => $job->toArray()]);
        } catch (\Throwable $e) {
            Logger::send('process', ['processId' => $processId, 'step' => 'Create media convert job', 'data' => [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]]);
            return false;
        }
        $job = (array)$job->get('Job');
        Logger::send('process', ['processId' => $processId, 'step' => 'Create MediaConvert job', 'data' => ['status' => 'success', 'jobId' => $job['Id']]]);
        if (strtolower($job['Status']) == 'submitted') {
            Logger::send('process', ['processId' => $processId, 'step' => 'Added to amazon:queue', 'data' => ['status' => 'success']]);
            Redis::getInstance()->sAdd('amazon:queue', json_encode([
                'jobId' => $job['Id'],
                'processId' => $processId,
                'presetName' => $this->presetName
            ]));
            return true;
        } else {
            Logger::send('process', ['processId' => $processId, 'step' => 'Wrong job', 'data' => $job]);
            return false;
        }
    }
    
    /**
     * @return S3Client
     */
    protected function getS3Client()
    {
        return new S3Client([
            'version' => 'latest',
            'region'  => $this->s3['region'],
            'credentials' => [
                'key' => $this->s3['key'],
                'secret' => $this->s3['secret']
            ]
        ]);
    }
    
    /**
     * @param S3Client $s3Client
     * @param array $watermark
     * @return null|string
     */
    protected function getWatermark($s3Client, $watermark = [])
    {
        $watermarkKey = parent::getWatermark($s3Client, $watermark);
        return $watermarkKey ? "s3://{$this->s3['bucket']}/$watermarkKey" : $watermarkKey;
    }
}