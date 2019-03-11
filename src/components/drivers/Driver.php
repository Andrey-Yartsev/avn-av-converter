<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\components\drivers;


use Converter\components\Config;
use Converter\components\storages\FileStorage;
use Imagine\Image\Box;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Imagine\Imagick\Image;
use Imagine\Imagick\Imagine;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;

abstract class Driver
{
    public $presetName;
    public $previews = [];
    
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
    
    public function createVideoPreview($filePath, $watermark = [])
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
            $fontSize = $watermark['size'] ?? 20;
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
}