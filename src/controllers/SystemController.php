<?php
/**
 * User: pel
 * Date: 19/11/2018
 */

namespace Converter\controllers;


use Converter\components\Controller;
use Converter\components\Redis;

class SystemController extends Controller
{
    public function actionStatus()
    {
        $redis = Redis::getInstance();
        
        return [
            'requests' => (int) $redis->get('status.requests'),
            'success' => (int) $redis->get('status.success'),
            'queue' => count($redis->keys('queue:*'))
        ];
    }
}