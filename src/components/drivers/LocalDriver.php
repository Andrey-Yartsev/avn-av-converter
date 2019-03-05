<?php
/**
 * User: pel
 * Date: 25/10/2018
 */

namespace Converter\components\drivers;

use Converter\components\Config;
use Converter\response\ImageResponse;
use Imagine\Filter\Basic\Autorotate;
use Imagine\Filter\Basic\WebOptimization;
use Imagine\Gd\Imagine as GdImagine;
use Imagine\Gmagick\Imagine as GmagickImagine;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Imagine\Imagick\Imagine as ImagickImagine;

class LocalDriver extends Driver
{
    public $thumbSizes = [];
    public $withSource = false;
    /** @var GdImagine|GmagickImagine|ImagickImagine */
    protected $imagine;

    public function __construct($presetName, array $config = [])
    {
        parent::__construct($presetName, $config);
        $engine = $config['engine'] ?? 'gd';
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

    public function createPhotoPreview($filePath, $watermark = [])
    {
        $localPath = str_replace(Config::getInstance()->get('baseUrl'), PUBPATH, $filePath);
        if (!file_exists($localPath)) {
            $localPath = PUBPATH . '/upload/' . md5($filePath) . basename($filePath);
            file_put_contents($localPath, file_get_contents($filePath));
        }
        $size = current($this->thumbSizes);
        $this->resizeImage($localPath, $size, $watermark);
        return true;
    }

    public function createVideoPreview($filePath, $watermark = [])
    {
        throw new \Exception('Not implemented ' . __CLASS__ . ' ' . __METHOD__ . ' ' . json_encode(func_get_args()));
    }

    public function processAudio($filePath, $callback, $processId = null, $watermark = [])
    {
        throw new \Exception('Not implemented ' . __CLASS__ . ' ' . __METHOD__ . ' ' . json_encode(func_get_args()));
    }

    public function getStatus($processId)
    {
        throw new \Exception('Not implemented ' . __CLASS__ . ' ' . __METHOD__ . ' ' . json_encode(func_get_args()));
    }

    public function processPhoto($filePath, $callback, $processId = null, $watermark = [])
    {
        $localPath = str_replace(Config::getInstance()->get('baseUrl'), PUBPATH, $filePath);
        if (!file_exists($localPath)) {
            $localPath = PUBPATH . '/upload/' . md5($filePath) . basename($filePath);
            file_put_contents($localPath, file_get_contents($filePath));
        }

        foreach ($this->thumbSizes as $size) {
            $this->resizeImage($localPath, $size, $watermark);
        }
        $needRemoved = true;
        if ($this->withSource) {
            if ($this->storage) {
                $url = $this->storage->upload($localPath, $this->storage->generatePath($filePath));
                $needRemoved = true;
            } else {
                $url = str_replace(PUBPATH, Config::getInstance()->get('baseUrl'), $localPath);
                $needRemoved = false;
            }
            $fileSize = filesize($localPath);
            list($width, $height) = getimagesize($localPath);

            $this->result[] = new ImageResponse([
                'name'   => 'source',
                'size'   => $fileSize,
                'width'  => $width,
                'height' => $height,
                'url'    => $url
            ]);
        }
        if ($needRemoved) {
            @unlink($localPath);
        }
        return $processId;
    }

    public function processVideo($filePath, $callback, $processId = null, $watermark = [])
    {
        throw new \Exception('Not implemented ' . __CLASS__ . ' ' . __METHOD__ . ' ' . json_encode(func_get_args()));
    }
    
    /**
     * @param $localPath
     * @return AbstractImage
     */
    protected function fixedOrientation($localPath)
    {
        $image = $this->imagine->open($localPath);
        $filter = new Autorotate();
        return $filter->apply($image);
    }

    /**
     * @param string $filePath
     * @param array $size
     * @param array $watermark
     * @return bool
     */
    protected function resizeImage($filePath, $size, $watermark = [])
    {
        $width = $size['width'] ?? null;
        $height = $size['height'] ?? null;
        $blur = $size['blur'] ?? null;
        $name = $size['name'] ?? null;
        $maxSize = $size['maxSize'] ?? null;
        $image = $this->fixedOrientation($filePath);

        if ($maxSize) {
            $this->resizeAdaptive($image, $maxSize);
        } elseif ($height && $height == $width) {
            $this->crop($image, $height);
        }

        if ($blur) {
            $image->effects()->blur($blur);
        }

        $imageSize = $image->getSize();
        $fileName = $imageSize->getWidth() . 'x' . $imageSize->getHeight() . '_' . urlencode(pathinfo($filePath, PATHINFO_FILENAME)) . '.jpg';
        $savedPath = '/upload/' . $fileName;

        if (!empty($watermark) && is_array($watermark)) {
            $image = $this->watermark($image, $watermark);
        }

        $webFilter = new WebOptimization(PUBPATH . $savedPath);
        $webFilter->apply($image);
        
        $fileSize = filesize(PUBPATH . $savedPath);
        if ($this->storage) {
            $url = $this->storage->upload(PUBPATH . $savedPath, $this->storage->generatePath($fileName));
            @unlink(PUBPATH . $savedPath);
        } else {
            $url = Config::getInstance()->get('baseUrl') . $savedPath;
        }
        $this->result[] = new ImageResponse([
            'name'   => $name,
            'size'   => $fileSize,
            'width'  => $imageSize->getWidth(),
            'height' => $imageSize->getHeight(),
            'url'    => $url
        ]);
        return true;
    }

    /**
     * @param AbstractImage $image
     * @param int $maxSize
     * @return AbstractImage
     */
    protected function resizeAdaptive($image, $maxSize)
    {
        $portraitRatio = 4 / 3;
        $landscapeRatio = 16 / 9;
        $imageSize = $image->getSize();
        $imageHeight = $imageSize->getHeight();
        $imageWidth = $imageSize->getWidth();

        if ($imageHeight >= $imageWidth) {
            $height = $imageHeight;
            if ($imageHeight / $imageWidth > $portraitRatio) {
                $height = $imageWidth * $portraitRatio;
                $sizeBox = new Box($imageWidth, $height);
                $cropPoint = new Point(0, ceil(($imageHeight - $height) / 2));
                $image->crop($cropPoint, $sizeBox);
            }
            if ($imageWidth > $maxSize) {
                $sizeBox = new Box($maxSize, ceil($height / ($imageWidth / $maxSize)));
                $image->resize($sizeBox);
            }
        } else {
            $width = $imageWidth;
            if ($imageWidth / $imageHeight > $landscapeRatio) {
                $width = $imageHeight * $landscapeRatio;
                $sizeBox = new Box($width, $imageHeight);
                $cropPoint = new Point(ceil(($imageWidth - $width) / 2), 0);
                $image->crop($cropPoint, $sizeBox);
            }
            if ($width > $maxSize) {
                $sizeBox = new Box($maxSize, ceil($imageHeight / ($width / $maxSize)));
                $image->resize($sizeBox);
            }
        }

        return $image;
    }

    /**
     * @param AbstractImage $image
     * @param int $size
     * @return AbstractImage
     */
    protected function crop($image, $size)
    {
        $imageSize = $image->getSize();
        $imageHeight = $imageSize->getHeight();
        $imageWidth = $imageSize->getWidth();
        $width = $height = $size;
        if ($imageWidth < $imageHeight) {
            $height = ceil($imageHeight / ($imageWidth / $width));
        } elseif ($imageWidth > $imageHeight) {
            $width = ceil($imageWidth / ($imageHeight / $height));
        }
        $sizeBox = new Box($width, $height);
        $image->resize($sizeBox);

        $cropPoint = new Point(0, 0);
        $sizeBox = new Box($size, $size);
        $image->crop($cropPoint, $sizeBox);

        return $image;
    }
    
    /**
     * @param ImageInterface $image
     * @param array $watermarkSettings
     * @return ImageInterface
     */
    protected function watermark($image, $watermarkSettings = [])
    {
        if (empty($watermarkSettings['text'])) {
            return $image;
        }
        $fontSize = $watermarkSettings['size'] ?? 20;
        $palette = new RGB();
        $font = $this->imagine->font(PUBPATH . '/fonts/OpenSans-Regular.ttf', $fontSize, $palette->color('#808080'));
        $image->draw()->text($watermarkSettings['text'], $font, new Point(10, 10));
        return $image;
    }
}
