<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\controllers;


use CloudConvert\Api;
use CloudConvert\Process;
use Converter\components\Controller;
use Converter\components\Redis;
use GuzzleHttp\Client;

class CloudConverterController extends Controller
{
    public function actionCallBack()
    {
        $request = $this->getRequest();
        $id = $request->get('id');
        if ($id && $request->get('step') == 'finished') {
            $options = Redis::getInstance()->get('cc:' . $id);
            if ($options) {
                $options = json_decode($options, true);
                $api = new Api($options['token']);
                $process = new Process($api, $request->get('url'));
                $url = $process->refresh()->output->url;
                exec('wget "https:' . $url . '" -O "/var/www/html/apichat.retloko.com/app/logs/test.mp4"');
                try {
                    $client = new Client();
                    $client->request('POST', $options['callback'], [
                        'json' => [
                            'processId' => $id,
                            'url' => $url
                        ]
                    ]);
                    Redis::getInstance()->del('cc:' . $id);
                } catch (\Exception $e) {
        
                }
            }
        }
    }
}