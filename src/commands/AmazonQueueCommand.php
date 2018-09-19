<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\commands;


use Aws\ElasticTranscoder\ElasticTranscoderClient;
use Converter\components\Config;
use Converter\components\drivers\AmazonDriver;
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
        while (true) {
            $jobs = Redis::getInstance()->sMembers('amazon:queue');
            foreach ($jobs as $job) {
                $output->writeln('<info>Catch ' . $job . '</info>');
                $options = json_decode($job, true);
                $presetName = $options['presetName'];
                $amazonDriver = new AmazonDriver($presetName, $presents[$presetName]);
                $transcoderClient = $amazonDriver->getTranscoderClient();
                $response = $transcoderClient->readJob(['Id' => $options['jobId']]);
                $jobData = (array) $response->get('Job');
                $output->writeln('Read job #' . $options['jobId'] . ', status: ' . strtolower($jobData['Status']));
                if (strtolower($jobData['Status']) == 'complete') {
                    try {
                        $client = new Client();
                        $client->request('POST', $options['callback'], [
                            'json' => [
                                'processId' => $options['processId'],
                                'url' => $amazonDriver->url . '/files/' . $jobData['Output']['Key']
                            ]
                        ]);
                        Redis::getInstance()->sRem('amazon:queue', $job);
                        // @TODO removed original file
                    } catch (\Exception $e) {
        
                    }
                } elseif (strtolower($jobData['Status']) == 'error') {
                    Redis::getInstance()->sRem('amazon:queue', $job);
                }
            }
            sleep(1);
        }
        $this->release();
        
        return 2;
    }
}