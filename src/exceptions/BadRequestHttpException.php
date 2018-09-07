<?php
/**
 * User: pel
 * Date: 07/09/2018
 */

namespace Converter\exceptions;

class BadRequestHttpException extends HttpException
{
	public function __construct($message = null, $code = 0, \Exception $previous = null)
	{
	    if ($message instanceof ApiForm) {
            $this->additionalArguments = $message->additionalArguments;
            $message = current($message->getErrors());
        }
		parent::__construct(400, $message, $code, $previous);
	}
}
