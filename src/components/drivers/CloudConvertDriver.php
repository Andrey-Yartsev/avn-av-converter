<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\components\drivers;


use CloudConvert\Api;
use CloudConvert\Process;
use Converter\components\Config;
use Converter\components\Logger;
use Converter\components\Process as CProcess;
use Converter\components\Redis;
use Converter\helpers\CliHelper;
use Converter\helpers\FileHelper;
use Converter\response\AudioResponse;
use Converter\response\StatusResponse;
use Converter\response\VideoResponse;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;

class CloudConvertDriver extends Driver
{
    /** @var Api */
    protected $client;
    public $token;
    public $outputFormat;
    public $command;
    public $withOutSave = false;

    public function __construct($presetName, $config = [])
    {
        Logger::send('converter.cc.init');
        parent::__construct($presetName, $config);
        $this->client = new Api($this->token);
    }

    public function createPhotoPreview($filePath, $watermark = [])
    {
        throw new \Exception('Not implemented ' . __CLASS__ . ' ' . __METHOD__ . ' ' . json_encode(func_get_args()));
    }
    
    /**
     * @param CProcess $process
     * @return StatusResponse
     */
    public function getStatus($process)
    {
        $processId = $process->getId();
        $options = Redis::getInstance()->get('cc:' . $processId);
        if ($options) {
            $options = json_decode($options, true);
            try {
                $process = new Process($this->client, $options['url']);
                $process->refresh();
                if ($process->percent) {
                    Logger::send('converter.cc.status', [
                        'processId' => $processId,
                        'percent'   => $process->percent,
                        'step'      => $process->step,
                        'message'   => $process->message
                    ]);
                    return new StatusResponse([
                        'id'      => $processId,
                        'percent' => $process->percent,
                        'step'    => $process->step,
                        'message' => $process->message
                    ]);
                }
            } catch (\CloudConvert\Exceptions\InvalidParameterException |
                \CloudConvert\Exceptions\ApiException |
                \GuzzleHttp\Exception\GuzzleException $e) {
                Logger::send('converter.cc.error', [
                    'error' => $e->getMessage(),
                    'loc' => 'ClodConvertDriver:getStatus()'
                ]);
            }
        }
        Logger::send('converter.cc.status', [
            'processId' => $processId,
            'percent'   => 0
        ]);
        return new StatusResponse([
            'id'      => $processId,
            'percent' => 0
        ]);
    }

    public function saveAudio($url)
    {
        try {
            $process = new Process($this->client, $url);
            $output = $process->refresh()->output;
            Logger::send('converter.cc.callback.output', [
                'url'   => $url,
                'debug' => json_encode($output)
            ]);
        } catch (\Exception $e) {
            Logger::send('converter.cc.error', [
                'error' => $e->getMessage(),
                'loc' => 'ClodConvertDriver:saveAudio()'
            ]);
            return false;
        }

        if (empty($output->url)) {
            Logger::send('converter.cc.error', [
                'error' => 'Empty $url',
                'loc' => 'ClodConvertDriver:saveAudio()'
            ]);
            return false;
        }
        $url = $output->url;
        if ($this->withOutSave) {
            $this->result[] = new AudioResponse([
                'name' => 'source',
                'url'  => $url,
                'size' => $output->size ?? 0,
            ]);
            return true;
        }
        $hash = md5($output->filename);
        $localSavedFile = PUBPATH . '/upload/' . $hash . '.' . $output->ext;
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        file_put_contents($localSavedFile, file_get_contents($url));
        if ($this->hasStorage()) {
            $storage = $this->getStorage();
            $savedPath = 'files/' . substr($hash, 0, 1) . '/' . substr($hash, 0, 2) . '/' . $hash;
            Logger::send('converter.cc.callback.upload', [
                'url'       => $url,
                'savedPath' => $savedPath . '/' . $hash . '.' . $output->ext
            ]);
            $url = $storage->upload($localSavedFile, $savedPath . '/' . $hash . '.' . $output->ext);
            Logger::send('converter.cc.callback.uploadFinished', [
                'url' => $url
            ]);
        } else {
            $url = str_replace(PUBPATH, Config::getInstance()->get('baseUrl'), $localSavedFile);
        }

        $this->result[] = new AudioResponse([
            'name'     => 'source',
            'url'      => $url,
            'size'     => $output->size ?? 0,
            'duration' => FileHelper::getAudioDuration($localSavedFile),
        ]);
        if ($this->hasStorage()) {
            CliHelper::run('worker:deletion', [$localSavedFile]);
        }
        return true;
    }

