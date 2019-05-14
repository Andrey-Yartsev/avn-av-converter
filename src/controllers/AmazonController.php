<?php
/**
 * User: pel
 * Date: 20/11/2018
 */

namespace Converter\controllers;


use Aws\Sns\Exception\InvalidSnsMessageException;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Converter\components\Config;
use Converter\components\Controller;
use Converter\components\drivers\AmazonDriver;
use Converter\components\Logger;
use Converter\components\Redis;
use Converter\exceptions\NotFoundHttpException;
use GuzzleHttp\Client;

class AmazonController extends Controller
{
    public function actionSns()
    {
        $message = Message::fromRawPostData();
        $validator = new MessageValidator();
        
        try {
            $validator->validate($message);
        } catch (InvalidSnsMessageException $e) {
            Logger::send('amazon.sns.validate', [
                'error' => $e->getMessage()
            ]);
            throw new NotFoundHttpException();
        }
    
        if ($message['Type'] === 'SubscriptionConfirmation') {
            file_get_contents($message['SubscribeURL']);
        } elseif ($message['Type'] === 'Notification') {
            Logger::send('amazon.sns.notification', [
                'messageId' => $message['MessageId'],
                'messageRaw' => $message['Message']
            ]);
            $presents = Config::getInstance()->get('presets');
            $jobs = Redis::getInstance()->sMembers('amazon:queue');
            foreach ($jobs as $job) {
                $options = json_decode($job, true);
                if ($options['jobId'] != $message['Message']['jobId']) {
                    continue;
                }
                $presetName = $options['presetName'];
                $amazonDriver = new AmazonDriver($presetName, $presents[$presetName]['video']);
                if ($amazonDriver->readJob($options['jobId'])) {
                    try {
                        $json = [
                            'processId' => $options['processId'],
                            'files' => $amazonDriver->getResult()
                        ];
                        Logger::send('process', ['processId' => $options['processId'], 'step' => 'Job success', 'data' => $json]);
                        try {
                            $client = new Client();
                            $response = $client->request('POST', $options['callback'], [
                                'json' => $json
                            ]);
                            Logger::send('converter.callback.sendCallback', [
                                'type' => 'amazon_main',
                                'request' => $json,
                                'httpCode' => $response->getStatusCode(),
                                'response' => $response->getBody()
                            ]);
                            Logger::send('process', ['processId' => $options['processId'], 'step' => 'Send callback', 'data' => [
                                'httpCode' => $response->getStatusCode(),
                                'response' => $response->getBody()
                            ]]);
                        } catch (\Exception $e) {
                            $this->failedCallback($e->getMessage(), $options['callback'], $options['processId'], $json);
                        }
                        Redis::getInstance()->sRem('amazon:queue', $job);
                        Redis::getInstance()->del('queue:' . $options['processId']);
                        // @TODO removed original file
                    } catch (\Exception $e) {
                        Redis::getInstance()->sRem('amazon:queue', $job);
                        $this->failedCallback($e->getMessage(), $options['callback'], $options['processId'], [
                            'processId' => $options['processId'],
                            'files' => $amazonDriver->getResult()
                        ]);
                    }
                } else {
                    if (($error = $amazonDriver->getError())) {
                        Redis::getInstance()->sRem('amazon:queue', $job);
                        Logger::send('process', ['processId' => $options['processId'], 'step' => 'Job failed', 'data' => ['error' => $error]]);
                        try {
                            $client = new Client();
                            $response = $client->request('POST', $options['callback'], [
                                'json' => [
                                    'processId' => $options['processId'],
                                    'error' => $error
                                ]
                            ]);
                            Logger::send('converter.callback.sendCallback', [
                                'type' => 'amazon_main',
                                'request' => [
                                    'processId' => $options['processId'],
                                    'error' => $error
                                ],
                                'httpCode' => $response->getStatusCode(),
                                'response' => $response->getBody()
                            ]);
                        } catch (\Exception $e) {
                            $this->failedCallback($e->getMessage(), $options['callback'], $options['processId'], [
                                'processId' => $options['processId'],
                                'error' => $error
                            ]);
                        }
                    }
                }
            }
        }
        
        return true;
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
        Logger::send('process', ['processId' => $processId, 'step' => 'Error send callback', 'data' => [
            'error' => $error
        ]]);
        Logger::send('converter.callback.sendCallback', [
            'type' => 'main',
            'params' => $params,
            'error' => $error
        ]);
    }
}