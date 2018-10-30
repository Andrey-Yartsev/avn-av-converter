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
    public $previews = [];
    
    public function __construct($presetName, $config = [])
    {
        Logger::send('converter.cc.init');
        parent::__construct($presetName, $config);
        $this->client = new Api($this->token);
    }
    
    public function saveVideo($url)
    {
        $process = new Process($this->client, $url);
        $output = $process->refresh()->output;
        Logger::send('converter.cc.callback.output', [
            'url'       => $url,
            'debug' => json_encode($output)
        ]);
        $url = $output->url;
        $hash = md5($output->filename);
        $localSavedFile = PUBPATH . '/upload/' . $hash . '.' . $output->ext;
        if (strpos($url, '//') === 0 ) {
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
            $url = str_replace(PUBPATH, Config::getInstance()->get('baseUri'), $localSavedFile);
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
        @unlink($tempPreviewFile);
    }
    
    /**
     * @param $filePath
     * @param $callback
     * @param null $processId
     * @return null|object
     * @throws \CloudConvert\Exceptions\ApiException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function processVideo($filePath, $callback, $processId = null)
    {
        $pathParts = pathinfo($filePath);
        $process = $this->client->createProcess( [
            'inputformat' => $pathParts['extension'],
            'outputformat' => $this->outputFormat,
        ]);
        $process->start([
            'outputformat' => $this->outputFormat,
            'converteroptions' => [
                'command' => $this->command,
            ],
            'input' => 'download',
            'file' => $filePath,
            'callback' => Config::getInstance()->get('baseUrl') . '/video/cloudconvert/callback?processId=' . $processId
        ]);
        $processId = $processId ? $processId : $process->id;
        Redis::getInstance()->set('cc:' . $processId, json_encode([
            'callback' => $callback,
            'presetName' => $this->presetName,
            'fileType' => FileHelper::TYPE_VIDEO
        ]));
        Logger::send('converter.cc.sendToProvider', [
            'callback' => $callback,
            'presetName' => $this->presetName,
            'processId' => $processId,
        ]);
        return $processId;
    }
    
    public function processAudio($filePath, $callback, $processId = null)
    {
        throw new \Exception('Not implemented');
    }
    
    public function processPhoto($filePath, $callback, $processId = null)
    {
        throw new \Exception('Not implemented');
    }
}