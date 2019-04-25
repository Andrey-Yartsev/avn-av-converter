<?php
/**
 * User: pel
 * Date: 25/10/2018
 */

namespace Converter\helpers;


use Converter\response\ImageResponse;
use Converter\response\VideoResponse;
use FFMpeg\FFProbe;

class FileHelper
{
    const TYPE_VIDEO = 'video';
    const TYPE_IMAGE = 'image';
    const TYPE_AUDIO = 'audio';

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
     * @param $mimeType
     * @return bool|string
     */
    public static function getTypeFile($mimeType)
    {
        if (preg_match('/video\/*/', $mimeType) || $mimeType == 'image/gif' || strpos($mimeType, 'stream')) {
            return self::TYPE_VIDEO;
        } elseif (preg_match('/image\/*/', $mimeType)) {
            return self::TYPE_IMAGE;
        }
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
        } else {
            return null;
        }
        $response->url = $fileUrl;
        $response->name = 'source';
        return $response;
    }
    
    /**
     * @param $filePath
     * @return float
     */
    public static function getVideoDuration($filePath)
    {
        $firstStream = FFProbe::create([
            'ffmpeg.binaries'  => exec('which ffmpeg'),
            'ffprobe.binaries' => exec('which ffprobe')
        ])->streams($filePath)
            ->videos()
            ->first();
        return ceil($firstStream->get('duration'));
    }
}
