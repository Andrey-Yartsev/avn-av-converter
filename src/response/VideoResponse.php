<?php
/**
 * User: pel
 * Date: 30/10/2018
 */

namespace Converter\response;


use Converter\components\Response;
use Converter\helpers\FileHelper;

class VideoResponse extends Response
{
    public $url;
    public $size;
    public $width;
    public $height;
    public $name;
    public $duration;
    
    public function jsonSerialize()
    {
        return [
            'name' => $this->name,
            'type' => FileHelper::TYPE_VIDEO,
            'url' => $this->url,
            'size' => (int) $this->size,
            'width' => (int) $this->width,
            'height' => (int) $this->height,
            'duration' => (int) $this->duration,
        ];
    }
}