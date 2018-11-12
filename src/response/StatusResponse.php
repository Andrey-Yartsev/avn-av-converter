<?php
/**
 * User: pel
 * Date: 12/11/2018
 */

namespace Converter\response;


use Converter\components\Response;

class StatusResponse extends Response
{
    public $id;
    public $percent;
    
    public function jsonSerialize()
    {
        return [
            'id' => $this->id,
            'percent' => (int) $this->percent,
        ];
    }
}