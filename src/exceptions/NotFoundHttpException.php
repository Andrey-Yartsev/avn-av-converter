<?php
/**
 * User: pel
 * Date: 07/09/2018
 */

namespace Converter\exceptions;

class NotFoundHttpException extends HttpException
{
	public function __construct($message = null, $code = 0, \Exception $previous = null)
	{
		parent::__construct(404, $message, $code, $previous);
	}
}
