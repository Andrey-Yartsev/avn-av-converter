<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\commands;

use Aws\Credentials\Credentials;
use Aws\MediaConvert\MediaConvertClient;
use Converter\components\Locker;
use Converter\components\Logger;
use Converter\components\Process;
use Converter\components\Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AmazonQueueCountCommand extends Command
{
    protected function configure()
    {
        $this->setName('amazon:queue:count');
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        echo 'Queue: ' . count(Redis::getInstance()->sMembers('amazon:queue')) . PHP_EOL;
        echo 'Workers: ' . floor(exec('ps aux | grep "amazon:queue" | grep -v "grep" | wc -l' ) / 2) . PHP_EOL;
        return 2;
        $mediaConvertClient = new MediaConvertClient([
             'version' => 'latest',
             'region' => 'us-east-1',
             'credentials' => new Credentials('AKIAUSX4CWPPFHYXZ6PQ', 'Ie9dhRuq/dWiAJM2MduBdajyTmxz7b9mnFX4Gjcp'),
             'endpoint' => 'https://q25wbt2lc.mediaconvert.us-east-1.amazonaws.com'
         ]);
        $jobs = Redis::getInstance()->sRandMember('amazon:queue', 500);
        foreach ($jobs as $job) {
            $options = json_decode($job, true);
            $process = Process::find($options['processId']);
            if (empty($process)) {
                Logger::send('amazon.queue', ['job' => $job, 'step' => 'Process not found']);
                Redis::getInstance()->sRem('amazon:queue', $job);
                continue;
            }
            if ($options['presetName'] != 'of_beta') {
                continue;
            }
            $lockProcessingKey = "in:processing:{$process->getId()}";
            if (Locker::isLocked($lockProcessingKey)) {
                Logger::send('amazon.queue', ['job' => $job, 'step' => 'Already in processing']);
                continue;
            }
            try {
                $response = $mediaConvertClient->getJob(['Id' => $options['jobId']]);
                $jobData = (array) $response->get('Job');
                if ($jobData['Status'] == 'SUBMITTED') {
                    $mediaConvertClient->cancelJob(['Id' => $options['jobId']]);
                    Redis::getInstance()->sRem('amazon:queue', $job);
                    Process::restart($process->getId(), 'of_reserve');
                    echo "Process #{$process->getId()} restarted" . PHP_EOL;
                }
            } catch (\Throwable $e) {
                echo 'Skip';
            }
            sleep(1);
        }
        return 2;
    }
}