    public function saveVideo($url)
    {
        try {
            $process = new Process($this->client, $url);
            $output = $process->refresh()->output;
            Logger::send('converter.cc.callback.output', [
                'url'   => $url,
                'debug' => json_encode($output)
            ]);
        } catch (\GuzzleHttp\Exception\GuzzleException | \Exception $e) {
            Logger::send('converter.cc.error', [
                'error' => $e->getMessage(),
                'loc' => 'ClodConvertDriver:saveVideo()'
            ]);
            return false;
        }

        if (empty($output->url)) {
            Logger::send('converter.cc.error', [
                'error' => 'Empty $url',
                'loc' => 'ClodConvertDriver:saveVideo()'
            ]);
            return false;
        }
        $url = $output->url;
        if ($this->withOutSave) {
            $this->result[] = new VideoResponse([
                'name' => 'source',
                'url'  => $url,
                'size' => $output->size ?? 0,
            ]);
            return true;
        }
        $hash = md5($output->filename);
        $localSavedFile = PUBPATH . '/upload/' . $hash . '.' . $output->ext;
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        file_put_contents($localSavedFile, file_get_contents($url));
        if ($this->hasStorage()) {
            $storage = $this->getStorage();
            $savedPath = 'files/' . substr($hash, 0, 1) . '/' . substr($hash, 0, 2) . '/' . $hash;
            Logger::send('converter.cc.callback.upload', [
                'url'       => $url,
                'savedPath' => $savedPath . '/' . $hash . '.' . $output->ext
            ]);
            $url = $storage->upload($localSavedFile, $savedPath . '/' . $hash . '.' . $output->ext);
            Logger::send('converter.cc.callback.uploadFinished', [
                'url' => $url
            ]);
        } else {
            $url = str_replace(PUBPATH, Config::getInstance()->get('baseUrl'), $localSavedFile);
        }

        $duration = FileHelper::getVideoDuration($localSavedFile);
        list($width, $height) = FileHelper::getVideoDimensions($localSavedFile);

        $this->result[] = new VideoResponse([
            'name'     => 'source',
            'url'      => $url,
            'width'    => $width,
            'height'   => $height,
            'duration' => $duration,
            'size'     => $output->size ?? 0,
        ]);
        Logger::send('converter.cc.makePreview', [
            'previewsConfig' => $this->previews
        ]);
        if ($this->previews) {
            $this->makePreview($localSavedFile);
        }
        if ($this->hasStorage()) {
            CliHelper::run('worker:deletion', [$localSavedFile]);
        }
        return true;
    }

    public function makePreview($localSavedFile)
    {
        $pathInfo = pathinfo($localSavedFile);
        $fileName = $pathInfo['filename'] ?? md5($localSavedFile);
        $tempPreviewFile = PUBPATH . '/upload/' . $fileName . '_preview.jpg';
        $video = FFMpeg::create([
            'ffmpeg.binaries'  => exec('which ffmpeg'),
            'ffprobe.binaries' => exec('which ffprobe')
        ])->open($localSavedFile);
        $video->frame(TimeCode::fromSeconds(1))
            ->save($tempPreviewFile);
        $driver = Driver::loadByConfig($this->presetName, $this->previews);
        $driver->processPhoto($tempPreviewFile, '');
        Logger::send('converter.cc.makePreview', [
            'previewsResult' => $driver->getResult()
        ]);
        foreach ($driver->getResult() as $result) {
            $this->result[] = $result;
        }
    }

