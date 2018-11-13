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
    'graylog' => [
        'facility'   => 'prod_of',
        'connection' => [
            'port' => 1517,
            'host' => '163.237.242.4',
        ]
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
    ]
];