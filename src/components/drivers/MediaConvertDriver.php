<?php
/**
 * User: pel
 * Date: 2020-03-23
 */

namespace Converter\components\drivers;


use Aws\Credentials\Credentials;
use Aws\MediaConvert\MediaConvertClient;
use Aws\S3\MultipartCopy;
use Aws\S3\S3Client;
use Converter\components\Locker;
use Converter\components\Logger;
use Converter\components\Process;
use Converter\components\Redis;
use Converter\components\storages\S3Storage;
use Converter\helpers\FileHelper;
use Converter\response\StatusResponse;
use Converter\response\VideoResponse;

class MediaConvertDriver extends AmazonDriver
{
    protected $mediaConfig = [];
    public $watermarkInfo = [
        'width' => 0,
        'height' => 0
    ];
    
    /**
     * @param $filePath
     * @param $callback
     * @param null $processId
     * @param array $watermark
     * @return null|string
     */
    public function processVideo($filePath, $callback, $processId = null, $watermark = [])
    {
        $fileExt = FileHelper::getExt($filePath);
        if (isset($this->mediaConfig['presetForGif']) && $fileExt == 'gif') {
            Process::restart($processId, $this->mediaConfig['presetForGif']);
            return $processId;
        } else {
            return parent::processVideo($filePath, $callback, $processId, $watermark);
        }
    }
    
    /**
     * @return MediaConvertClient
     */
    protected function getClient()
    {
        return new MediaConvertClient([
            'version' => 'latest',
            'region' => $this->mediaConfig['region'],
            'credentials' => new Credentials($this->mediaConfig['key'], $this->mediaConfig['secret']),
            'endpoint' => $this->mediaConfig['endpoint']
        ]);
    }
    
    /**
     * @param Process $process
     * @return StatusResponse
     */
    public function getStatus($process)
    {
        $percent = $jobId = null;
        $processId = $process->getId();
        $client = $this->getClient();
        $process->log(__METHOD__);
        try {
            $jobs = Redis::getInstance()->sMembers('amazon:queue');
            foreach ($jobs as $job) {
                $options = json_decode($job, true);
                if ($options['processId'] == $processId) {
                    $jobId = $options['jobId'];
                    break;
                }
            }
            $process->log('Founded #' . $jobId);
            if ($jobId) {
                $response = $client->getJob(['Id' => $jobId]);
                $jobData = (array) $response->get('Job');
                $percent = $jobData['JobPercentComplete'] ?? null;
                $process->log("Percent $percent%");
            }
        } catch (\Throwable $e) {
            $process->log('Error on get status', ['error' => $e->getMessage()]);
        }
        return new StatusResponse([
            'id'      => $process->getId(),
            'percent' => $percent
        ]);
    }
    