    /**
     * @param $filePath
     * @param $callback
     * @param null $processId
     * @return null|object
     * @throws \CloudConvert\Exceptions\ApiException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function processVideo($filePath, $callback, $processId = null, $watermark = [])
    {
        $pathParts = pathinfo($filePath);
        Logger::send('process', ['processId' => $processId, 'step' => 'CloudConverter driver', 'data' => ['status' => 'init']]);
        $process = $this->client->createProcess([
            'inputformat'  => $pathParts['extension'],
            'outputformat' => $this->outputFormat,
        ]);
        $watermarkString = '';
        if (isset($watermark['text'])) {
            Logger::send('process', ['processId' => $processId, 'step' => 'Set watermark', 'data' => ['status' => 'success']]);
            $fontSize = $watermark['size'] ?? 20;
            $watermarkString = '-vf drawtext="fontfile=/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf:text=\'' . addslashes($watermark['text']). '\':x=w-tw-10:y=h-th-10:fontsize=' . $fontSize . ':fontcolor=gray"';
        }
        Logger::send('process', ['processId' => $processId, 'step' => 'Start process', 'data' => ['status' => 'success']]);
        $process->start([
            'outputformat'     => $this->outputFormat,
            'converteroptions' => [
                'command' => strtr($this->command, [
                    '{watermark}' => $watermarkString
                ]),
            ],
            'input'            => 'download',
            'file'             => FileHelper::getSignedUrl($filePath),
            'callback'         => Config::getInstance()->get('baseUrl') . '/video/cloudconvert/callback?processId=' . $processId
        ]);
        $processId = $processId ? $processId : $process->id;
        Redis::getInstance()->set('cc:' . $processId, json_encode([
            'callback'   => $callback,
            'presetName' => $this->presetName,
            'fileType'   => FileHelper::TYPE_VIDEO,
            'url'        => $process->refresh()->url
        ]));
        Logger::send('converter.cc.sendToProvider', [
            'file'       => $filePath,
            'callback'   => $callback,
            'presetName' => $this->presetName,
            'processId'         => $processId,
            'url'        => $process->refresh()->url
        ]);
        return $processId;
    }

    /**
     * @param $filePath
     * @param $callback
     * @param null $processId
     * @return null|object
     * @throws \CloudConvert\Exceptions\ApiException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function processAudio($filePath, $callback, $processId = null, $watermark = [])
    {
        $pathParts = pathinfo($filePath);
        $process = $this->client->createProcess([
            'inputformat'  => $pathParts['extension'],
            'outputformat' => $this->outputFormat,
        ]);
        $process->start([
            'outputformat'     => $this->outputFormat,
            'converteroptions' => [],
            'input'            => 'download',
            'file'             => FileHelper::getSignedUrl($filePath),
            'callback'         => Config::getInstance()->get('baseUrl') . '/video/cloudconvert/callback?processId=' . $processId
        ]);
        $processId = $processId ? $processId : $process->id;
        Redis::getInstance()->set('cc:' . $processId, json_encode([
            'callback'   => $callback,
            'presetName' => $this->presetName,
            'fileType'   => FileHelper::TYPE_AUDIO,
            'url'        => $process->refresh()->url
        ]));
        Logger::send('converter.cc.sendToProvider', [
            'file'       => $filePath,
            'callback'   => $callback,
            'presetName' => $this->presetName,
            'processId'  => $processId,
            'url'        => $process->refresh()->url
        ]);
        return $processId;
    }

    public function processPhoto($filePath, $callback, $processId = null, $watermark = [])
    {
        throw new \Exception('Not implemented ' . __CLASS__ . ' ' . __METHOD__ . ' ' . json_encode(func_get_args()));
    }
}
