<?php
/**
 * User: pel
 * Date: 14/01/2019
 */

namespace Converter\commands;


use Converter\components\Config;
use Converter\components\drivers\AmazonDriver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UploadCommand extends Command
{
    protected function configure()
    {
        $this->setName('upload');
        $this->addArgument('upload', InputArgument::REQUIRED);
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $upload = $input->getArgument('upload');
        file_put_contents('test.txt', $upload); die;
        $presents = Config::getInstance()->get('presets');
        $params = json_decode($upload, true);
        $presetName = $params['presetName'];
        $output->writeln('<info>Init amazon driver</info>');
        $amazonDriver = new AmazonDriver($presetName, $presents[$presetName]['video']);
        if ($amazonDriver->createJob($params['filePath'], $params['callback'], $params['processId'], $params['watermark'])) {
            $output->writeln('<info>Process #' . $params['processId'] . ' uploaded</info>');
        } else {
            $output->writeln('<error>:(</error>');
        }
    }
}