    /**
     * @param $jobId
     * @param Process $process
     * @param array $jobData
     */
    public function readJob($jobId, $process, $jobData = [])
    {
        $process->log(__METHOD__, ['jobId' => $jobId]);
        $client = $this->getClient();
        try {
            if (empty($jobData)) {
                $response = $client->getJob(['Id' => $jobId]);
                Logger::send('converter.aws.readJob', $response->toArray());
                $jobData = $this->getJobData((array) $response->get('Job'));
            } else {
                $process->log('Use job data from web hook');
            }
            
            if (strtolower($jobData['status']) != 'complete') {
                $percent = $jobData['percentComplete'] ?? 'null';
                $process->log("Status {$jobData['status']}, $percent%");
                if (strtolower($jobData['status']) == 'error') {
                    $this->error = $jobData['errorMessage'] ?? '';
                }
                $this->restart($process->getId(), $jobId);
                return false;
            }
            
            $files = [];
            $sourcePath = null;
            $outputDetails = $jobData['outputGroupDetails'][0]['outputDetails'] ?? [];
            foreach ($outputDetails as $outputDetail) {
                if ($outputDetail['durationInMs'] <= 1001) {
                    $process->log('Detected 1 second video file');
                    $this->restart($process->getId(), $jobId);
                    return false;
                }
                $path = current($outputDetail['outputFilePaths']);
                $nameModifier = str_replace('.mp4', '', substr($path, strpos($path, '_') + 1));
                $path = str_replace("s3://{$this->s3['bucket']}/", '', $path);
                $duration = round($outputDetail['durationInMs']/1000);
                $files[] = [
                    'duration' => $duration,
                    'height' => $outputDetail['videoDetails']['heightInPx'],
                    'width' => $outputDetail['videoDetails']['widthInPx'],
                    'url' => $this->url . '/' . $path,
                    'path' => $path,
                    'name' => $nameModifier,
                ];
                if ($nameModifier == 'source') {
                    $sourcePath = $path;
                }
            }
            
            if ($this->previews && !$this->needPreviewOnStart) {
                $process->log('Start make previews');
                $driver = Driver::loadByConfig($this->presetName, $this->previews);
                $videoUrl = $this->url . '/' . $sourcePath;
                $previewPath = $this->getVideoFrame($videoUrl, 0);
                $process->log('Get video frame from ' . $videoUrl);
                if ($previewPath) {
                    $driver->createPhotoPreview($previewPath);
                } else {
                    $process->log('Failed get video frame');
                }
                foreach ($driver->getResult() as $result) {
                    $process->log('End make previews');
                    $this->result[] = $result;
                }
                
                $thumbsCount = $process->get('thumbsCount');
                if ($thumbsCount && !empty($this->thumbs)) {
                    if ($duration > $thumbsCount) {
                        $step = floor($duration / $thumbsCount);
                    } else {
                        $thumbsCount = $duration;
                        $step = 1;
                    }
                    $process->log("Start make {$thumbsCount} thumbs");
                    $driver = Driver::loadByConfig($this->presetName, $this->thumbs);
                    for ($i = 0; $i < $thumbsCount; $i++) {
                        $previewPath = $this->getVideoFrame($videoUrl, $i * $step);
                        $driver->createPhotoPreview($previewPath);
                    }
                    foreach ($driver->getResult() as $result) {
                        $this->result[] = $result;
                    }
                    
                    $process->log('End make thumbs');
                }
            }
    
            foreach ($files as $file) {
                $storage = $this->getStorage();
                if ($storage instanceof S3Storage && $storage->bucket != $this->s3['bucket']) {
                    try {
                        $s3Client = $this->getS3Client();
                        if ($s3Client->doesObjectExist($this->s3['bucket'], $file['path'])) {
                            $targetPath = $storage->generatePath($file['path']);
                            $uploader = new MultipartCopy($s3Client, "/{$this->s3['bucket']}/{$file['path']}", [
                                'Bucket' => $storage->bucket,
                                'Key'    => $targetPath,
                            ]);
                            $uploader->copy();
                            $s3Client->deleteObject([
                                'Bucket' => $this->s3['bucket'],
                                'Key'    => $file['path'],
                            ]);
                            $file['url'] = $storage->url . '/' . $targetPath;
                            $process->log("Moved file s3://{$this->s3['bucket']}/{$file['path']} => s3://{$storage->bucket}/$targetPath");
                            if ($process->getFileType() == FileHelper::TYPE_VIDEO) {
                                $this->result[] = new VideoResponse($file);
                            }
                        } else {
                            if ($file['name'] == 'source') {
                                $process->log('File not exists (source)');
                                $deltaTime = time() - $jobData['finishTime'];
                                if ($deltaTime > 300) {
                                    $this->error = 'File not exists (source)';
                                    return false;
                                }
                                return false;
                            } else {
                                $process->log('File not exists', ['path' => 's3://' . $this->s3['bucket'] . '/' . $file['path']]);
                                Logger::send('converter.fatal', [
                                    'path'  => 's3://' . $this->s3['bucket'] . '/' . $file['path'],
                                    'error' => 'File not exists'
                                ]);
                            }
                        }
                    } catch (\Throwable $exception) {
                        $process->log('Error on move file', ['error' => $exception->getMessage(), 'jobId' => $jobId]);
                        return false;
                    }
                } else {
                    if ($process->getFileType() == FileHelper::TYPE_VIDEO) {
                        $this->result[] = new VideoResponse($file);
                    }
                }
            }
        } catch (\Throwable $e) {
            $process->log('Failed read job', [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
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
        $process->log(__METHOD__);
        $s3Client = $this->getS3Client();
        $file = $process->getFile();
        if ($file) {
            if ($s3Client->doesObjectExist($file['Bucket'], $file['Key'])) {
                $keyName = 's3://' . $file['Bucket'] . '/' . $file['Key'];
                $process->log('Set keyName', ['keyName' => $keyName]);
            } else {
                $process->log("S3 file not found: s3://{$file['Bucket']}/{$file['Key']}");
                $this->error = 'File not found';
                return false;
            }
        } else {
            $process->log('S3 file object not found');
            return false;
        }
        
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
                'Rotate' => 'AUTO',
                'AlphaBehavior' => 'DISCARD',
            ]
        ];
        
        $client = $this->getClient();
        try {
            [$width, $height] = $this->getVideoDimensions($filePath);
            if ($width == 0 || $height == 0) {
                throw new \Exception("Wrong dimensions $width X $height");
            }
        } catch (\Throwable $exception) {
            $process->log('Error get dimensions', ['error' => $exception->getMessage()]);
            $process->log('Try get dimensions from ID3');
            $info = FileHelper::getFileID3($filePath);
            if (empty($info['SourceImageWidth']) && empty($info['SourceImageHeight'])) {
                $process->log('Empty ID3 info', $info);
                $this->error = 'File is empty';
                return false;
            }
            $width = $info['SourceImageWidth'];
            $height = $info['SourceImageHeight'];
        }
        
        try {
            $originalWidth = $this->roundNumberToEven($width);
            $originalHeight = $this->roundNumberToEven($height);
            $process->log('Get dimensions', ['dimensions' => "$originalWidth X $originalHeight"]);
            if (($originalWidth > 1920 && $originalHeight > 1080) || ($originalWidth > 1080 && $originalHeight > 1920)) {
                $width = $this->roundNumberToEven($originalWidth/2);
                $height = $this->roundNumberToEven($originalHeight/2);
                $process->log('Change dimensions', ['dimensions' => "$width X $height"]);
            } else {
                $width = $originalWidth;
                $height = $originalHeight;
            }
            $watermarkSettings = $process->getWatermark();
            if (empty($watermarkSettings['size'])) {
                $watermarkSettings['size'] = round(0.027 * $originalHeight);
            }
            $watermarkKey = $this->getWatermark($s3Client, $watermarkSettings);
            $process->log('Get watermark', ['settings' => $watermarkSettings, 'key' => $watermarkKey, 'height' => $this->watermarkInfo['height'], 'width' => $this->watermarkInfo['width']]);
            if ($watermarkKey) {
                $imageX = $originalWidth - 10 - $this->watermarkInfo['width'];
                $imageY = $originalHeight - 10 - $this->watermarkInfo['height'];
                if ($imageX < 0) {
                    $imageX = 0;
                }
                if ($imageY < 0) {
                    $imageY = 0;
                }
                $inputSettings['ImageInserter']['InsertableImages'][] = [
                    'ImageX' => $imageX,
                    'ImageY' => $imageY,
                    'Layer' => 10,
                    'ImageInserterInput' => $watermarkKey,
                    'Opacity' => 100,
                ];
            }
            $outputGroup = [
                'Name' => 'Group',
                'OutputGroupSettings' => [
                    'Type' => 'FILE_GROUP_SETTINGS',
                    'FileGroupSettings' => [
                        'Destination' => "s3://{$this->s3['bucket']}/files/$processId/$processId"
                    ]
                ],
                'Outputs' => [],
            ];
            [$presetId, $presetSettings] = $this->getSourcePresetId($width, $height);
            if ($presetId) {
                $this->mediaConfig['presets'][$presetId] = $presetSettings;
                $process->log('Selected source preset ' . $presetSettings['height']);
            } else {
                $process->log('No selected source preset');
            }
            foreach ($this->mediaConfig['presets'] as $presetId => $presetSettings) {
                if ($presetSettings['name'] != 'source' && !empty($presetSettings['height']) && $presetSettings['height'] > $height) {
                    $process->log('Skip preset with height ' . $presetSettings['height']);
                    continue;
                }
                $ratio = $presetSettings['name'] == 'source' ? 1 :round($presetSettings['height'] / $height, 4);
                $newWidth = $this->roundNumberToEven($width * $ratio);
                $newHeight = $this->roundNumberToEven($height * $ratio);
                $outputGroup['Outputs'][] = [
                    'Preset' => $presetId,
                    'NameModifier' => '_' . $presetSettings['name'],
                    'VideoDescription' => [
                        'Width' => $newWidth,
                        'Height' => $newHeight
                    ]
                ];
                $process->log("Set preset {$presetSettings['name']} with $newWidth X $newHeight");
            }
    
            $jobSettings = [
                'Role' => $this->mediaConfig['role'],
                'Settings' => [
                    'Inputs' => [$inputSettings],
                    'OutputGroups' => [$outputGroup],
                ],
            ];
            if (isset($this->mediaConfig['mainQueue']) && isset($this->mediaConfig['queues'])) {
                $jobSettings['Queue'] = $this->mediaConfig['mainQueue']['id'];
                $jobSettings['HopDestinations'][] = [
                    'WaitMinutes' => (int) $this->mediaConfig['mainQueue']['waitMinutes'],
                    'Queue' => $this->mediaConfig['queues'][array_rand($this->mediaConfig['queues'])],
                ];
                $process->log('Set mainQueue and HopDestinations');
            } elseif (isset($this->mediaConfig['queues'])) {
                $jobSettings['Queue'] = $this->mediaConfig['queues'][array_rand($this->mediaConfig['queues'])];
                $process->log('Set random queue from list: ' . $jobSettings['Queue']);
            } elseif (isset($this->mediaConfig['queue'])) {
                $jobSettings['Queue'] = $this->mediaConfig['queue'];
                $process->log('Set single queue');
            } else {
                $process->log('Wrong config for queue');
                return false;
            }
            $job = $client->createJob($jobSettings);
        } catch (\Throwable $e) {
            $process->log('Failed create media convert job', [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->restart($process->getId());
            return false;
        }
        $job = (array)$job->get('Job');
        $process->log('Create MediaConvert job', ['status' => 'success', 'jobId' => $job['Id']]);
        if (strtolower($job['Status']) == 'submitted') {
            $process->log('Added to amazon:queue', ['status' => 'success']);
            Redis::getInstance()->sAdd('amazon:queue', json_encode([
                'jobId' => $job['Id'],
                'processId' => $processId,
                'presetName' => $this->presetName,
                'timestamp' => time(),
            ]));
            Locker::lock("process:{$process->getId()}", 300);
            return true;
        } else {
            $process->log('Wrong job', $job);
            return false;
        }
    }
    
    protected function getJobData($job)
    {
        $data = [
            'finishTime' => isset($job['Timing']['FinishTime']) ? strtotime($job['Timing']['FinishTime']) : time(),
            'status' => $job['Status'],
            'percentComplete' => $job['JobPercentComplete'] ?? null,
            'errorMessage' => $job['ErrorMessage'] ?? null,
        ];
    
        $outputDetails = $job['OutputGroupDetails'][0]['OutputDetails'] ?? [];
        $outputs = $job['Settings']['OutputGroups'][0]['Outputs'] ?? [];
        $path = $job['Settings']['OutputGroups'][0]['OutputGroupSettings']['FileGroupSettings']['Destination'] ?? null;
        foreach ($outputDetails as $index => $outputDetail) {
            if (empty($outputs[$index])) {
                continue;
            }
            $nameModifier = $outputs[$index]['NameModifier'];
            $data['outputGroupDetails'][0]['outputDetails'][] = [
                'outputFilePaths' => [$path . $nameModifier . '.mp4'],
                'durationInMs' => $outputDetail['DurationInMs'],
                'videoDetails' => [
                    'widthInPx' => $outputDetail['VideoDetails']['WidthInPx'],
                    'heightInPx' => $outputDetail['VideoDetails']['HeightInPx'],
                ]
            ];
        }
        
        return $data;
    }
    
    protected function restart($processId, $jobId = null)
    {
        if ($jobId) {
            Redis::getInstance()->sRem('amazon:queue', json_encode([
                'jobId' => $jobId,
                'processId' => $processId,
                'presetName' => $this->presetName
            ]));
        }
        Process::restart($processId, $this->mediaConfig['presetForGif']);
    }
    
    protected function getSourcePresetId($width, $height)
    {
        foreach (array_reverse($this->mediaConfig['sourcePresets']) as $presetId => $presetSettings) {
            if ($height && !empty($presetSettings['height']) && $presetSettings['height'] > $height) {
                return [$presetId, $presetSettings];
            }
        }
        return [$presetId, $presetSettings];
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
     * @param $number
     * @return mixed
     */
    protected function roundNumberToEven($number)
    {
        $number = round($number);
        if ($number % 2) {
            return $number - 1;
        }
        return $number;
    }
    
    /**
     * @param S3Client $s3Client
     * @param array $watermark
     * @return null|string
     */
    public function getWatermark($s3Client, $watermark = [])
    {
        $watermarkKey = parent::getWatermark($s3Client, $watermark);
        if ($watermarkKey) {
            $localPath = FileHelper::getLocalPath($s3Client->getObjectUrl($this->s3['bucket'], $watermarkKey));
            if (file_exists($localPath)) {
                [$width, $height] = getimagesize($localPath);
                $this->watermarkInfo = [
                    'width' => $width,
                    'height' => $height
                ];
            }
            return "s3://{$this->s3['bucket']}/$watermarkKey";
        }
        
        return $watermarkKey;
    }
}