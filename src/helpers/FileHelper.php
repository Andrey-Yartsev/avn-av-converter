<?php
/**
 * User: pel
 * Date: 25/10/2018
 */

namespace Converter\helpers;


class FileHelper
{
    const TYPE_VIDEO = 'video';
    const TYPE_IMAGE = 'image';
    const TYPE_AUDIO = 'audio';
    
    public static function getTypeFile($mimeType)
    {
        if (preg_match('/video\/*/', $mimeType) || $mimeType == 'image/gif') {
            return self::TYPE_VIDEO;
        } elseif (preg_match('/image\/*/', $mimeType)) {
            return self::TYPE_IMAGE;
        }
        return false;
    }
}