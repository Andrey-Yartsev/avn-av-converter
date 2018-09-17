<?php
/**
 * User: pel
 * Date: 07/09/2018
 */

namespace Converter\exceptions;

use Converter\components\Form;

class BadRequestHttpException extends HttpException
{
	public function __construct($message = null, $code = 0, \Exception $previous = null)
	{
	    if ($message instanceof Form) {
            $message = current($message->getErrors());
        }
		parent::__construct(400, $message, $code, $previous);
	}
}
