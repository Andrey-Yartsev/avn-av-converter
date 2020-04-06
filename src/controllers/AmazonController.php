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
use Converter\components\Process;
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
            $messageRaw = json_decode($message['Message'], true);
            $messageRaw = isset($messageRaw['detail']) ? $messageRaw['detail'] : $messageRaw;
            if (isset($messageRaw['state']) && $messageRaw['state'] == 'PROGRESSING') {
                return;
            }
            Logger::send('amazon.sns.notification', [
                'messageId' => $message['MessageId'],
                'messageRaw' => $messageRaw
            ]);
            $jobs = Redis::getInstance()->sMembers('amazon:queue');
            foreach ($jobs as $job) {
                $options = json_decode($job, true);
                if ($options['jobId'] != $messageRaw['jobId']) {
                    continue;
                }
                $presetName = $options['presetName'];
                $process = Process::find($options['processId']);
                $amazonDriver = $process->getDriver();
                if (!$amazonDriver instanceof AmazonDriver) {
                    Logger::send('process', ['processId' => $options['processId'], 'step' => 'Wrong driver']);
                    continue;
                }
                if ($amazonDriver->readJob($options['jobId'], $process)) {
                    try {
                        $json = [
                            'processId' => $options['processId'],
                            'baseUrl'   => Config::getInstance()->get('baseUrl'),
                            'preset'    => $presetName,
                            'files'     => $amazonDriver->getResult()
                        ];
                        Logger::send('process', ['processId' => $options['processId'], 'step' => 'Job success', 'data' => $json]);
                        try {
                            $client = new Client();
                            $response = $client->request('POST', $process->getCallbackUrl(), [
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
                            $this->failedCallback($e->getMessage(), $process->getCallbackUrl(), $options['processId'], $json);
                        }
                        Redis::getInstance()->sRem('amazon:queue', $job);
                        Redis::getInstance()->del('queue:' . $options['processId']);
                        // @TODO removed original file
                    } catch (\Exception $e) {
                        Redis::getInstance()->sRem('amazon:queue', $job);
                        $this->failedCallback($e->getMessage(), $process->getCallbackUrl(), $options['processId'], [
                            'processId' => $options['processId'],
                            'baseUrl'   => Config::getInstance()->get('baseUrl'),
                            'files'     => $amazonDriver->getResult(),
                            'preset'    => $presetName,
                        ]);
                    }
                } else {
                    if (($error = $amazonDriver->getError())) {
                        Redis::getInstance()->sRem('amazon:queue', $job);
                        Logger::send('process', ['processId' => $options['processId'], 'step' => 'Job failed', 'data' => ['error' => $error]]);
                        try {
                            $client = new Client();
                            $response = $client->request('POST', $process->getCallbackUrl(), [
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
                            $this->failedCallback($e->getMessage(), $process->getCallbackUrl(), $options['processId'], [
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