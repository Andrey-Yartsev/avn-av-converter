<?php
/**
 * User: pel
 * Date: 20/11/2018
 */

namespace Converter\controllers;


use Converter\components\Controller;
use Converter\components\Redis;

class AmazonController extends Controller
{
    public function actionSns()
    {
        $params = array_merge($_GET, $_POST);
        Redis::getInstance()->set('amazon:sns:' . time(), json_encode($params));
        return ['success' => true];
    }
}