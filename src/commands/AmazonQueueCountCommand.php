<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\commands;

use Converter\components\Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AmazonQueueCountCommand extends Command
{
    protected function configure()
    {
        $this->setName('amazon:queue:count');
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        echo count(Redis::getInstance()->sMembers('amazon:queue'));
        
        return 2;
    }
}