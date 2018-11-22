<?php
/**
 * User: pel
 * Date: 06/11/2018
 */

return [
    'isProd' => true,
    'baseUrl' => 'https://convert.onlyfans.com',
    'redis'   => [
        'host'     => '/var/run/redis/redis-server.sock',
        'port'     => null,
        'database' => 1
    ],
    'presets' => [
        'of' => [
            'video' => [
                'driver'       => \Converter\components\drivers\CloudConvertDriver::class,
                'withOutSave'  => true,
                'token'        => 'TnjiK5KWTh4PU9ceXIiQ9PoRGK_PXZOyR7whEi3rpAK8mweQJyuq650aWorqA2p78ohq2MYoHH9PjrEkzQEG7w',
                'outputFormat' => 'mp4',
                'command'      => "-i {INPUTFILE} {watermark} {OUTPUTFILE} -c:v libx264 -preset slow -crf 23 -profile:v baseline -level 3.0 -b:v 250k -maxrate 250k -bufsize 500k -movflags +faststart -pix_fmt yuv420p -c:a libfdk_aac -b:a 128k"
            ]
        ],
        'ofamazon' => [
            'video' => [
                'driver' => \Converter\components\drivers\AmazonDriver::class,
                'url'        => 'https://of2transcoder.s3.amazonaws.com',
                's3'         => [
                    'region' => 'us-east-1',
                    'bucket' => 'of2transcoder',
                    'key'    => 'AKIAIEGIIC3WZYGPIPCA',
                    'secret' => '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'
                ],
                'transcoder' => [
                    'region'   => 'us-east-1',
                    'bucket'   => 'of2transcoder',
                    'key'      => 'AKIAIEGIIC3WZYGPIPCA',
                    'secret'   => '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',
                    'pipeline' => '1542729803060-wvvyxu',
                    'preset'   => '1542903430885-ic9kqe'
                ]
            ]
        ],
    ]
];