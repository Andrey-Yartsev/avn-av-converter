<?php
/**
 * User: pel
 * Date: 07/09/2018
 */

namespace Converter\exceptions;


class HttpException extends \Exception
{
    public $statusCode;

    protected $httpStatuses = [
        200 => 'OK',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway or Proxy Error',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
    ];

    public function __construct($status, $message = null, $code = 0, \Exception $previous = null)
    {
        $this->statusCode = $status;
        parent::__construct($message, $code, $previous);
    }

    public function getName()
    {
        if (isset($this->httpStatuses[$this->statusCode])) {
            return $this->httpStatuses[$this->statusCode];
        }

        return 'Error';
    }
}