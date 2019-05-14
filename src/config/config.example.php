<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

return [
    'baseUrl' => 'https://converter.retloko.com',
    'redis'   => [
        'host'     => '127.0.0.1',
        'port'     => 6379,
        'database' => 1
    ],
    'graylog' => [
        'facility'   => 'dev',
        'connection' => [
            'port' => 1517,
            'host' => '163.237.242.4',
        ]
    ],
    'log' => [
        'driver' => \Converter\components\logs\File::class
    ],
    'presets' => [
        'test2'  => [
            'driver'       => \Converter\components\drivers\CloudConvertDriver::class,
            'token'        => 'fYu7Qu2yue99aZ_yRBnJM4WJtHDAMGyEef6s_KZAUeqDAy34YkcfmPXb-eFrtf2lgraBOCLtQ38A-no0fdrkjQ',
            'outputFormat' => 'mp4',
            'command'      => "-i {INPUTFILE} {OUTPUTFILE} -f mp4 -vcodec libx264 -movflags +faststart -pix_fmt yuv420p -preset veryslow -b:v 512k -maxrate 512k -profile:v high -level 4.2 -acodec aac -threads 0",
            'storage'      => [
                'driver' => \Converter\components\storages\EllipticsStorage::class,
                'bucket' => '',
                'url'    => ''
            ]
        ]
    ],
];