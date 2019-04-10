<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\components\drivers;


use Converter\components\Config;
use Converter\components\storages\FileStorage;
use Converter\helpers\FileHelper;
use FFMpeg\FFProbe;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;

abstract class Driver
{
    public $presetName;
    public $previews = [];
    public $thumbs = [];
    
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
    
    public function createThumbsFormVideo($filePath)
    {
        if (empty($this->thumbs)) {
            return false;
        }
        $duration = FileHelper::getVideoDuration($filePath);
        $step = floor($duration / $this->thumbs['maxCount']);
        $driver = Driver::loadByConfig($this->presetName, $this->thumbs);
        for ($i = 1; $i <= $this->thumbs['maxCount']; $i++) {
            $tempPreviewFile = $this->getVideoFrame($filePath, $i * $step);
            $driver->createPhotoPreview($tempPreviewFile);
        }
        $result = [];
        foreach ($driver->getResult() as $index => $item) {
            $result[] = [
                'index' => $index,
                'url' => $item->url
            ];
        }
        return $result;
    }
    
    public function createVideoPreview($filePath, $watermark = [], $seconds = 1)
    {
        $tempPreviewFile = $this->getVideoFrame($filePath, $seconds);
        $driver = Driver::loadByConfig($this->presetName, $this->previews);
        $driver->createPhotoPreview($tempPreviewFile, $watermark);
        foreach ($driver->getResult() as $result) {
            $this->result[] = $result;
        }
        return true;
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
        return $this->result;
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
            $fontSize = (int) $watermark['size'];
            if (!$fontSize) {
                $fontSize = 20;
            }
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
        $tempPreviewFile = PUBPATH . '/upload/' . $fileName . '_preview.jpg';
        $video = FFMpeg::create([
            'ffmpeg.binaries'  => exec('which ffmpeg'),
            'ffprobe.binaries' => exec('which ffprobe')
        ])->open($localPath);
        $video->frame(TimeCode::fromSeconds($seconds))
            ->save($tempPreviewFile);
        return $tempPreviewFile;
    }
}