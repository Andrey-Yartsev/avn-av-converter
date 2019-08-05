<?php
/**
 * User: pel
 * Date: 25/10/2018
 */

namespace Converter\components\drivers;

use Converter\components\Config;
use Converter\components\Logger;
use Converter\response\ImageResponse;
use Imagine\Filter\Basic\WebOptimization;
use Imagine\Gd\Imagine as GdImagine;
use Imagine\Gmagick\Imagine as GmagickImagine;
use Imagine\Image\AbstractImage;
use Imagine\Image\Box;
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
        Logger::send('create.preview', ['filePath' => $filePath, 'step' => 'Make photo preview']);
        $localPath = str_replace(Config::getInstance()->get('baseUrl'), PUBPATH, $filePath);
        if (!file_exists($localPath)) {
            Logger::send('create.preview', ['filePath' => $filePath, 'step' => 'Download source']);
            $localPath = PUBPATH . '/upload/' . md5($filePath) . basename($filePath);
            file_put_contents($localPath, file_get_contents($filePath));
        }
        Logger::send('create.preview', ['filePath' => $filePath, 'step' => 'fixedOrientation()']);
        $this->fixedOrientation($localPath);
        foreach ($this->thumbSizes as $size) {
            Logger::send('create.preview', ['filePath' => $filePath, 'step' => 'Make photo size: ' . $size['name'] ?? null]);
            $this->resizeImage($localPath, $size, $watermark);
            Logger::send('create.preview', ['filePath' => $filePath, 'step' => 'End photo size: ' . $size['name'] ?? null]);
        }
        return true;
    }

    public function createVideoPreview($filePath, $watermark = [], $seconds = 1)
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
            Logger::send('process', ['processId' => $processId, 'step' => 'Make photo size: ' . $size['name'] ?? null]);
            $this->resizeImage($localPath, $size, $watermark);
        }

        if ($this->withSource) {
            Logger::send('process', ['processId' => $processId, 'step' => 'Process source']);
            $this->fixedOrientation($localPath);
            $this->setWatermark($localPath, $watermark);
            list($width, $height) = getimagesize($localPath);
            if ($this->storage) {
                Logger::send('process', ['processId' => $processId, 'step' => 'Upload to storage']);
                $url = $this->storage->upload($localPath, $this->storage->generatePath($filePath));
            } else {
                Logger::send('process', ['processId' => $processId, 'step' => 'generate url for download']);
                $url = str_replace(PUBPATH, Config::getInstance()->get('baseUrl'), $localPath);
            }
            
            $this->result[] = new ImageResponse([
                'name'   => 'source',
                'size'   => filesize($localPath),
                'width'  => $width,
                'height' => $height,
                'url'    => $url
            ]);
        }
        return $processId;
    }

    public function processVideo($filePath, $callback, $processId = null, $watermark = [])
    {
        throw new \Exception('Not implemented ' . __CLASS__ . ' ' . __METHOD__ . ' ' . json_encode(func_get_args()));
    }
    
    /**
     * @param $localPath
     * @return \Imagine\Image\ImageInterface
     */
    public function fixedOrientation($localPath)
    {
        $command = 'convert -auto-orient -strip "' . $localPath . '" "' . $localPath . '"';
        exec($command);
        return $this->imagine->open($localPath);
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
        $image = $this->imagine->open($filePath);
        if ($image->getImagick()) {
            $image->getImagick()->setImageBackgroundColor('white');
            $image->getImagick()->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        }

        if ($maxSize) {
            $image = $this->resizeAdaptive($image, $maxSize);
        } elseif ($height && $height == $width) {
            $image = $this->crop($image, $height);
        } elseif ($width && $height) {
            $image = $this->resize($image, $width, $height);
        }

        if ($blur) {
            $image->effects()->blur($blur);
        }

        $imageSize = $image->getSize();
        $fileName = $imageSize->getWidth() . 'x' . $imageSize->getHeight() . '_' . urlencode(pathinfo($filePath, PATHINFO_FILENAME)) . '.jpg';
        $savedPath = '/upload/' . $fileName;

//        $webFilter = new WebOptimization(PUBPATH . $savedPath, [
//            'jpeg_quality' => 86
//        ]);
//        $webFilter->apply($image);
        
        $this->setWatermark(PUBPATH . $savedPath, $watermark);
        
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
     * @param $width
     * @param $height
     * @return AbstractImage
     */
    protected function resize($image, $width, $height)
    {
        $imageSize = $image->getSize();
        $imageHeight = $imageSize->getHeight();
        $imageWidth = $imageSize->getWidth();
        if ($imageWidth > $width || $imageHeight > $height) {
            if ($imageWidth < $imageHeight) {
                $height = $imageHeight > $height ? $height : $imageHeight;
                $width = ceil($imageWidth / ($imageHeight / $height));
            } elseif ($imageWidth > $imageHeight) {
                $width = $imageWidth > $width ? $width : $imageWidth;
                $height = ceil($imageHeight / ($imageWidth / $width));
            } else {
                $height = $width;
            }
            $sizeBox = new Box($width, $height);
            $image->resize($sizeBox);
        }
    
        return $image;
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
        
        $startX = round(($width - $size) / 2);
        $startY = round(($height - $size) / 2);

        $cropPoint = new Point($startX, $startY);
        $sizeBox = new Box($size, $size);
    
        $image->crop($cropPoint, $sizeBox);

        return $image;
    }
    
    protected function setWatermark($localPath, $watermark = [])
    {
        if (!empty($watermark['text'])) {
            if (!empty($watermark['size'])) {
                $fontSize = escapeshellarg($watermark['size']);
            } else {
                $fontSize = '$(identify -format "%[fx:int(w*0.03)]" ' . escapeshellarg($localPath) . ')';
            }
            $font = PUBPATH . '/fonts/OpenSans-Regular.ttf';
            $command = 'convert ' . escapeshellarg($localPath)
                . '  -pointsize ' . $fontSize
                . ' -font ' . escapeshellarg($font)
                . ' -draw ' . escapeshellarg('gravity southeast fill grey text 4,4 ' . escapeshellarg($watermark['text'])) . ' '
                . escapeshellarg($localPath);
            @exec($command);
        } elseif (!empty($watermark['imagePath'])) {
            try {
                $watermark = $this->imagine->open($watermark['imagePath']);
                $image     = $this->imagine->open($localPath);
                $size      = $image->getSize();
                $wSize     = $watermark->getSize();
                $width     = $size->getWidth();
                $height    = $size->getHeight();
                $bottomRight = new Point($width - ($width * 0.05) - $wSize->getWidth(), $height - ($height * 0.05) - $wSize->getHeight());
                $image->paste($watermark, $bottomRight)->save();
            } catch (\Exception $e) {
                Logger::send('converter.watermark', ['msg' => $e->getMessage()]);
            }
        }
    }
}
