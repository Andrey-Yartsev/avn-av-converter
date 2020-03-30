<?php
/**
 * User: pel
 * Date: 2020-03-23
 */

namespace Converter\components\drivers;


use Aws\Credentials\Credentials;
use Aws\MediaConvert\MediaConvertClient;
use Aws\S3\S3Client;
use Converter\components\Logger;
use Converter\components\Process;
use Converter\components\Redis;
use Converter\components\storages\S3Storage;
use Converter\helpers\FileHelper;
use Converter\response\VideoResponse;

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
            'credentials' => new Credentials($this->mediaConfig['key'], $this->mediaConfig['secret']),
            'endpoint' => 'https://q25wbt2lc.mediaconvert.us-east-1.amazonaws.com'
        ]);
    }
    
    /**
     * @param $jobId
     * @param Process $process
     */
    public function readJob($jobId, $process)
    {
        Logger::send('process', ['processId' => $process->getId(), 'jobId' => $jobId, 'step' =>  __METHOD__]);
        $client = $this->getClient();
        try {
            $response = $client->getJob(['Id' => $jobId]);
            Logger::send('converter.aws.readJob', $response->toArray());
            $jobData = (array) $response->get('Job');
            if (strtolower($jobData['Status']) != 'complete') {
                return false;
            }
            
            $files = [];
            $outputDetails = $jobData['OutputGroupDetails'][0]['OutputDetails'] ?? [];
            $outputs = $jobData['Settings']['OutputGroups'][0]['Outputs'] ?? [];
            $path = $jobData['Settings']['OutputGroups'][0]['OutputGroupSettings']['FileGroupSettings']['Destination'] ?? null;
            $path = str_replace("s3://{$this->s3['bucket']}/", '', $path);
            $sourcePath = null;
            foreach ($outputDetails as $index => $outputDetail) {
                if (empty($outputs[$index])) {
                    continue;
                }
                $nameModifier = $outputs[$index]['NameModifier'];
                $files[] = [
                    'duration' => round($outputDetail['DurationInMs']/1000),
                    'height' => $outputDetail['VideoDetails']['HeightInPx'],
                    'width' => $outputDetail['VideoDetails']['WidthInPx'],
                    'url' => $this->url . '/' . $path . $nameModifier . '.mp4',
                    'path' => $path . $nameModifier . '.mp4',
                    'name' => substr($nameModifier, 1),
                    'presetId' => $outputs[$index]['Preset'],
                ];
                if ($nameModifier == '_source') {
                    $sourcePath = $path . $nameModifier . '.mp4';
                }
            }
            
            if ($this->previews && !$this->needPreviewOnStart) {
                Logger::send('process', ['processId' => $process->getId(), 'step' => 'Start make previews']);
                $driver = Driver::loadByConfig($this->presetName, $this->previews);
                $videoUrl = $this->url . $sourcePath;
                $previewPath = $this->getVideoFrame($videoUrl, 0);
                if ($previewPath) {
                    $driver->createPhotoPreview($previewPath);
                }
                foreach ($driver->getResult() as $result) {
                    Logger::send('process', ['processId' => $process->getId(), 'step' => 'End make previews']);
                    $this->result[] = $result;
                }
            }
    
            foreach ($files as $file) {
                if (isset($this->mediaConfig['presets'][$file['presetId']])) {
                    Logger::send('process', ['processId' => $process->getId(), 'step' => "Find # {$file['presetId']} ({$file['name']})"]);
                } else {
                    Logger::send('process', ['processId' => $process->getId(), 'step' => "Skip # {$file['presetId']} ({$file['name']})"]);
                    continue;
                }
                $storage = $this->getStorage();
                if ($storage instanceof S3Storage && $storage->bucket != $this->s3['bucket']) {
                    try {
                        $s3Client = $this->getS3Client();
                        if ($s3Client->doesObjectExist($this->s3['bucket'], $file['path'])) {
                            $s3Client->copyObject([
                                'Bucket'     => $storage->bucket,
                                'Key'        => $file['path'],
                                'CopySource' => $this->s3['bucket'] . '/' . $file['path'],
                            ]);
                            $s3Client->deleteObject([
                                'Bucket' => $this->s3['bucket'],
                                'Key'    => $file['path'],
                            ]);
                            Logger::send('process', ['processId' => $process->getId(), 'step' => 'Moved file']);
                            if ($process->getFileType() == FileHelper::TYPE_VIDEO) {
                                $this->result[] = new VideoResponse($file);
                            }
                        } else {
                            if ($file['name'] == 'source') {
                                Logger::send('process', ['processId' => $process->getId(), 'step' => 'File not exists (source)']);
                                $finishTime = round($jobData['Timing']['FinishTimeMillis']/100);
                                $deltaTime = time() - $finishTime;
                                if ($deltaTime > 300) {
                                    $this->error = 'File not exists (source)';
                                    return false;
                                }
                                return false;
                            } else {
                                Logger::send('process', ['processId' => $process->getId(), 'step' => 'File not exists', 'path'  => 's3://' . $this->s3['bucket'] . '/' . $file['path'],]);
                                Logger::send('converter.fatal', [
                                    'path'  => 's3://' . $this->s3['bucket'] . '/' . $file['path'],
                                    'error' => 'File not exists'
                                ]);
                            }
                        }
                    } catch (\Throwable $exception) {
                        Logger::send('converter.fatal', [
                            'job'   => $jobData['Output'],
                            'error' => $exception->getMessage()
                        ]);
                        $jobId = $jobData['Output']['Id'] ?? 'unknown';
                        $this->error = 'Job #' . $jobId . ' failed.';
                        return false;
                    }
                } else {
                    if ($process->getFileType() == FileHelper::TYPE_VIDEO) {
                        $this->result[] = new VideoResponse($file);
                    }
                }
            }
        } catch (\Throwable $e) {
            Logger::send('process', ['processId' => $process->getId(), 'step' => 'Failed read job', 'data' => [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]]);
            return false;
        }
        return true;
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
                'Opacity' => 100,
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
                        'Destination' => "s3://{$this->s3['bucket']}/$processId/$processId"
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