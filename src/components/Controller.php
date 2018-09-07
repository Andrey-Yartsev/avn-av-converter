<?php
/**
 * User: pel
 * Date: 07/09/2018
 */

namespace Converter\components;


use Symfony\Component\HttpFoundation\Request;

class Controller
{
    /** @var  Request */
    protected $_request;

    public function __construct()
    {
        $this->_request = Request::createFromGlobals();
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->_request;
    }

}