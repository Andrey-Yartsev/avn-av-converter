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
use Converter\exceptions\BadRequestHttpException;
use Converter\forms\CloudConvertForm;
use GuzzleHttp\Client;

class CloudConverterController extends Controller
{
    public function actionProcess()
    {
        $request = $this->getRequest();
        $formData = $request->getContentType() == 'json' ? json_decode($request->getContent(), true) : [];
        $form = new CloudConvertForm();
        $form->setAttributes($formData);
        $processId = $form->start();
        if ($processId === false) {
            throw new BadRequestHttpException($form);
        }
        
        return [
            'processId' => $processId
        ];
    }
    
    public function actionCallBack()
    {
        $request = $this->getRequest();
        $id = $request->get('id');
        if ($id && $request->get('step') == 'finished') {
            $cloudConvertConfig = Config::getInstance()->get('cloudconverter');
            $api = new Api($cloudConvertConfig['token']);
            $options = Redis::getInstance()->get('cc:' . $id);
            if ($options) {
                $options = json_decode($options, true);
                $process = new Process($api, $request->get('url'));
                $url = $process->refresh()->output->url;
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