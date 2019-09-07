<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\commands;


use Converter\components\Config;
use Converter\components\Logger;
use Converter\components\Redis;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

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
        /** @var Process[] $processes */
        $processes = [];
        while (true) {
            $uploads = Redis::getInstance()->sRandMember('amazon:upload', 10);
            foreach ($uploads as $upload) {
                $params = json_decode($upload, true);
                $output->writeln('<info>Catch ' . $upload . '</info>');
                $presetName = $params['presetName'];
                $processId = $params['processId'];
                if (empty($presents[$presetName]) || empty($presents[$presetName]['video'])) {
                    Redis::getInstance()->sRem('amazon:upload', $upload);
                    continue;
                }
                if (count($processes) < 10) {
                    Redis::getInstance()->sRem('amazon:upload', $upload);
                    $command = __DIR__ . '/../../console/index.php worker:upload \'' . $upload . '\'';
                    $process = new Process(['php', $command]);
                    Logger::send('worker.upload.run', [
                        'process' => $processId,
                        'step' => 'start',
                        'command' => $command,
                        'countWorkers' => count($processes)
                    ]);
                    $process->start();
                    $processes[$processId] = $process;
                }
            }
            sleep(1);
            foreach ($processes as $pid => $process) {
                if (!$process->isRunning()) {
                    unset($processes[$pid]);
                    Logger::send('worker.upload.run', [
                        'process' => $processId,
                        'step' => 'Done',
                        'countWorkers' => count($processes)
                    ]);
                }
            }
        }

        $this->release();

        return 2;
    }
}
