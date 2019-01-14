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
        while (true) {
            $uploads = Redis::getInstance()->sRandMember('amazon:upload', 10);
            if (empty($uploads)) {
                sleep(1);
            } else {
                foreach ($uploads as $upload) {
                    $params = json_decode($upload, true);
                    $output->writeln('<info>Catch ' . $upload . '</info>');
                    $presetName = $params['presetName'];
                    if (empty($presents[$presetName]) || empty($presents[$presetName]['video'])) {
                        Redis::getInstance()->sRem('amazon:upload', $upload);
                        continue;
                    }
                    if (exec('ps aux | grep worker:upload | wc -l') < 10) {
                        Redis::getInstance()->sRem('amazon:upload', $upload);
                        exec('php ' . __DIR__ . '/../../console/index.php worker:upload ' . $upload . ' &');
                    }
                }
            }
        }

        $this->release();

        return 2;
    }
}
