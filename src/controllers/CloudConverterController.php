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
        if ($id === null) {
            $id = $request->get('id');
        }
        Logger::send('CC.callback.init', [
            'getId' => $id,
            'step'  => $request->get('step')
        ]);
        if ($id && $request->get('step') == 'finished') {
            $options = Redis::getInstance()->get('cc:' . $id);
            if ($options) {
                $options = json_decode($options, true);
                Logger::send('CC.callback.findJob', $options);
                $presents = Config::getInstance()->get('presets');
                if (!empty($presents[$options['presetName']])) {
                    $preset = $presents[$options['presetName']];
                    $api = new Api($preset['token']);
                    $process = new Process($api, $request->get('url'));
                    $output = $process->refresh()->output;
                    $url = $output->url;
                    Logger::send('CC.callback.findPreset', $preset);
                    if (!empty($preset['storage'])) {
                        /** @var FileStorage $storage */
                        $storage = new $preset['storage']['driver']($preset['storage']['url'], $preset['storage']['bucket']);
                        $hash = md5($output->filename);
                        $savedPath = 'files/' . substr($hash, 0, 1) . '/' . substr($hash, 0, 2) . '/' . $hash;
                        Logger::send('CC.callback.upload', [
                            'url'       => $url,
                            'savedPath' => $savedPath . '/' . $hash . '.' . $output->ext
                        ]);
                        $url = $storage->upload($url, $savedPath . '/' . $hash . '.' . $output->ext);
                        Logger::send('CC.callback.uploadFinished', [
                            'url' => $url
                        ]);
                    }
                    try {
                        $client = new Client();
                        $response = $client->request('POST', $options['callback'], [
                            'json' => [
                                'processId' => $id,
                                'url'       => $url,
                                'size'      => $output->size
                            ]
                        ]);
                        Logger::send('CC.callback.sendCallback', [
                            'response' => $response->getBody()
                        ]);
                        Redis::getInstance()->del('cc:' . $id);
                    } catch (\Exception $e) {
                        Logger::send('CC.callback.sendCallback', [
                            'error' => $e->getMessage()
                        ], LogLevel::ERROR);
                    }
                }
            }
        }
    }
}