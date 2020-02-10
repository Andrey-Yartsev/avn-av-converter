<?php
/**
 * User: pel
 * Date: 06/11/2018
 */

return [
    'isProd'  => false,
    'baseUrl' => 'https://convert.onlyfans.com',
    'redis'   => [
        'host'     => '127.0.0.1',
        'port'     => 6379,
        'database' => 1
    ],
    'protect' => [
        'type' => 'cloudfront',
        'key_pair_id' => 'APKAJKLR6VB3PTZVDEBA',
        'private_key' => '/var/www/html/converter.retloko.com/logs/pk-cloudfront.pem',
        'expires' => '+1 year',
        'url' => 'https://cdn2.onlyfans.com',
    ],
    'presets' => [
        'of_beta'   => [
            'callback' => 'https://spa.onlyfans.retloko.com/converter/geo',
            'audio' => [
                'driver'       => \Converter\components\drivers\CloudConvertDriver::class,
                'token'        => 'TnjiK5KWTh4PU9ceXIiQ9PoRGK_PXZOyR7whEi3rpAK8mweQJyuq650aWorqA2p78ohq2MYoHH9PjrEkzQEG7w',
                'outputFormat' => 'mp3',
                'needPreviewOnStart' => false,
            ],
            'video'    => [
                'driver'     => \Converter\components\drivers\AmazonDriver::class,
                'url'        => 'https://of2transcoder.s3-accelerate.amazonaws.com',
                'needProtect' => true,
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
                    'preset'   => '1579715526552-cf3kd8'
                ],
                'thumbs' => [
                    'driver'     => \Converter\components\drivers\LocalDriver::class,
                    'engine'     => 'imagick',
                    'maxCount'   => 1,
                    'thumbSizes' => [
                        [
                            'name'   => 'thumb',
                            'maxSize' => 300,
                        ],
                    ]
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
                            'width'  => 150,
                            'height' => 150,
                        ],
                        [
                            'name'    => 'locked',
                            'maxSize' => 30,
                            'blur'    => 1,
                            'watermark' => false,
                        ]
                    ]
                ],
                'needPreviewOnStart' => false,
            ],
            'image'    => [
                'driver'     => \Converter\components\drivers\LocalDriver::class,
                'engine'     => 'imagick',
                'thumbSizes' => [
                    [
                        'name'   => 'source',
                        'width'  => 3840,
                        'height' => 2160,
                    ],
                    [
                        'name'    => 'preview',
                        'maxSize' => 960,
                    ],
                    [
                        'name'   => 'square_preview',
                        'width'  => 960,
                        'height' => 960,
                    ],
                    [
                        'name'   => 'thumb',
                        'width'  => 300,
                        'height' => 300,
                    ],
                    [
                        'name'    => 'locked',
                        'maxSize' => 30,
                        'blur'    => 1,
                        'watermark' => false,
                    ]
                ],
                'needProtect' => true,
                'thumbs' => [
                    'driver'     => \Converter\components\drivers\LocalDriver::class,
                    'engine'     => 'imagick',
                    'thumbSizes' => [
                        [
                            'name'   => 'thumb',
                            'maxSize' => 300,
                        ],
                    ]
                ],
                'needPreviewOnStart' => false,
            ],
            'storage'  => [
                'driver' => \Converter\components\storages\S3Storage::class,
                'url'    => 'https://of2media.s3.amazonaws.com',
                'region' => 'us-east-1',
                'bucket' => 'of2media',
                'key'    => 'AKIAIEGIIC3WZYGPIPCA',
                'secret' => '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'
            ]
        ],
    ]
];