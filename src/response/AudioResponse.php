<?php
/**
 * User: pel
 * Date: 2018-12-26
 */

namespace Converter\response;


use Converter\components\Response;
use Converter\helpers\FileHelper;

class AudioResponse extends Response
{
    public $url;
    public $size;
    public $name;
    public $duration;
    
    public function jsonSerialize()
    {
        return [
            'name' => $this->name,
            'type' => FileHelper::TYPE_AUDIO,
            'url' => $this->url,
            'size' => (int) $this->size,
            'duration' => (int) $this->duration,
        ];
    }
}