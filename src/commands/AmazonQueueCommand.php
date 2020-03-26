<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\commands;

use Converter\components\Config;
use Converter\components\drivers\AmazonDriver;
use Converter\components\Logger;
use Converter\components\Process;
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
        
        $jobs = Redis::getInstance()->sMembers('amazon:queue');
        Logger::send('amazon.queue', ['count' => count($jobs)]);
        foreach ($jobs as $job) {
            $output->writeln('<info>Catch ' . $job . '</info>');
            $options = json_decode($job, true);
            $presetName = $options['presetName'];
            $process = Process::find($options['processId']);
            Logger::send('amazon.queue', ['job' => $job, 'step' => 'Start']);
            if (empty($process)) {
                Logger::send('amazon.queue', ['job' => $job, 'step' => 'Process #' . $options['processId'] . ' not found']);
                $output->writeln('<error>Process not found</error>');
                Redis::getInstance()->sRem('amazon:queue', $job);
                Logger::send('process', ['processId' => $options['processId'], 'step' => 'Not founded']);
                continue;
            }
            $amazonDriver = $process->getDriver();
            if (!$amazonDriver instanceof AmazonDriver) {
                Logger::send('amazon.queue', ['job' => $job, 'step' => 'Process #' . $options['processId'] . ' wrong driver']);
                $output->writeln('<error>Wrong driver</error>');
                Redis::getInstance()->sRem('amazon:queue', $job);
                Logger::send('process', ['processId' => $options['processId'], 'step' => 'Wrong driver']);
                continue;
            }
            $output->writeln('Read job #' . $options['jobId']);
            try {
                
                if ($amazonDriver->readJob($options['jobId'], $process)) {
                    Logger::send('amazon.queue', ['job' => $job, 'step' => 'readJob():true']);
                    $output->writeln('Job #' . $options['jobId'] . ' complete');
                    try {
                        $json = [
                            'processId' => $options['processId'],
                            'preset'    => $presetName,
                            'baseUrl'   => Config::getInstance()->get('baseUrl'),
                            'files'     => $amazonDriver->getResult()
                        ];
                        Logger::send('process', ['processId' => $options['processId'], 'step' => 'Job success', 'data' => $json]);
                        Logger::send('amazon.queue', ['job' => $job, 'step' => 'Job success']);
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
                            Logger::send('amazon.queue', ['job' => $job, 'step' => 'Send callback ' . $response->getStatusCode()]);
                        } catch (\Exception $e) {
                            Logger::send('amazon.queue', ['job' => $job, 'step' => 'Error send callback ' . $e->getMessage()]);
                            $this->failedCallback($e->getMessage(), $process->getCallbackUrl(), $options['processId'], $json);
                        }
                        $output->writeln(json_encode($amazonDriver->getResult()));
                        Redis::getInstance()->sRem('amazon:queue', $job);
                        Redis::getInstance()->del('queue:' . $options['processId']);
                        // @TODO removed original file
                    } catch (\Exception $e) {
                        $output->writeln('<error>' . $e->getMessage() . '</error>');
                        Redis::getInstance()->sRem('amazon:queue', $job);
                        $this->failedCallback($e->getMessage(), $process->getCallbackUrl(), $options['processId'], [
                            'processId' => $options['processId'],
                            'baseUrl'   => Config::getInstance()->get('baseUrl'),
                            'preset'    => $presetName,
                            'files'     => $amazonDriver->getResult()
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
                        Logger::send('amazon.queue', ['job' => $job, 'step' => 'readJob():false:' . $error]);
                        $output->writeln('<error>Job #' . $options['jobId'] . ' error</error>');
                    } else {
                        Logger::send('amazon.queue', ['job' => $job, 'step' => 'readJob():not complete']);
                        $output->writeln('<error>Job #' . $options['jobId'] . ' not complete</error>');
                    }
                }
            } catch (\Exception $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                Logger::send('process', ['processId' => $process->getId(), 'step' => 'Failed', 'data' => $e->getMessage()]);
                Redis::getInstance()->sRem('amazon:queue', $job);
                continue;
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