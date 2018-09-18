<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\commands;


use Converter\components\Redis;
use Converter\forms\AmazonForm;
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
        while (true) {
            $uploads = Redis::getInstance()->sMembers('amazon:upload');
            foreach ($uploads as $upload) {
                $params = json_decode($upload, true);
                $form = new AmazonForm();
                $form->setAttributes($params);
                $output->writeln('<info>Catch message #' . $upload . '</info>');
                if ($form->processVideo()) {
                    Redis::getInstance()->sRem('amazon:upload', $upload);
                    $output->writeln('<info>Process #' . $params['processId'] . ' uploaded</info>');
                } else {
                    $output->writeln('<error>' . current($form->getErrors()) . '</error>');
                }
            }
    
        }
        
        $this->release();
    
        return 2;
    }
}