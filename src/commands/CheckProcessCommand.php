<?php

namespace Converter\commands;

use Converter\components\drivers\AmazonDriver;
use Converter\components\Locker;
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
        $this->addArgument('jobId', InputArgument::OPTIONAL);
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getArgument('id');
        $process = Process::find($id);
        if (empty($process)) {
            Logger::send('process', ['processId' => $id, 'step' => 'Not founded']);
            echo 'Not founded' . PHP_EOL;
            die;
        }
    
        $jobId = $input->getArgument('jobId');
        if (empty($jobId)) {
            foreach (Redis::getInstance()->sMembers('amazon:queue') as $job) {
                $options = json_decode($job, true);
                if ($options['processId'] == $id) {
                    $jobId = $options['jobId'];
                    echo 'Job founded' . PHP_EOL;
                    break;
                }
            }
        }
        
        if (empty($jobId)) {
            echo 'Job not founded' . PHP_EOL;
            die;
        }
    
        $process->log('Start check status');
        $amazonDriver = $process->getDriver();
        if (!$amazonDriver instanceof AmazonDriver) {
            $process->log('Wrong driver');
            echo 'Wrong driver' . PHP_EOL;
            die;
        }
        try {
            if ($amazonDriver->readJob($jobId, $process)) {
                $result = $amazonDriver->getResult();
                $process->log('Job success', $result);
                $resultCallback = $process->sendCallback(['files' => $result]);
            
                if ($resultCallback) {
                    echo 'Send callback' . PHP_EOL;
                    die;
                } else {
                    echo 'Error send callback' . PHP_EOL;
                    die;
                }
            } else {
                if (($error = $amazonDriver->getError())) {
                    echo 'Error:' . $error . PHP_EOL;
                } else {
                    echo 'Job not complete' . PHP_EOL;
                }
            }
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            die;
        }
    }
}