<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\components\drivers;


use Converter\components\Config;
use Converter\components\ProtectUrl;
use Converter\components\storages\FileStorage;
use Converter\helpers\FileHelper;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;

abstract class Driver
{
    public $presetName;
    public $previews = [];
    public $thumbs = [];
    public $needProtect = false;
    public $needPreviewOnStart = true;
    
    /** @var FileStorage */
    protected $storage;
    protected $result = [];
    protected $error;
    
    public function __construct($presetName, $config = [])
    {
        $this->presetName = $presetName;
        foreach ($config as $name => $value) {
            $this->$name = is_string($value) ? trim($value) : $value;
        }
        $this->storage = FileStorage::loadByPreset($presetName);
    }
    
    /**
     * @return bool
     */
    public function hasStorage()
    {
        return $this->storage instanceof FileStorage;
    }
    
    /**
     * @return FileStorage|null
     */
    public function getStorage()
    {
        return $this->storage;
    }
    
    abstract public function processPhoto($filePath, $callback, $processId = null, $watermark = []);

    abstract public function processAudio($filePath, $callback, $processId = null, $watermark = []);
    
    abstract public function processVideo($filePath, $callback, $processId = null, $watermark = []);
    
    abstract public function createPhotoPreview($filePath, $watermark = []);

    /**
     * @param string $filePath
     * @return array
     */
    public function createPhotoThumbs($filePath)
    {
        if (empty($this->thumbs)) {
            return [];
        }
        $driver = Driver::loadByConfig($this->presetName, $this->thumbs);
        $driver->createPhotoPreview($filePath);
        $result = [];
        $protect = $this->needProtect ? new ProtectUrl() : null;
        foreach ($driver->getResult() as $index => $item) {
            $result[] = [
                'id'  => ++$index,
                'url' => $protect ? $protect->getProtectedUrl($item->url) : $item->url
            ];
        }
        return $result;
    }

    public function createThumbsFormVideo($filePath)
    {
        if (empty($this->thumbs)) {
            return false;
        }
        $duration = $this->getVideoDuration($filePath);

        if ($duration > $this->thumbs['maxCount']) {
            $maxCount = $this->thumbs['maxCount'];
            $step = floor($duration / $this->thumbs['maxCount']);
        } else {
            $maxCount = $duration;
            $step = 1;
        }

        $driver = Driver::loadByConfig($this->presetName, $this->thumbs);
        if ($duration == 0) {
            $tempPreviewFile = $this->getVideoFrame($filePath, $duration);
            if ($tempPreviewFile) {
                $driver->createPhotoPreview($tempPreviewFile);
            }
        } else {
            for ($i = 0; $i < $maxCount; $i++) {
                $tempPreviewFile = $this->getVideoFrame($filePath, $i * $step);
                if ($tempPreviewFile) {
                    $driver->createPhotoPreview($tempPreviewFile);
                }
            }
        }

        $result = [];
    
        $protect = $this->needProtect ? new ProtectUrl() : null;
        foreach ($driver->getResult() as $index => $item) {
            $result[] = [
                'id'  => ++$index,
                'url' => $protect ? $protect->getProtectedUrl($item->url) : $item->url
            ];
        }
        return $result;
    }
    
    public function createVideoPreview($filePath, $watermark = [], $seconds = 1)
    {
        $tempPreviewFile = $this->getVideoFrame($filePath, $seconds);
        if (!$tempPreviewFile) {
            return false;
        }
        $driver = Driver::loadByConfig($this->presetName, $this->previews);
        $driver->createPhotoPreview($tempPreviewFile, $watermark);
        foreach ($driver->getResult() as $result) {
            $this->result[] = $result;
        }
        return true;
    }

    /**
     * @param string $filePath
     * @return float
     */
    public function getVideoDuration($filePath)
    {
        return FileHelper::getVideoDuration($filePath);
    }
    
    abstract public function getStatus($processId);
    
    /**
     * @param $presetName
     * @param $config
     * @return Driver|null
     */
    public static function loadByConfig($presetName, $config)
    {
        $driverName = $config['driver'] ?? null;
        if ($driverName == null || !class_exists($driverName)) {
            return null;
        }
        unset($config['driver']);
        return new $driverName($presetName, $config);
    }
    
    /**
     * @return array
     */
    public function getResult()
    {
        return array_values($this->result);
    }
    
    /**
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }
    
    /**
     * @param $watermark
     * @return string
     */
    public function generateWatermark($watermark)
    {
        if (isset($watermark['imagePath'])) {
            $localPath = PUBPATH . '/upload/' . uniqid('watermark_') . basename($watermark['imagePath']);
            file_put_contents($localPath, file_get_contents($watermark['imagePath']));
            return $localPath;
        } elseif ($watermark['text']) {
            $localPath = PUBPATH . '/upload/' . uniqid('watermark_') . md5($watermark['text']) . '.png';
            $fontSize = (int) ($watermark['size'] ?? 20);
            $palette = new RGB();
            $imagine = new \Imagine\Gd\Imagine();
            $font = $imagine->font(PUBPATH . '/fonts/OpenSans-Regular.ttf', $fontSize, $palette->color('#808080'));
            $box = $font->box($watermark['text'], 3);
            $image = $imagine->create($box, $palette->color('#fff', 0));
            $image->draw()->text($watermark['text'], $font, new Point(0, 0));
            $image->save($localPath);
            return $localPath;
        }
    }
    
    protected function getVideoFrame($filePath, $seconds)
    {
        $localPath = str_replace(Config::getInstance()->get('baseUrl'), PUBPATH, $filePath);
        if (!file_exists($localPath)) {
            $localPath = PUBPATH . '/upload/' . md5($filePath) . basename($filePath);
            file_put_contents($localPath, file_get_contents($filePath));
        }
        $pathInfo = pathinfo($localPath);
        $fileName = $pathInfo['filename'] ?? md5($localPath);
        $tempPreviewFile = PUBPATH . '/upload/' . $fileName . $seconds . '_preview.jpg';
        $video = FFMpeg::create([
            'ffmpeg.binaries'  => exec('which ffmpeg'),
            'ffprobe.binaries' => exec('which ffprobe')
        ])->open($localPath);
        $video->frame(TimeCode::fromSeconds($seconds))
            ->save($tempPreviewFile);
        return file_exists($tempPreviewFile) ? $tempPreviewFile : false;
    }
}