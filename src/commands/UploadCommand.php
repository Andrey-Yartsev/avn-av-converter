<?php
/**
 * User: pel
 * Date: 14/01/2019
 */

namespace Converter\commands;


use Converter\components\Config;
use Converter\components\drivers\AmazonDriver;
use Converter\components\Logger;
use Converter\components\Process;
use Converter\components\Redis;
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
            $presents = Config::getInstance()->get('presets');
            $params = json_decode($upload, true);
            $presetName = $params['presetName'];
            $amazonDriver = new AmazonDriver($presetName, $presents[$presetName]['video']);
            Logger::send('worker.upload.run', [
                'step' => $params['processId'] . ' init amazon driver'
            ]);
            Logger::send('process', ['processId' => $params['processId'], 'step' => 'Amazon driver', 'data' => ['status' => 'init']]);
            $process = Process::find($params['processId']);
            if ($process) {
                if ($amazonDriver->createJob($process)) {
                    Logger::send('worker.upload.run', [
                        'step' => $params['processId'] . ' success file uploaded'
                    ]);
                } else {
                    Logger::send('worker.upload.run', [
                        'step' => $params['processId'] . ' failed file uploaded'
                    ]);
                    Logger::send('faileds', [
                        'process' => $upload
                    ]);
                }
            } else {
                Logger::send('worker.upload.run', [
                    'step' => $params['processId'] . ' not founded'
                ]);
            }
        } catch (\Exception $e) {
            Logger::send('faileds', [
                'process' => $upload
            ]);
        }
    }
}