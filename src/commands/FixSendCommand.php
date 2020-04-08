<?php
/**
 * User: pel
 * Date: 2020-04-07
 */

namespace Converter\commands;


use Aws\Credentials\Credentials;
use Aws\MediaConvert\MediaConvertClient;
use Aws\S3\S3Client;
use Converter\components\Config;
use Converter\components\drivers\Driver;
use Converter\components\drivers\MediaConvertDriver;
use Converter\components\Logger;
use Converter\components\storages\S3Storage;
use Converter\helpers\FileHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixSendCommand extends Command
{
    use LockableTrait;
    
    protected function configure()
    {
        $this->setName('fix:send');
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $presetName = 'of_beta';
        $config = Config::getInstance()->get('presets', []);
        /** @var MediaConvertDriver $driver */
        $driver = Driver::loadByConfig($presetName, $config[$presetName]['video']);
        $mediaConfig = $config[$presetName]['video']['mediaConfig'];
        $mediaClient = new MediaConvertClient([
            'version' => 'latest',
            'region' => $mediaConfig['region'],
            'credentials' => new Credentials($mediaConfig['key'], $mediaConfig['secret']),
            'endpoint' => $mediaConfig['endpoint']
        ]);
        $s3Client = new S3Client([
            'version' => 'latest',
            'region'  => $config[$presetName]['video']['s3']['region'],
            'credentials' => [
                'key' => $config[$presetName]['video']['s3']['key'],
                'secret' => $config[$presetName]['video']['s3']['secret']
            ]
        ]);
        $data = [];

        $file = new \SplFileObject('jobs.log');
        while (!$file->eof()) {
            $row = $file->fgets();
            $row = substr($row, strpos($row, '{'));
            $data[] = json_decode($row, true);
        }
        
        foreach ($data as $index2 => $row) {
            $retry = true;
            do {
                try {
                    echo 'Try #' . $index2 . ' ' . $row['processId'] . PHP_EOL;
                    $response = $mediaClient->getJob(['Id' => $row['jobId']]);
                    $jobData = (array)$response->get('Job');
                    if (strtolower($jobData['Status']) != 'complete') {
                        echo $jobData['Status'] . '=' . $jobData['JobPercentComplete'] . PHP_EOL;
                        break;
                    }
        
                    $files = [];
                    $outputDetails = $jobData['OutputGroupDetails'][0]['OutputDetails'] ?? [];
                    $outputs = $jobData['Settings']['OutputGroups'][0]['Outputs'] ?? [];
                    $path = $jobData['Settings']['OutputGroups'][0]['OutputGroupSettings']['FileGroupSettings']['Destination'] ?? null;
                    $path = str_replace("s3://{$config[$presetName]['video']['s3']['bucket']}/", '', $path);
                    $sourcePath = null;
                    foreach ($outputDetails as $index => $outputDetail) {
                        if (empty($outputs[$index])) {
                            echo 'skip';
                            continue;
                        }
                        $nameModifier = $outputs[$index]['NameModifier'];
                        $files[] = [
                            'duration' => round($outputDetail['DurationInMs'] / 1000),
                            'height'   => $outputDetail['VideoDetails']['HeightInPx'],
                            'width'    => $outputDetail['VideoDetails']['WidthInPx'],
                            'url'      => $config[$presetName]['video']['url'] . '/' . $path . $nameModifier . '.mp4',
                            'path'     => $path . $nameModifier . '.mp4',
                            'name'     => substr($nameModifier, 1),
                            'presetId' => $outputs[$index]['Preset'],
                        ];
                        if ($nameModifier == '_source') {
                            $sourcePath = $path . $nameModifier . '.mp4';
                        }
                    }
                    $file = current($files);
                    $storage = $driver->getStorage();
                    if ($storage instanceof S3Storage && $storage->bucket != $config[$presetName]['video']['s3']['bucket']) {
                        try {
                            $targetPath = $storage->generatePath($file['path']);
                            $s3Client->copyObject([
                                'Bucket'     => $storage->bucket,
                                'Key'        => $targetPath,
                                'CopySource' => $config[$presetName]['video']['s3']['bucket'] . '/' . $file['path'],
                            ]);
                            $file['url'] = $storage->url . '/' . $targetPath;
                            echo $row['processId'] . '=>' . $file['url'] . PHP_EOL;
                            Logger::send('123', ['step' => $row['processId'] . '=>' . $file['url']]);
                            $retry = false;
                        } catch (\Throwable $exception) {
                            echo $exception->getMessage();
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    echo $e->getMessage();
                    sleep(2);
                    $retry = true;
                }
            } while ($retry);
            
        }
    }
}