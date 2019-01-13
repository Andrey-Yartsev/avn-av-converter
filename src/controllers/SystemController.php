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
        $retries = [];
        
        foreach ($redis->keys('retry:*') as $key) {
            if (strpos($key, 'count')) {
                continue;
            }
            $retries[$key] = (int) $redis->get($key . ':count');
        }
        
        return [
            'amazon' => [
                'queues' => count($redis->sMembers('amazon:queue')),
                'upload' => count($redis->sMembers('amazon:upload')),
            ],
            'cloudconvert' => [
                'queues' => count($redis->keys('cc:*')),
            ],
            'general' => [
                'queues' => count($redis->keys('queue:*')),
            ],
            'retries' => $retries
        ];
    }
}