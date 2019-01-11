<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\components\drivers;


use Converter\components\storages\FileStorage;
use Imagine\Image\Box;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Imagine\Imagick\Image;
use Imagine\Imagick\Imagine;

abstract class Driver
{
    public $presetName;
    
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
    
    abstract public function createPhotoPreview($filePath);
    
    abstract public function createVideoPreview($filePath);
    
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
        if (isset($watermark['image'])) {
            $localPath = PUBPATH . '/upload/' . uniqid('watermark_') . basename($watermark['image']);
            file_put_contents($localPath, file_get_contents($watermark['image']));
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