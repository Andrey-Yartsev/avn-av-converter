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
use Converter\components\Redis;
use Converter\helpers\FileHelper;
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
    public $previews = [];
    
    public function __construct($presetName, $config = [])
    {
        Logger::send('converter.cc.init');
        parent::__construct($presetName, $config);
        $this->client = new Api($this->token);
    }
    
    public function createPhotoPreview($filePath)
    {
        throw new \Exception('Not implemented');
    }
    
    public function getStatus($processId)
    {
        $options = Redis::getInstance()->get('cc:' . $processId);
        if ($options) {
            $options = json_decode($options, true);
            $process = new Process($this->client, $options['url']);
            $process->refresh();
            if ($process->percent) {
                Logger::send('converter.cc.status', [
                    'id'      => $processId,
                    'percent' => $process->percent,
                    'step'    => $process->step,
                    'message' => $process->message
                ]);
                return new StatusResponse([
                    'id'      => $processId,
                    'percent' => $process->percent,
                    'step'    => $process->step,
                    'message' => $process->message
                ]);
            }
        }
        Logger::send('converter.cc.status', [
            'id'      => $processId,
            'percent' => 0
        ]);
        return new StatusResponse([
            'id'      => $processId,
            'percent' => 0
        ]);
    }
    
    public function createVideoPreview($filePath)
    {
        $localPath = str_replace(Config::getInstance()->get('baseUrl'), PUBPATH, $filePath);
        if (!file_exists($localPath)) {
            $localPath = PUBPATH . '/upload/' . md5($filePath) . basename($filePath);
            file_put_contents($localPath, file_get_contents($filePath));
        }
        $pathInfo = pathinfo($localPath);
        $fileName = $pathInfo['filename'] ?? md5($localPath);
        $tempPreviewFile = PUBPATH . '/upload/' . $fileName . '_preview.jpg';
        $video = FFMpeg::create([
            'ffmpeg.binaries'  => exec('which ffmpeg'),
            'ffprobe.binaries' => exec('which ffprobe')
        ])->open($localPath);
        $video->frame(TimeCode::fromSeconds(1))
            ->save($tempPreviewFile);
        $driver = Driver::loadByConfig($this->presetName, $this->previews);
        $driver->createPhotoPreview($tempPreviewFile);
        foreach ($driver->getResult() as $result) {
            $this->result[] = $result;
        }
        return true;
    }
    
    public function saveVideo($url)
    {
        $process = new Process($this->client, $url);
        $output = $process->refresh()->output;
        Logger::send('converter.cc.callback.output', [
            'url'   => $url,
            'debug' => json_encode($output)
        ]);
        $url = $output->url;
        if ($this->withOutSave) {
            $this->result[] = new VideoResponse([
                'name' => 'source',
                'url'  => $url
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
        $firstStream = FFProbe::create([
            'ffmpeg.binaries'  => exec('which ffmpeg'),
            'ffprobe.binaries' => exec('which ffprobe')
        ])->streams($localSavedFile)
            ->videos()
            ->first();
        $dimension = $firstStream->getDimensions();
        $this->result[] = new VideoResponse([
            'name'     => 'source',
            'url'      => $url,
            'width'    => $dimension->getWidth(),
            'height'   => $dimension->getHeight(),
            'duration' => ceil($firstStream->get('duration')),
            'size'     => $output->size
        ]);
        Logger::send('converter.cc.makePreview', [
            'previewsConfig' => $this->previews
        ]);
        if ($this->previews) {
            $this->makePreview($localSavedFile);
        }
        if ($this->hasStorage()) {
            @unlink($localSavedFile);
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
        $process = $this->client->createProcess([
            'inputformat'  => $pathParts['extension'],
            'outputformat' => $this->outputFormat,
        ]);
        $process->start([
            'outputformat'     => $this->outputFormat,
            'converteroptions' => [
                'command' => $this->command,
            ],
            'input'            => 'download',
            'file'             => $filePath,
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
            'processId'  => $processId,
            'url'        => $process->refresh()->url
        ]);
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
}