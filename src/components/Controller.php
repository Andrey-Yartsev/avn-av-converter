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
    
    /**
     * Controller constructor.
     * @param Request $request
     */
    public function __construct($request)
    {
        $this->_request = $request;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->_request;
    }

}