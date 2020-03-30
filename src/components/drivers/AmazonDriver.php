<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\components\drivers;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Converter\components\Logger;
use Converter\components\Redis;
use Converter\helpers\FileHelper;
use Converter\response\StatusResponse;
use FFMpeg\Coordinate\TimeCode;

abstract class AmazonDriver extends Driver
{
    public $url;
    public $s3;

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
    
    abstract public function readJob($jobId, $process);
    
    abstract public function createJob($process);
    
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
            $watermarkKey = 'watermarks/' . $hash . '.png';
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
