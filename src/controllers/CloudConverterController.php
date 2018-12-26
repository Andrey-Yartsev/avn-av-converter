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
use Converter\helpers\FileHelper;
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
                            $fileType = $preset[$options['fileType']];
                            Logger::send('converter.cc.callback.findPreset', $preset);
                            $driver = Driver::loadByConfig($options['presetName'], $fileType);
                            if ($driver instanceof CloudConvertDriver) {
                                if ($fileType == FileHelper::TYPE_AUDIO) {
                                    $debug = '1';
                                    if ($driver->saveAudio($request->get('url')) == false) {
                                        $this->sendError('Could not get processed file', $id, $options['callback']);
                                    }
                                } elseif ($fileType == FileHelper::TYPE_VIDEO) {
                                    $debug = '2';
                                    if ($driver->saveVideo($request->get('url')) == false) {
                                        $this->sendError('Could not get processed file', $id, $options['callback']);
                                    }
                                } else {
                                    $debug = '3' . $fileType;
                                }
                                
                                $json = [
                                    'debug' => $debug,
                                    'processId' => $id,
                                    'files' => $driver->getResult()
                                ];
                                try {
                                    $client = new Client();
                                    $response = $client->request('POST', $options['callback'], [
                                        'json' => $json
                                    ]);
                                    Logger::send('converter.cc.callback.sendCallback', [
                                        'type' => 'main',
                                        'request' => $json,
                                        'httpCode' => $response->getStatusCode(),
                                        'response' => $response->getBody()
                                    ]);
                                } catch (\Exception $e) {
                                    $this->failedCallback($e->getMessage(), $options['callback'], $id, $json);
                                }
                                Redis::getInstance()->del('cc:' . $id, 'queue:' . $id);
                            }
                        }
                    }
                } elseif ($request->get('step') == 'error') {
                    $this->sendError($request->get('url'), $id, $options['callback']);
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
    
    /**
     * @param $url
     * @param $id
     * @param $callback
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function sendError($url, $id, $callback)
    {
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
        $json = [
            'processId' => $id,
            'error' => $response['message']
        ];
        Redis::getInstance()->del('cc:' . $id, 'queue:' . $id);
        try {
            $client = new Client();
            $response = $client->request('POST', $callback, [
                'json' => $json
            ]);
            Logger::send('converter.cc.callback.sendCallback', [
                'type' => 'main',
                'request' => $json,
                'httpCode' => $response->getStatusCode(),
                'response' => $response->getBody()
            ], LogLevel::ERROR);
        } catch (\Exception $e) {
            $this->failedCallback($e->getMessage(), $callback, $id, $json);
        }
    }
    
    /**
     * @param $error
     * @param $url
     * @param $processId
     * @param $body
     */
    protected function failedCallback($error, $url, $processId, $body)
    {
        $params = [
            'url' => $url,
            'processId' => $processId,
            'body' => $body
        ];
        Redis::getInstance()->set('retry:' . $processId, json_encode($params));
        Redis::getInstance()->incr('retry:' . $processId . ':count');
        Logger::send('converter.cc.callback.sendCallback', [
            'type' => 'main',
            'params' => $params,
            'error' => $error
        ], LogLevel::ERROR);
    }
}