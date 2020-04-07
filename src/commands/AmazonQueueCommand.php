<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\commands;

use Converter\components\drivers\AmazonDriver;
use Converter\components\Logger;
use Converter\components\Process;
use Converter\components\Redis;
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
        $totalJobs = count(Redis::getInstance()->sMembers('amazon:queue'));
        if (!$this->lock()) {
            Logger::send('amazon.queue', ['step' => 'Process already working!', 'totalJobs' => $totalJobs]);
            $output->writeln('Process already working!');
            return 1;
        }
        $jobs = Redis::getInstance()->sRandMember('amazon:queue', 50);
        Logger::send('amazon.queue', ['count' => count($jobs), 'total' => $totalJobs]);
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
                    $result = $amazonDriver->getResult();
                    $process->log('Job success', $result);
                    $output->writeln('Job #' . $options['jobId'] . ' complete');
                    $resultCallback = $process->sendCallback([
                        'files' => $result
                    ]);
                    
                    if ($resultCallback) {
                        // @TODO removed original file
                        $output->writeln(json_encode($amazonDriver->getResult()));
                        Redis::getInstance()->sRem('amazon:queue', $job);
                        Logger::send('amazon.queue', ['job' => $job, 'step' => 'Send callback']);
                    } else {
                        $output->writeln('<error>Failed send callback</error>');
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
}