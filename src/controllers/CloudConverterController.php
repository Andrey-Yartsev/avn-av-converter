<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\controllers;


use CloudConvert\Api;
use CloudConvert\Process;
use Converter\components\Config;
use Converter\components\Controller;
use Converter\components\drivers\CloudConvertDriver;
use Converter\components\drivers\Driver;
use Converter\components\Logger;
use Converter\components\Redis;
use Converter\components\storages\FileStorage;
use GuzzleHttp\Client;
use Psr\Log\LogLevel;

class CloudConverterController extends Controller
{
    public function actionCallBack()
    {
        $request = $this->getRequest();
        $id = $request->get('processId');
        if (!$id) {
            $id = $request->get('id');
        }
        Logger::send('converter.cc.callback.init', [
            'params'  => $_GET
        ]);
        if ($id) {
            $options = Redis::getInstance()->get('cc:' . $id);
            if ($options) {
                $options = json_decode($options, true);
                if ($request->get('step') == 'finished') {
                    Logger::send('converter.cc.callback.findJob', $options);
                    $presents = Config::getInstance()->get('presets');
                    if (!empty($presents[$options['presetName']])) {
                        $preset = $presents[$options['presetName']];
                        if (!empty($preset[$options['fileType']])) {
                            Logger::send('converter.cc.callback.findPreset', $preset);
                            $driver = Driver::loadByConfig($options['presetName'], $preset[$options['fileType']]);
                            if ($driver instanceof CloudConvertDriver) {
                                try {
                                    $driver->saveVideo($request->get('url'));
                                    $client = new Client();
                                    $response = $client->request('POST', $options['callback'], [
                                        'json' => [
                                            'processId' => $id,
                                            'files' => $driver->getResult()
                                        ]
                                    ]);
                                    Logger::send('converter.cc.callback.sendCallback', [
                                        'request' => [
                                            'processId' => $id,
                                            'files' => $driver->getResult()
                                        ],
                                        'response' => $response->getBody()
                                    ]);
                                    Redis::getInstance()->del('cc:' . $id, 'queue:' . $id);
                                    Redis::getInstance()->incr('status.success');
                                } catch (\Exception $e) {
                                    Logger::send('converter.cc.callback.sendCallback', [
                                        'error' => $e->getMessage()
                                    ], LogLevel::ERROR);
                                }
                            }
                        }
                    }
                } elseif ($request->get('step') == 'error') {
                    $url = $request->get('url');
                    if (strpos($url, '//') === 0) {
                        $url = 'https:' . $url;
                    }
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_URL, $url);
                    $response = curl_exec($ch);
                    curl_close($ch);
                    $response = json_decode($response, true);
                    $client = new Client();
                    $response = $client->request('POST', $options['callback'], [
                        'json' => [
                            'processId' => $id,
                            'error' => $response['message']
                        ]
                    ]);
                    Redis::getInstance()->del('cc:' . $id, 'queue:' . $id);
                }
            }
        } else {
            $url = $request->get('url');
            if (strpos($url, '//') === 0) {
                $url = 'http:' . $url;
            }
            Logger::send('converter.cc.callback.error', [
                'url'   => $url,
                'answer' => file_get_contents($url)
            ]);
        }
    }
}