<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\commands;


use Converter\components\Config;
use Converter\components\drivers\AmazonDriver;
use Converter\components\Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AmazonUploadCommand extends Command
{
    use LockableTrait;
    
    protected function configure()
    {
        $this->setName('amazon:upload');
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('Process already working!');
            return 1;
        }
    
        $presents = Config::getInstance()->get('presets');
        Redis::getInstance()->subscribe(['amazon:upload'], function ($redis, $channel, $msg) use ($output, $presents) {
            $params = json_decode($msg, true);
            $output->writeln('<info>Catch ' . $msg . '</info>');
            $presetName = $params['presetName'];
            if (empty($presents[$presetName]) || empty($presents[$presetName]['video'])) {
                return false;
            }
    
            $output->writeln('<info>Init amazon driver</info>');
            $amazonDriver = new AmazonDriver($presetName, $presents[$presetName]['video']);
            if ($amazonDriver->createJob($params['filePath'], $params['callback'], $params['processId'])) {
                Redis::getInstance()->sRem('amazon:upload', $msg);
                $output->writeln('<info>Process #' . $params['processId'] . ' uploaded</info>');
            } else {
                $output->writeln('<error>:(</error>');
            }
        });
        
        $this->release();
    
        return 2;
    }
}