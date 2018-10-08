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
use Converter\components\Redis;
use Converter\components\storages\FileStorage;
use GuzzleHttp\Client;

class CloudConverterController extends Controller
{
    public function actionCallBack()
    {
        $request = $this->getRequest();
        $id = $request->get('id');
        $fn = fopen('log.txt', 'w+');
        fputs($fn, '#' . $id . ' step = ' . $request->get('step'));
        if ($id && $request->get('step') == 'finished') {
            $options = Redis::getInstance()->get('cc:' . $id);
            if ($options) {
                $options = json_decode($options, true);
                $presents = Config::getInstance()->get('presets');
                if (!empty($presents[$options['presetName']])) {
                    $preset = $presents[$options['presetName']];
                    $api = new Api($preset['token']);
                    $process = new Process($api, $request->get('url'));
                    $output = $process->refresh()->output;
                    $url = $output->url;
                    
                    if (!empty($preset['storage'])) {
                        fputs($fn, 'storage step');
                        /** @var FileStorage $storage */
                        $storage = new $preset['storage']['driver']($preset['storage']['url'], $preset['storage']['bucket']);
                        $hash = md5($output->filename);
                        $savedPath = 'files/' . substr($hash, 0, 1) . '/' . substr($hash, 0, 2) . '/' . $hash;
                        fputs($fn, '$savedPath:' . $savedPath . '/' . $hash . '.' . $output->ext);
                        fputs($fn, '$url:' . $url);
                        $url = $storage->upload($url, $savedPath . '/' . $hash . '.' . $output->ext);
                        fputs($fn, 'error:' . $storage->getError());
                        fputs($fn, '$url:' . $url);
                    }
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
        fclose($fn);
    }
}