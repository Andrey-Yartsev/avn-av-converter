<?php
/**
 * User: pel
 * Date: 25/10/2018
 */

namespace Converter\helpers;


use Converter\response\ImageResponse;
use Converter\response\VideoResponse;

class FileHelper
{
    const TYPE_VIDEO = 'video';
    const TYPE_IMAGE = 'image';
    const TYPE_AUDIO = 'audio';
    
    /**
     * @param $mimeType
     * @return bool|string
     */
    public static function getTypeFile($mimeType)
    {
        if (preg_match('/video\/*/', $mimeType) || $mimeType == 'image/gif') {
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
}