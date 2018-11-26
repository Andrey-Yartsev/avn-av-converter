<?php
/**
 * User: pel
 * Date: 26/11/2018
 */

namespace Converter\commands;


use Converter\components\Logger;
use Converter\components\Redis;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RetryCommand extends Command
{
    use LockableTrait;
    
    protected function configure()
    {
        $this->setName('retry');
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('Process already working!');
            return 1;
        }
        
        $keys = Redis::getInstance()->keys('retry:*');
        $httpClient = new Client();
        
        foreach ($keys as $key) {
            $options = Redis::getInstance()->get($key);
            $options = json_decode($options, true);
            $countKey = 'retry:' . $options['processId'] . ':count';
            Redis::getInstance()->incr($countKey);
            try {
                $response = $httpClient->request('POST', $options['url'], [
                    'json' => $options['body']
                ]);
                Logger::send('converter.cc.callback.sendCallback', [
                    'type' => 'retry',
                    'request' => $options['body'],
                    'httpCode' => $response->getStatusCode(),
                    'response' => $response->getBody()
                ]);
                if ($response->getStatusCode() == 200) {
                    Redis::getInstance()->del($key, $countKey);
                }
            } catch (\Exception $e) {
                Logger::send('converter.cc.callback.sendCallback', [
                    'type' => 'retry',
                    'error' => $e->getMessage()
                ]);
            }
    
            if ($countKey > 10) {
                Redis::getInstance()->del($key, $countKey);
                Logger::send('converter.cc.retry', [
                    'options' => $options
                ]);
            }
        }
    
        $this->release();
    
        return 2;
    }
}