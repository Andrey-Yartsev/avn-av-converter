<?php
/**
 * User: pel
 * Date: 25/10/2018
 */

namespace Converter\helpers;


use Converter\components\Logger;
use Converter\response\AudioResponse;
use Converter\response\ImageResponse;
use Converter\response\VideoResponse;
use FFMpeg\FFProbe;

class FileHelper
{
    const TYPE_VIDEO = 'video';
    const TYPE_IMAGE = 'image';
    const TYPE_AUDIO = 'audio';

    protected static $firstStreams = [];
    /**
     * @return array
     */
    public static function getAllowedTypes()
    {
        return [
            self::TYPE_AUDIO,
            self::TYPE_VIDEO,
            self::TYPE_IMAGE,
        ];
    }
    
    /**
     * @param $filePath
     * @return bool|string
     */
    public static function getTypeFile($filePath)
    {
        $output = $return = null;
        exec(sprintf('file --mime-type -b %s', escapeshellarg($filePath)), $output, $return);
        $mimeType = $return === 0 && $output ? $output[0] : null;
        
        if (preg_match('/video\/*/', $mimeType) || $mimeType == 'image/gif' || strpos($mimeType, 'stream')) {
            return self::TYPE_VIDEO;
        } elseif (preg_match('/image\/*/', $mimeType)) {
            return self::TYPE_IMAGE;
        } elseif (preg_match('/audio\/*/', $mimeType)) {
            return self::isVideo($filePath) ? self::TYPE_VIDEO : self::TYPE_AUDIO;
        }
        Logger::send('wrong.ext', [
            'mimeType' => $mimeType,
            'filePath' => $filePath
        ]);
        return false;
    }

    /**
     * @param $fileUrl
     * @param $fileType
     * @return ImageResponse|VideoResponse|null
     */
    public static function getFileResponse($fileUrl, $fileType)
    {
        if ($fileType == self::TYPE_VIDEO) {
            $response = new VideoResponse();
        } elseif ($fileType == self::TYPE_IMAGE) {
            $response = new ImageResponse();
        } elseif ($fileType == self::TYPE_AUDIO) {
            $response = new AudioResponse();
        } else {
            return null;
        }
        $response->url = $fileUrl;
        $response->name = 'source';
        return $response;
    }
    
    /**
     * @param $filePath
     * @return bool
     */
    public static function isVideo($filePath)
    {
        $ffprobe = \FFMpeg\FFProbe::create([
            'ffmpeg.binaries'  => exec('which ffmpeg'),
            'ffprobe.binaries' => exec('which ffprobe')
        ]);
        return (bool) count($ffprobe->streams($filePath)->videos());
    }
    
    /**
     * @param $filePath
     * @return FFProbe\DataMapping\Stream
     */
    protected static function getFirstVideoFrame($filePath)
    {
        if (empty(self::$firstStreams[$filePath])) {
            self::$firstStreams[$filePath] = FFProbe::create([
                'ffmpeg.binaries'  => exec('which ffmpeg'),
                'ffprobe.binaries' => exec('which ffprobe')
            ])->streams($filePath)
                ->videos()
                ->first();
        }
        return self::$firstStreams[$filePath];
    }
    
    /**
     * @param $filePath
     * @return float
     */
    public static function getVideoDuration($filePath)
    {
        $firstStream = self::getFirstVideoFrame($filePath);
        
        return floor($firstStream->get('duration'));
    }
    
    /**
     * @param $filePath
     * @return array
     */
    public static function getVideoDimensions($filePath)
    {
        $firstStream = self::getFirstVideoFrame($filePath);
        $dimensions = $firstStream->getDimensions();
        return [$dimensions->getWidth(), $dimensions->getHeight()];
    }
}
