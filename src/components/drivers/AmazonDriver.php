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
use Converter\response\StatusResponse;
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
            'filePath' => $filePath,
            'watermark' => $watermark
        ]));
        return $processId;
    }

    public function processAudio($filePath, $callback, $processId = null, $watermark = [])
    {
        throw new \Exception('Not implemented ' . __CLASS__ . ' ' . __METHOD__ . ' ' . json_encode(func_get_args()));
    }

    public function processPhoto($filePath, $callback, $processId = null, $watermark = [])
    {
        throw new \Exception('Not implemented ' . __CLASS__ . ' ' . __METHOD__ . ' ' . json_encode(func_get_args()));
    }

    public function getStatus($processId)
    {
        Logger::send('converter.cc.status', [
            'id'      => $processId,
            'percent' => 7
        ]);
        return new StatusResponse([
            'id'      => $processId,
            'percent' => 7
        ]);
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
                $this->error = $jobData['Output']['StatusDetail'];
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
     * @param array $watermark
     * @return bool
     */
    public function createJob($filePath, $callback, $processId, $watermark = [])
    {
        $pathParts = pathinfo($filePath);
        $keyName = 'temp_video/' . parse_url($filePath, PHP_URL_HOST) . '/' . date('Y_m_d') . '/' . uniqid('', true) . '.' . $pathParts['extension'];

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

        $dir = date('Y_m_d') . '/' . substr($processId, 0, 2) . '/' . substr($processId, 0, 3) . '/' . $processId;

        $watermarkKey = $this->getWatermark($s3Client, $watermark);
        Logger::send('amazon.watermark', ['key' => $watermarkKey]);
        $transcoderClient = $this->getTranscoderClient();
        try {
            $outputSettings = [
                'Key'      => $dir . '.mp4',
                'Rotate'   => 'auto',
                'PresetId' => $this->transcoder['preset'],
            ];
            if ($watermarkKey) {
                $outputSettings['Watermarks'][] = [
                    'InputKey' => $watermarkKey,
                    'PresetWatermarkId' => 'BottomRight'
                ];
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
                'Outputs' => [
                    $outputSettings,
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

    /**
     * @param S3Client $s3Client
     * @param array $watermark
     * @return null|string
     */
    protected function getWatermark($s3Client, $watermark = [])
    {
        Logger::send('amazon.watermark', ['settings' => $watermark]);
        if (isset($watermark['text'])) {
            $hash = md5($watermark['text']);
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
}
