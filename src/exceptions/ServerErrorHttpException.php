<?php
/**
 * User: pel
 * Date: 07/09/2018
 */

namespace Converter\exceptions;

class ServerErrorHttpException extends HttpException
{
	public function __construct($message = null, $code = 0, \Exception $previous = null)
	{
		parent::__construct(500, $message, $code, $previous);
	}
}
