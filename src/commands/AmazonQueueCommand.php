<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\commands;

use Converter\components\drivers\AmazonDriver;
use Converter\components\Locker;
use Converter\components\Logger;
use Converter\components\Process;
use Converter\components\Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AmazonQueueCommand extends Command
{
    protected function configure()
    {
        $this->setName('amazon:queue');
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $totalJobs = count(Redis::getInstance()->sMembers('amazon:queue'));
        $jobs = Redis::getInstance()->sRandMember('amazon:queue', 150);
        Logger::send('amazon.queue', ['count' => count($jobs), 'total' => $totalJobs]);
        foreach ($jobs as $job) {
            $options = json_decode($job, true);
            $process = Process::find($options['processId']);
            if (empty($process)) {
                Logger::send('amazon.queue', ['job' => $job, 'step' => 'Process not found']);
                Redis::getInstance()->sRem('amazon:queue', $job);
                continue;
            }
            $lockProcessingKey = "in:processing:{$process->getId()}";
            if (Locker::isLocked($lockProcessingKey)) {
                Logger::send('amazon.queue', ['job' => $job, 'step' => 'Already in processing']);
                continue;
            }
            Logger::send('amazon.queue', ['job' => $job, 'step' => 'Start']);
            if (empty($process)) {
                Logger::send('amazon.queue', ['job' => $job, 'step' => 'Process #' . $options['processId'] . ' not found']);
                Redis::getInstance()->sRem('amazon:queue', $job);
                Logger::send('process', ['processId' => $options['processId'], 'step' => 'Not founded']);
                continue;
            }
            $process->log('Start check status');
            $lockKey = "process:{$process->getId()}";
            if (Locker::isLocked($lockKey)) {
                $process->log('Skip by timeout');
                Logger::send('amazon.queue', ['job' => $job, 'step' => 'Skip by timeout']);
                continue;
            }
            $amazonDriver = $process->getDriver();
            if (!$amazonDriver instanceof AmazonDriver) {
                Logger::send('amazon.queue', ['job' => $job, 'step' => 'Process #' . $options['processId'] . ' wrong driver']);
                Redis::getInstance()->sRem('amazon:queue', $job);
                $process->log('Wrong driver');
                continue;
            }
            try {
                Locker::lock($lockProcessingKey, 60);
                if ($amazonDriver->readJob($options['jobId'], $process)) {
                    Logger::send('amazon.queue', ['job' => $job, 'step' => 'readJob():true']);
                    $result = $amazonDriver->getResult();
                    $process->log('Job success', $result);
                    $resultCallback = $process->sendCallback([
                        'files' => $result
                    ]);
                    
                    if ($resultCallback) {
                        // @TODO removed original file
                        Redis::getInstance()->sRem('amazon:queue', $job);
                        Logger::send('amazon.queue', ['job' => $job, 'step' => 'Send callback']);
                    } else {
                        Redis::getInstance()->sRem('amazon:queue', $job);
                        Logger::send('amazon.queue', ['job' => $job, 'step' => 'Error send callback']);
                    }
                } else {
                    if (($error = $amazonDriver->getError())) {
                        Redis::getInstance()->sRem('amazon:queue', $job);
                        $process->log('Job failed', ['error' => $error]);
                        $process->sendCallback([
                            'error' => $error
                        ]);
                        Logger::send('amazon.queue', ['job' => $job, 'step' => 'readJob():false:' . $error]);
                    } else {
                        Logger::send('amazon.queue', ['job' => $job, 'step' => 'readJob():not complete']);
                        Locker::lock($lockKey, 120);
                    }
                }
            } catch (\Exception $e) {
                $process->log('Failed', ['data' => $e->getMessage()]);
                Redis::getInstance()->sRem('amazon:queue', $job);
                continue;
            }
            Locker::unlock($lockProcessingKey);
            sleep(1);
        }
        
        return 2;
    }
}