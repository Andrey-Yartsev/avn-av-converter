<?php
/**
 * User: pel
 * Date: 26/10/2018
 */

namespace Converter\response;


use Converter\components\Response;
use Converter\helpers\FileHelper;

class ImageResponse extends Response
{
    public $url;
    public $size;
    public $width;
    public $height;
    
    public function jsonSerialize()
    {
        return [
            'type' => FileHelper::TYPE_IMAGE,
            'url' => $this->url,
            'size' => (int) $this->size,
            'width' => (int) $this->width,
            'height' => (int) $this->height,
        ];
    }
}