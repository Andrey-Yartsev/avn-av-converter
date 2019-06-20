<?php

return [
    'isProd'  => true,
    'baseUrl' => 'https://xx.convert.onlyfans.com',
    'redis'   => [
        'host'     => '127.0.0.1',
        'port'     => 6379,
        'database' => 1
    ],
    'log'     => [
        'driver'   => \Converter\components\logs\Graylog::class,
        'host'     => '163.237.242.4',
        'port'     => 1517,
        'facility' => 'converter-dev',
    ],
    'presets' => [
        'of_geo'   => [
            'callback' => 'https://onlyfans.com/converter/ready',
            'audio' => [
                'driver'       => \Converter\components\drivers\CloudConvertDriver::class,
                'withOutSave'  => true,
                'token'        => 'TnjiK5KWTh4PU9ceXIiQ9PoRGK_PXZOyR7whEi3rpAK8mweQJyuq650aWorqA2p78ohq2MYoHH9PjrEkzQEG7w',
                'outputFormat' => 'mp3',
            ],
            'video'    => [
                'driver'     => \Converter\components\drivers\AmazonDriver::class,
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
                ],
                'previews'   => [
                    'driver'     => \Converter\components\drivers\LocalDriver::class,
                    'engine'     => 'imagick',
                    'thumbSizes' => [
                        [
                            'name'    => 'preview',
                            'maxSize' => 760,
                        ],
                        [
                            'name'   => 'thumb',
                            'width'  => 440,
                            'height' => 440,
                        ],
                        [
                            'name'    => 'locked',
                            'maxSize' => 50,
                            'blur'    => 10
                        ]
                    ]
                ]
            ],
            'image'    => [
                'driver'     => \Converter\components\drivers\LocalDriver::class,
                'engine'     => 'imagick',
                'withSource' => true,
                'thumbSizes' => [
                    [
                        'name'    => 'preview',
                        'maxSize' => 760,
                    ],
                    [
                        'name'   => 'thumb',
                        'width'  => 440,
                        'height' => 440,
                    ],
                    [
                        'name'    => 'locked',
                        'maxSize' => 50,
                        'blur'    => 10
                    ]
                ]
            ],
            'storage'  => [
                'driver' => \Converter\components\storages\S3Storage::class,
                'url'    => 'https://of2transcoder.s3.amazonaws.com',
                'region' => 'us-east-1',
                'bucket' => 'of2media',
                'key'    => 'AKIAIEGIIC3WZYGPIPCA',
                'secret' => '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'
            ]
        ],
    ],
];
