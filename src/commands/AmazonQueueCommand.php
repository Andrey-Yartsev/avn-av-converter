<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\commands;

use Converter\components\Config;
use Converter\components\drivers\AmazonDriver;
use Converter\components\Logger;
use Converter\components\Redis;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AmazonQueueCommand extends Command
{
    use LockableTrait;
    
    protected function configure()
    {
        $this->setName('amazon:queue');
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('Process already working!');
            return 1;
        }
        
        $presents = Config::getInstance()->get('presets');
        $jobs = Redis::getInstance()->sMembers('amazon:queue');
        foreach ($jobs as $job) {
            $output->writeln('<info>Catch ' . $job . '</info>');
            $options = json_decode($job, true);
            $presetName = $options['presetName'];
            $amazonDriver = new AmazonDriver($presetName, $presents[$presetName]['video']);
            
            $output->writeln('Read job #' . $options['jobId']);
            if ($amazonDriver->readJob($options['jobId'])) {
                $output->writeln('Job #' . $options['jobId'] . ' complete');
                try {
                    $json = [
                        'processId' => $options['processId'],
                        'baseUrl'   => Config::getInstance()->get('baseUrl'),
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
                    $output->writeln(json_encode($amazonDriver->getResult()));
                    Redis::getInstance()->sRem('amazon:queue', $job);
                    Redis::getInstance()->del('queue:' . $options['processId']);
                    // @TODO removed original file
                } catch (\Exception $e) {
                    $output->writeln('<error>' . $e->getMessage() . '</error>');
                    Redis::getInstance()->sRem('amazon:queue', $job);
                    $this->failedCallback($e->getMessage(), $options['callback'], $options['processId'], [
                        'processId' => $options['processId'],
                        'baseUrl'   => Config::getInstance()->get('baseUrl'),
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
                    
                    $output->writeln('<error>Job #' . $options['jobId'] . ' error</error>');
                } else {
                    $output->writeln('<error>Job #' . $options['jobId'] . ' not complete</error>');
                }
            }
            sleep(1);
        }
        $this->release();
        
        return 2;
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