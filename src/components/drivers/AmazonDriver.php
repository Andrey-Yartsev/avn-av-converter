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
use Converter\components\storages\S3Storage;
use Converter\helpers\FileHelper;
use Converter\response\AudioResponse;
use Converter\response\StatusResponse;
use Converter\response\VideoResponse;
use FFMpeg\Coordinate\TimeCode;
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
        Logger::send('process', ['processId' => $processId, 'step' => 'Send to upload amazon queue']);
        Redis::getInstance()->sAdd('amazon:upload', json_encode([
            'presetName' => $this->presetName,
            'processId' => $processId
        ]));
        return $processId;
    }

    public function processAudio($filePath, $callback, $processId = null, $watermark = [])
    {
        Logger::send('process', ['processId' => $processId, 'step' => 'Send to upload amazon queue']);
        Redis::getInstance()->sAdd('amazon:upload', json_encode([
            'presetName' => $this->presetName,
            'processId' => $processId
        ]));
        return $processId;
    }

    public function processPhoto($filePath, $callback, $processId = null, $watermark = [])
    {
        throw new \Exception('Not implemented ' . __CLASS__ . ' ' . __METHOD__ . ' ' . json_encode(func_get_args()));
    }

    public function getStatus($processId)
    {
        Logger::send('converter.amazon.status', [
            'processId'      => $processId,
            'percent' => 0
        ]);
        return new StatusResponse([
            'id'      => $processId,
            'percent' => 0
        ]);
    }

    public function createPhotoPreview($filePath, $watermark = [])
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

    /**
     * @param string $jobId
     * @param Process $process
     * @return bool
     */
    public function readJob($jobId, $process)
    {
        $transcoderClient = $this->getTranscoderClient();
        $response = $transcoderClient->readJob(['Id' => $jobId]);
        Logger::send('converter.aws.readJob', $response->toArray());
        $jobData = (array) $response->get('Job');
        if (strtolower($jobData['Status']) != 'complete') {
            if (strtolower($jobData['Status']) == 'error') {
                Logger::send('converter.aws.readJob', $jobData['Output']);
                $this->error = $jobData['Output']['StatusDetail'];
            }
            return false;
        }
        $output = $jobData['Output'];

        if ($this->previews && !$this->needPreviewOnStart) {
            Logger::send('process', ['processId' => $process->getId(), 'step' => 'Start make previews']);
            $driver = Driver::loadByConfig($this->presetName, $this->previews);
            $videoUrl = $this->url . '/files/' . $output['Key'];
            $previewPath = $this->getVideoFrame($videoUrl, 0);
            if ($previewPath) {
                $driver->createPhotoPreview($previewPath);
            } elseif (!empty($output['ThumbnailPattern'])) {
                $thumbUrl = $this->url . '/files/' . $output['ThumbnailPattern'] . '.jpg';
                $thumbUrl = str_replace('{count}', '00001', $thumbUrl);
                $driver->createPhotoPreview($thumbUrl);
            }
            foreach ($driver->getResult() as $result) {
                Logger::send('process', ['processId' => $process->getId(), 'step' => 'End make previews']);
                $this->result[] = $result;
            }
        }
        $responseName = 'source';
        foreach ($jobData['Outputs'] as $output) {
            if (isset($this->transcoder['presets'][$output['PresetId']])) {
                $responseName = $this->transcoder['presets'][$output['PresetId']]['name'];
                Logger::send('process', ['processId' => $process->getId(), 'step' => 'Find #' . $output['PresetId'] . " ($responseName)"]);
            } elseif (empty($this->transcoder['preset']) ||  $this->transcoder['preset'] != $output['PresetId']) {
                Logger::send('process', ['processId' => $process->getId(), 'step' => 'Skip output #' . $output['PresetId']]);
                continue;
            }
            if ($this->hasStorage()) {
                $storage = $this->getStorage();
                if ($storage instanceof S3Storage && $storage->bucket != $this->s3['bucket']) {
                    try {
                        $s3Client = $this->getS3Client();
                        if ($s3Client->doesObjectExist($this->s3['bucket'], 'files/' . $output['Key'])) {
                            $s3Client->copyObject([
                                'Bucket'     => $storage->bucket,
                                'Key'        => 'files/' . $output['Key'],
                                'CopySource' => $this->s3['bucket'] . '/files/' . $output['Key'],
                            ]);
                            $s3Client->deleteObject([
                                'Bucket' => $this->s3['bucket'],
                                'Key' => 'files/' . $output['Key'],
                            ]);
                            Logger::send('process', ['processId' => $process->getId(), 'step' => 'Moved file']);
                            if ($process->getFileType() == FileHelper::TYPE_VIDEO) {
                                $this->result[] = new VideoResponse([
                                    'name'     => $responseName,
                                    'url'      => $storage->url . '/files/' . $output['Key'],
                                    'width'    => $output['Width'] ?? 0,
                                    'height'   => $output['Height'] ?? 0,
                                    'duration' => $output['Duration'] ?? 0,
                                    'size'     => $output['FileSize'] ?? 0
                                ]);
                            } elseif ($process->getFileType() == FileHelper::TYPE_AUDIO) {
                                $this->result[] = new AudioResponse([
                                    'name'     => $responseName,
                                    'url'      => $storage->url . '/files/' . $output['Key'],
                                    'duration' => $output['Duration'] ?? 0,
                                    'size'     => $output['FileSize'] ?? 0
                                ]);
                            }
                        } else {
                            Logger::send('process', ['processId' => $process->getId(), 'step' => 'Error move file']);
                            Logger::send('converter.fatal', [
                                'path' => 's3://' . $this->s3['bucket'] . '/files/' . $output['Key'],
                                'error' => 'File exists'
                            ]);
                        }
                    } catch (\Throwable $exception) {
                        Logger::send('converter.fatal', [
                            'job' => $jobData['Output'],
                            'error' => $exception->getMessage()
                        ]);
                        $jobId = $jobData['Output']['Id'] ?? 'unknown';
                        $this->error = 'Job #' . $jobId . ' failed.';
                        return false;
                    }
                }
            } else {
                if ($process->getFileType() == FileHelper::TYPE_VIDEO) {
                    $this->result[] = new VideoResponse([
                        'name'     => $responseName,
                        'url'      => $this->url . '/files/' . $output['Key'],
                        'width'    => $output['Width'] ?? 0,
                        'height'   => $output['Height'] ?? 0,
                        'duration' => $output['Duration'] ?? 0,
                        'size'     => $output['FileSize'] ?? 0
                    ]);
                } elseif ($process->getFileType() == FileHelper::TYPE_AUDIO) {
                    $this->result[] = new AudioResponse([
                        'name'     => $responseName,
                        'url'      => $this->url . '/files/' . $output['Key'],
                        'duration' => $output['Duration'] ?? 0,
                        'size'     => $output['FileSize'] ?? 0
                    ]);
                }
                
            }
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
        Logger::send('process', ['processId' => $processId, 'step' => 'createJob()']);
        $file = $process->getFile();
        $s3Client = $this->getS3Client();
        if ($file) {
            //@TODO validate file type for aws
            $keyName = $file['Key'];
            Logger::send('process', ['processId' => $processId, 'step' => 'Set keyName', 'keyName' => $keyName]);
            $ext = FileHelper::getExt($filePath);
            if ($ext == 'gif') {
                // @todo handle as image
                if (!$this->getVideoDuration($filePath)) {
                    $localPath = FileHelper::getLocalPath($filePath);
                    $tmpPath = PUBPATH . '/upload/' . md5($filePath) . '_gif.mp4';
                    shell_exec(
                        sprintf(
                            'ffmpeg -f gif -i %s %s',
                            escapeshellarg($localPath),
                            escapeshellarg($tmpPath)
                        )
                    );
                    $keyName = 'temp_video/' . pathinfo($tmpPath, PATHINFO_BASENAME);
                    $s3Client->putObject([
                        'Bucket' => $this->s3['bucket'],
                        'Key' => $keyName,
                        'SourceFile' => $tmpPath,
                    ]);
                }
            }
        } else {
            $pathParts = pathinfo($filePath);
            $keyName = 'temp_video/' . parse_url($filePath, PHP_URL_HOST) . '/' . date('Y_m_d') . '/' . uniqid('', true) . '.' . $pathParts['extension'];
            
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
            } catch (\Throwable $e) {
                Logger::send('process', ['processId' => $processId, 'step' => 'Upload to S3', 'data' => [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]]);
                return false;
            }
        }
        
        Logger::send('process', ['processId' => $processId, 'step' => 'Upload to S3', 'data' => ['status' => 'success']]);
        $dir = date('Y_m_d') . '/' . substr($processId, 0, 2) . '/' . substr($processId, 0, 3) . '/' . $processId;

        $watermarkKey = $this->getWatermark($s3Client, $process->getWatermark());
        Logger::send('process', ['processId' => $processId, 'step' => 'Get watermark', 'data' => ['key' => $watermarkKey]]);
        Logger::send('amazon.watermark', ['key' => $watermarkKey]);
        $transcoderClient = $this->getTranscoderClient();
        try {
            $outputSettings = [];
            if (!empty($this->transcoder['preset'])) {
                $ext = null;
                if ($process->getFileType() == FileHelper::TYPE_VIDEO) {
                    $ext = 'mp4';
                } elseif ($process->getFileType() == FileHelper::TYPE_AUDIO) {
                    $ext = 'mp3';
                }
                $outputSettings[] = [
                    'Key'      => $dir . '.' . $ext,
                    'Rotate'   => 'auto',
                    'PresetId' => $this->transcoder['preset']
                ];
            } else {
                list($width, $height) = FileHelper::getVideoDimensions($filePath);
                Logger::send('process', ['processId' => $processId, 'step' => 'Get dimensions', 'data' => "$width X $height"]);
                foreach ($this->transcoder['presets'] as $presetId => $presetSettings) {
                    if ($height && !empty($presetSettings['height']) && $presetSettings['height'] > $height) {
                        Logger::send('process', ['processId' => $processId, 'step' => 'Skip preset with height ' . $presetSettings['height']]);
                        continue;
                    }
                    $outputSetting = [
                        'Key'      => $dir . '_' . strtolower($presetSettings['name']) . '.mp4',
                        'Rotate'   => 'auto',
                        'PresetId' => $presetId
                    ];
                    if ($watermarkKey) {
                        Logger::send('process', [
                            'processId' => $processId,
                            'step'      => 'Set watermark',
                            'data'      => ['status' => 'success', 'presetId' => $presetId, 'settings' => $presetSettings]
                        ]);
                        $outputSetting['Watermarks'][] = [
                            'InputKey' => $watermarkKey,
                            'PresetWatermarkId' => 'BottomRight'
                        ];
                    }
                    $outputSettings[] = $outputSetting;
                }
            }
            
            $job = $transcoderClient->createJob([
                'PipelineId'      => $this->transcoder['pipeline'],
                'OutputKeyPrefix' => 'files/',
                'Input' => [
                    'Key'         => $keyName,
                    'FrameRate'   => 'auto',
                    'Resolution'  => 'auto',
                    'AspectRatio' => 'auto',
                    'Interlaced'  => 'auto',
                    'Container'   => 'auto',
                ],
                'Outputs' => array_values($outputSettings),
            ]);
        } catch (\Exception $e) {
            Logger::send('process', ['processId' => $processId, 'step' => 'Create transcoder job', 'data' => [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]]);
            return false;
        }
        $job = (array)$job->get('Job');
        Logger::send('process', ['processId' => $processId, 'step' => 'Create transcoder job', 'data' => ['status' => 'success', 'jobId' => $job['Id']]]);
        
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
    
    protected function moveObject($keyName, $sourceBucket, $targetBucket)
    {
        try {
            $s3Client = $this->getS3Client();
            $s3Client->copyObject([
                'Bucket'     => $targetBucket,
                'Key'        => $keyName,
                'CopySource' => $sourceBucket . $keyName,
            ]);
            $s3Client->deleteObject([
                'Bucket' => $sourceBucket,
                'Key' => $keyName,
            ]);
            return true;
        } catch (\Throwable $e) {
            Logger::send('converter.fatal', [
                'args' => compact('keyName', 'sourceBucket', 'targetBucket'),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
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
        Logger::send('amazon.watermark', ['settings' => $watermark]);
        if (isset($watermark['text']) || isset($watermark['imagePath'])) {
            $hash = isset($watermark['text']) ? md5($watermark['text']) : md5($watermark['imagePath']);
            $hash .= $watermark['size'] ?? 20;
            $hash .= 'v2';
            $watermarkKey = 'watermarks/' . $hash . '.jpg';
            $fileExists = $s3Client->doesObjectExist($this->s3['bucket'], $watermarkKey);
            if (!$fileExists) {
                $localPath = $this->generateWatermark($watermark);
                try {
                    $s3Client->putObject([
                        'Bucket' => $this->s3['bucket'],
                        'Key' => $watermarkKey,
                        'SourceFile' => $localPath,
                    ]);
                    if (file_exists($localPath)) {
                        @unlink($localPath);
                    }
                } catch (S3Exception $e) {
                    Logger::send('amazon.watermark', ['error' => $e->getMessage()]);
                    return null;
                }
            }
            return $watermarkKey;
        }

        return null;
    }

    /**
     * @param string $filePath
     * @param int $seconds
     * @return string
     */
    protected function getVideoFrame($filePath, $seconds)
    {
        $framePath =  PUBPATH . '/upload/' . md5($filePath) . '_frame_' . $seconds . '.jpg';
        if (file_exists($framePath)) {
            return $framePath;
        }
        $sourcePath = $filePath;
        $ext = FileHelper::getExt($filePath);
        if ($ext == 'gif') {
            $sourcePath = FileHelper::getLocalPath($filePath);
        }
        shell_exec(
            sprintf(
                'ffmpeg -ss %s -i %s -vframes 1 %s',
                escapeshellarg((string) TimeCode::fromSeconds($seconds)),
                escapeshellarg($sourcePath),
                escapeshellarg($framePath)
            )
        );
        if (!file_exists($framePath)) {
            return null;
        }
        return $framePath;
    }

    /**
     * @param string $filePath
     * @return float
     */
    public function getVideoDuration($filePath)
    {
        $ext = FileHelper::getExt($filePath);
        if ($ext == 'gif') {
            $localPath = FileHelper::getLocalPath($filePath);
            return (float) shell_exec(
                sprintf(
                    'exiftool -Duration -b %s',
                    escapeshellarg($localPath)
                )
            );
        }
        return (float) shell_exec(
            sprintf(
                "ffprobe -v error -select_streams v:0 -show_entries stream=duration %s | grep -i duration | sed 's/duration=//'",
                escapeshellarg($filePath)
            )
        );
    }
    
    /**
     * @param string $filePath
     * @return array
     */
    public function getVideoDimensions($filePath)
    {
        return explode(PHP_EOL, trim(shell_exec(sprintf("ffprobe -v error -select_streams v:0 -show_entries stream=width,height %s | grep -e width -e height | sed 's/width=//' | sed 's/height=//'", escapeshellarg($filePath)))));
    }
}
