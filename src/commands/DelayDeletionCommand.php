<?php
/**
 * User: pel
 * Date: 2019-07-18
 */

namespace Converter\commands;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DelayDeletionCommand extends Command
{
    protected function configure()
    {
        $this->setName('worker:deletion');
        $this->addArgument('path', InputArgument::REQUIRED);
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $filePath = $input->getArgument('path');
        if (file_exists($filePath)) {
            sleep(120);
            @unlink($filePath);
        }
    }
}