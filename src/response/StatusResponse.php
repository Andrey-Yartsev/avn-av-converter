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
    public $step;
    public $message;
    
    public function jsonSerialize()
    {
        return [
            'id'      => $this->id,
            'percent' => floor($this->percent),
            'step'    => $this->step,
            'message' => $this->message
        ];
    }
}