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
    protected $watermarkInfo = [
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
     * @param $jobId
     * @param Process $process
     */
    public function readJob($jobId, $process)
    {
        $process->log(__METHOD__, ['jobId' => $jobId]);
        $client = $this->getClient();
        try {
            $response = $client->getJob(['Id' => $jobId]);
            Logger::send('converter.aws.readJob', $response->toArray());
            $jobData = (array) $response->get('Job');
            if (strtolower($jobData['Status']) != 'complete') {
                $percent = $jobData['JobPercentComplete'] ?? 'null';
                $process->log("Status {$jobData['Status']}, $percent%");
                if (strtolower($jobData['Status']) == 'error') {
                    $this->error = $jobData['ErrorMessage'] ?? '';
                }
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
            }
    
            foreach ($files as $file) {
                $storage = $this->getStorage();
                if ($storage instanceof S3Storage && $storage->bucket != $this->s3['bucket']) {
                    try {
                        $s3Client = $this->getS3Client();
                        if ($s3Client->doesObjectExist($this->s3['bucket'], $file['path'])) {
                            $targetPath = $storage->generatePath($file['path']);
                            $s3Client->copyObject([
                                'Bucket'     => $storage->bucket,
                                'Key'        => $targetPath,
                                'CopySource' => $this->s3['bucket'] . '/' . $file['path'],
                            ]);
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
                                $finishTime = round($jobData['Timing']['FinishTimeMillis']/100);
                                $deltaTime = time() - $finishTime;
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
                'Rotate' => 'DEGREE_0',
                'AlphaBehavior' => 'DISCARD',
            ]
        ];
        
        $client = $this->getClient();
        try {
            list($width, $height) = $this->getVideoDimensions($filePath);
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
            $width = $this->roundNumberToEven($width);
            $height = $this->roundNumberToEven($height);
            $process->log('Get dimensions', ['dimensions' => "$width X $height"]);
            $watermarkKey = $this->getWatermark($s3Client, $process->getWatermark());
            $process->log('Get watermark', ['settings' => $process->getWatermark(), 'key' => $watermarkKey]);
            if ($watermarkKey) {
                $imageX = $width - 10 - $this->watermarkInfo['width'];
                $imageY = $height - 10 - $this->watermarkInfo['height'];
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
            list($presetId, $presetSettings) = $this->getSourcePresetId($width, $height);
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
    
            $jobSettings['OutputGroups'][] = $outputGroup;
            if (isset($this->mediaConfig['queues'])) {
                $queue = $this->mediaConfig['queues'][array_rand($this->mediaConfig['queues'])];
            } else {
                $queue = $this->mediaConfig['queue'];
            }
            $job = $client->createJob([
                'Role' => $this->mediaConfig['role'],
                'Settings' => [
                    'Inputs' => [$inputSettings],
                    'OutputGroups' => [$outputGroup],
                ],
                'Queue' => $queue,
            ]);
        } catch (\Throwable $e) {
            $process->log('Failed create media convert job', [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            $this->error = $e->getMessage();
            return false;
        }
        $job = (array)$job->get('Job');
        $process->log('Create MediaConvert job', ['status' => 'success', 'jobId' => $job['Id']]);
        if (strtolower($job['Status']) == 'submitted') {
            $process->log('Added to amazon:queue', ['status' => 'success']);
            Redis::getInstance()->sAdd('amazon:queue', json_encode([
                'jobId' => $job['Id'],
                'processId' => $processId,
                'presetName' => $this->presetName
            ]));
            return true;
        } else {
            $process->log('Wrong job', $job);
            return false;
        }
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
    protected function getWatermark($s3Client, $watermark = [])
    {
        $watermarkKey = parent::getWatermark($s3Client, $watermark);
        if ($watermarkKey) {
            $localPath = FileHelper::getLocalPath($s3Client->getObjectUrl($this->s3['bucket'], $watermarkKey));
            if (file_exists($localPath)) {
                list($width, $height) = getimagesize($localPath);
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