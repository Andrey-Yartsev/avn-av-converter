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
use Converter\components\Redis;
use Converter\helpers\FileHelper;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixCommand extends Command
{
    use LockableTrait;
    
    protected function configure()
    {
        $this->setName('fix:seed');
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
    
        $file = new \SplFileObject('result.json');
        while (!$file->eof()) {
            $row = $file->fgets();
            $data[] = json_decode($row, true);
        }
        
        foreach ($data as $index => $row) {
            $file = $row['data']['file'];
            $filePath = $file['Location'];
            $keyName = 's3://' . $file['Bucket'] . '/' . $file['Key'];
            $inputSettings = [
                'FileInput' => $keyName,
                'AudioSelectors' => [
                    'Audio Selector 1' => [
                        'Offset' => 0,
                        'DefaultSelection' => 'DEFAULT',
                        'ProgramSelection' => 1,
                    ]
                ],
                'VideoSelector' => [
                    'ColorSpace' => 'FOLLOW',
                    'Rotate' => 'DEGREE_0',
                    'AlphaBehavior' => 'DISCARD',
                ]
            ];
            try {
                list($width, $height) = $driver->getVideoDimensions($filePath);
                if ($width == 0 || $height == 0) {
                    throw new \Exception("Wrong dimensions $width X $height");
                }
            } catch (\Throwable $exception) {
                echo  $exception->getMessage() . PHP_EOL;
                $info = FileHelper::getFileID3($filePath);
                if (empty($info['SourceImageWidth']) && empty($info['SourceImageHeight'])) {
                    continue;
                }
                $width = $info['SourceImageWidth'];
                $height = $info['SourceImageHeight'];
            }
            
            try {
                $watermarkKey = $driver->getWatermark($s3Client, $row['data']['watermark']);
                if ($watermarkKey) {
                    $imageX = $width - 10 - $driver->watermarkInfo['width'];
                    $imageY = $height - 10 - $driver->watermarkInfo['height'];
                    if ($imageX < 0) {
                        $imageX = 0;
                    }
                    if ($imageY < 0) {
                        $imageY = 0;
                    }
                    $inputSettings['ImageInserter']['InsertableImages'][] = [
                        'ImageX' => $imageX,
                        'ImageY' => $imageY,
                        'Layer' => 10,
                        'ImageInserterInput' => $watermarkKey,
                        'Opacity' => 100,
                    ];
                }
                $outputGroup = [
                    'Name' => 'Group',
                    'OutputGroupSettings' => [
                        'Type' => 'FILE_GROUP_SETTINGS',
                        'FileGroupSettings' => [
                            'Destination' => "s3://{$config[$presetName]['video']['s3']['bucket']}/files/{$row['processId']}/{$row['processId']}"
                        ]
                    ],
                    'Outputs' => [],
                ];
                $outputGroup['Outputs'][] = [
                    'Preset' => 'System-Generic_Hd_Mp4_Avc_Aac_16x9_1920x1080p_24Hz_6Mbps',
                    'NameModifier' => '_source',
                    'VideoDescription' => [
                        'Width' => $width,
                        'Height' => $height
                    ]
                ];
                $jobSettings['OutputGroups'][] = $outputGroup;
                $job = $mediaClient->createJob([
                    'Role' => $mediaConfig['role'],
                    'Settings' => [
                        'Inputs' => [$inputSettings],
                        'OutputGroups' => [$outputGroup],
                    ],
                    'Queue' => $mediaConfig['queues'][array_rand($mediaConfig['queues'])],
                ]);
            } catch (\Throwable $e) {
                echo  $e->getMessage() . PHP_EOL;
                continue;
            }
            $job = (array)$job->get('Job');
            if (strtolower($job['Status']) == 'submitted') {
                Logger::send('jobs', [
                    'jobId' => $job['Id'],
                    'processId' => $row['processId'],
                ]);
            }
        }
    }
}