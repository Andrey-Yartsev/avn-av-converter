<?php
/**
 * User: pel
 * Date: 25/10/2018
 */

namespace Converter\components\drivers;

use Converter\components\Config;
use Converter\response\ImageResponse;
use Imagine\Gd\Imagine as GdImagine;
use Imagine\Gmagick\Imagine as GmagickImagine;
use Imagine\Image\AbstractImagine;
use Imagine\Image\Box;
use Imagine\Imagick\Imagine as ImagickImagine;

class LocalDriver extends Driver
{
    public $thumbSizes = [];
    public $withSource = false;
    /** @var AbstractImagine */
    protected $image;
    
    public function __construct($presetName, array $config = [])
    {
        parent::__construct($presetName, $config);
        $engine = $config['driver'] ?? 'gd';
        switch ($engine) {
            case 'gd':
                $this->imagine = new GdImagine();
                break;
            case 'gmagick':
                $this->imagine = new GmagickImagine();
                break;
            case 'imagick':
                $this->imagine = new ImagickImagine();
                break;
            default:
                throw new \Exception();
        }
    }
    
    public function processAudio($filePath, $callback, $processId = null)
    {
        throw new \Exception('Not implemented');
    }
    
    public function processPhoto($filePath, $callback, $processId = null)
    {
        foreach ($this->thumbSizes as $size) {
            $width = $size['width'] ?? null;
            $height = $size['height'] ?? null;
            $blur = $size['blur'] ?? null;
            $name = $size['name'] ?? null;
            $this->resizeImage($filePath, $width, $height, $name, $blur);
        }
        if ($this->storage) {
            $url = $this->storage->upload($filePath, $this->storage->generatePath($filePath));
        } else {
            $url = $filePath;
        }
        if ($this->withSource) {
            $localPath = str_replace(Config::getInstance()->get('baseUrl'), PUBPATH, $url);
            $needRemoved = false;
            if (!file_exists($localPath)) {
                file_put_contents($localPath, file_get_contents($url));
                $needRemoved = true;
            }
    
            $fileSize = filesize($localPath);
            list($width, $height) = getimagesize($localPath);
            
            if ($needRemoved) {
                @unlink($localPath);
            }
            $this->result[] = new ImageResponse([
                'name' => 'source',
                'size' => $fileSize,
                'width' => $width,
                'height' => $height,
                'url' => $url
            ]);
        }
        
        return $processId;
    }
    
    public function processVideo($filePath, $callback, $processId = null)
    {
        throw new \Exception('Not implemented');
    }
    
    /**
     * @param $filePath
     * @param $width
     * @param $height
     * @param null $name
     * @param null $blur
     * @return bool
     */
    protected function resizeImage($filePath, $width, $height, $name = null, $blur = null)
    {
        $image = $this->imagine->open($filePath);
        if ($width && empty($height)) {
            $imageSize = $image->getSize();
            $height = ceil($imageSize->getHeight() / ($imageSize->getWidth() / $width));
        } elseif ($height && empty($width)) {
            $imageSize = $image->getSize();
            $width = ceil($imageSize->getWidth() / ($imageSize->getHeight() / $height));
        } elseif (empty($height) && empty($width)) {
            return false;
        }
        $resizeBox = new Box($width, $height);
        $image->resize($resizeBox);
        if ($blur)  {
            $image->effects()->blur($blur);
        }
    
        $fileName = $width . 'x' . $height . '_' . urlencode(basename($filePath));
        $savedPath = '/upload/' . $fileName;
        $image->save(PUBPATH . $savedPath);
        $fileSize = filesize(PUBPATH . $savedPath);
        if ($this->storage) {
            $url = $this->storage->upload(PUBPATH . $savedPath, $this->storage->generatePath($fileName));
            @unlink(PUBPATH . $savedPath);
        } else {
            $url = Config::getInstance()->get('baseUrl') . $savedPath;
        }
        $this->result[] = new ImageResponse([
            'name' => $name,
            'size' => $fileSize,
            'width' => $width,
            'height' => $height,
            'url' => $url
        ]);
        return true;
    }
}