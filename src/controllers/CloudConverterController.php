<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\controllers;


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
        $id      = $request->get('processId');
        $url     = $request->get('url', '');
        if (!$id) {
            $id = $request->get('id');
        }
        Logger::send('converter.cc.callback.init', [
            'params' => $_GET
        ]);
        if ($id) {
            $options = Redis::getInstance()->get('cc:' . $id);
            if ($options) {
                $options = json_decode($options, true);
                if ($request->get('step') == 'finished') {
                    Logger::send('converter.cc.callback.findJob', $options);
                    $presets    = Config::getInstance()->get('presets');
                    $presetName = $options['presetName'] ?? '';
                    if ($presetName && !empty($presets[$presetName])) {
                        $preset   = $presets[$presetName];
                        $fileType = $options['fileType'] ?? '';
                        if ($fileType && in_array($fileType, FileHelper::getAllowedTypes()) && !empty($preset[$fileType])) {
                            Logger::send('converter.cc.callback.findPreset', $preset);
                            $driver = Driver::loadByConfig($presetName, $preset[$fileType]);
                            if ($driver instanceof CloudConvertDriver) {
                                $success = true;
                                if ($fileType == FileHelper::TYPE_AUDIO) {
                                    $success = $driver->saveAudio($url);
                                } elseif ($fileType == FileHelper::TYPE_VIDEO) {
                                    $success = $driver->saveVideo($url);
                                }
                                if (!$success) {
                                    $this->sendError('Could not get processed file', $id, $options['callback']);
                                }

                                $json = [
                                    'processId' => $id,
                                    'files'     => $driver->getResult()
                                ];
                                try {
                                    $client   = new Client();
                                    $response = $client->request('POST', $options['callback'], [
                                        'json' => $json
                                    ]);
                                    Logger::send('converter.callback.sendCallback', [
                                        'type'     => 'main',
                                        'request'  => $json,
                                        'httpCode' => $response->getStatusCode(),
                                        'response' => $response->getBody()
                                    ]);
                                } catch (\GuzzleHttp\Exception\GuzzleException | \Exception $e) {
                                    $this->failedCallback($e->getMessage(), $options['callback'], $id, $json);
                                }
                                Redis::getInstance()->del('cc:' . $id, 'queue:' . $id);
                            }
                        }
                    }
                } elseif ($request->get('step') == 'error') {
                    $this->sendError($url, $id, $options['callback']);
                }
            }
        } else {
            if (strpos($url, '//') === 0) {
                $url = 'http:' . $url;
            }
            Logger::send('converter.cc.callback.error', [
                'url'    => $url,
                'answer' => file_get_contents($url)
            ]);
        }
    }

    /**
     * @param $url
     * @param $id
     * @param $callback
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
        $json     = [
            'processId' => $id,
            'error'     => $response['message'] ?? ''
        ];
        Redis::getInstance()->del('cc:' . $id, 'queue:' . $id);
        try {
            $client         = new Client();
            $guzzleResponse = $client->request('POST', $callback, [
                'json' => $json
            ]);
            Logger::send('converter.callback.sendCallback', [
                'type'     => 'main',
                'request'  => $json,
                'httpCode' => $guzzleResponse->getStatusCode(),
                'response' => $guzzleResponse->getBody()
            ], LogLevel::ERROR);
        } catch (\GuzzleHttp\Exception\GuzzleException | \Exception $e) {
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
            'url'       => $url,
            'processId' => $processId,
            'body'      => $body
        ];
        Redis::getInstance()->set('retry:' . $processId, json_encode($params));
        Redis::getInstance()->incr('retry:' . $processId . ':count');
        Logger::send('converter.callback.sendCallback', [
            'type'   => 'main',
            'params' => $params,
            'error'  => $error
        ], LogLevel::ERROR);
    }
}
