<?php

namespace Converter\commands;

use Converter\components\drivers\AmazonDriver;
use Converter\components\Logger;
use Converter\components\Process;
use Converter\components\Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * User: pel
 * Date: 02.07.2020
 */

class CheckProcessCommand extends Command
{
    protected function configure()
    {
        $this->setName('process:check');
        $this->addArgument('id', InputArgument::REQUIRED);
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $jobId = $input->getArgument('id');
        $jobs = Redis::getInstance()->sMembers('amazon:queue');
        foreach ($jobs as $job) {
            $options = json_decode($job, true);
            if ($options['jobId'] != $jobId) {
                continue;
            }
            $process = Process::find($options['processId']);
            Logger::send('amazon.queue', ['job' => $job, 'step' => 'Start']);
            if (empty($process)) {
                Logger::send('amazon.queue', ['job' => $job, 'step' => 'Process #' . $options['processId'] . ' not found']);
                Redis::getInstance()->sRem('amazon:queue', $job);
                Logger::send('process', ['processId' => $options['processId'], 'step' => 'Not founded']);
                continue;
            }
            $process->log('Start check status');
            $lockKey = "process:{$process->getId()}";
            $amazonDriver = $process->getDriver();
            if (!$amazonDriver instanceof AmazonDriver) {
                Logger::send('amazon.queue', ['job' => $job, 'step' => 'Process #' . $options['processId'] . ' wrong driver']);
                Redis::getInstance()->sRem('amazon:queue', $job);
                $process->log('Wrong driver');
                continue;
            }
            try {
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
                        $process->sendCallback(['error' => $error]);
                        Logger::send('amazon.queue', ['job' => $job, 'step' => 'readJob():false:' . $error]);
                    } else {
                        Logger::send('amazon.queue', ['job' => $job, 'step' => 'readJob():not complete']);
                    }
                }
            } catch (\Exception $e) {
                $process->log('Failed', ['data' => $e->getMessage()]);
                Redis::getInstance()->sRem('amazon:queue', $job);
                continue;
            }
        }
    }
}