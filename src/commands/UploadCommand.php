<?php
/**
 * User: pel
 * Date: 14/01/2019
 */

namespace Converter\commands;


use Converter\components\drivers\AmazonDriver;
use Converter\components\Logger;
use Converter\components\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UploadCommand extends Command
{
    protected function configure()
    {
        $this->setName('worker:upload');
        $this->addArgument('upload', InputArgument::REQUIRED);
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $upload = $input->getArgument('upload');
        try {
            $params = json_decode($upload, true);
            $process = Process::find($params['processId']);
            $amazonDriver = $process->getDriver();
            Logger::send('worker.upload.run', [
                'step' => $params['processId'] . ' init amazon driver'
            ]);
            Logger::send('process', ['processId' => $params['processId'], 'step' => 'Amazon driver', 'data' => ['status' => 'init']]);
            if ($process) {
                if ($amazonDriver instanceof AmazonDriver) {
                    if (!$amazonDriver->createJob($process)) {
                        $error = $amazonDriver->getError();
                        if ($error) {
                            $process->log("Failed creat job, $error");
                            $process->sendCallback(['error' => $error]);
                        } else {
                            $process->log('Failed creat job, no error message');
                        }
                    }
                } else {
                    $process->log('Wrong driver');
                }
            } else {
                Logger::send('process', ['processId' => $params['processId'], 'step' => 'Try creat job, process not found']);
            }
        } catch (\Exception $e) {
            Logger::send('faileds', [
                'process' => $upload
            ]);
        }
    }
}