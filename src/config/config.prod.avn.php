<?php
/**
 * User: pel
 * Date: 06/11/2018
 */

return [
    'isProd'  => true,
    'baseUrl' => 'https://convert.avn.com',
    'redis'   => [
        'host'     => '/var/run/redis/redis-server.sock',
        'port'     => null,
        'database' => 1
    ],
    'protect' => [
        'type' => 'cloudfront',
        'key_pair_id' => 'APKAIZQN3TWWCTQ5Z6ZQ',
        'private_key' => '/var/www/html/src/config/pk.avn.cf.pem',
        'expires' => '+1 year',
        'url' => 'https://cdn2-media.avn.com',
    ],
    'presets' => [
        'avn' => [
            'callback' => 'https://stars.avn.com/api2/v2/converter/ready',
            'audio'    => [
                'driver'     => \Converter\components\drivers\AmazonDriver::class,
                'url'        => 'https://avnstars-media.s3.amazonaws.com',
                's3'         => [
                    'region' => 'us-east-1',
                    'bucket' => 'avnstars-media',
                    'key'    => 'AKIAJNM2CVRJSS3CFXIA',
                    'secret' => '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'
                ],
                'transcoder' => [
                    'region'   => 'us-east-1',
                    'bucket'   => 'avnstars-media',
                    'key'      => 'AKIAJQCE37RKJ3OTOIZA',
                    'secret'   => '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',
                    'pipeline' => '1535375850253-dxzlpd',
                    'preset'   => '1351620000001-300020'
                ],
            ],
            'video'    => [
                'driver'     => \Converter\components\drivers\AmazonDriver::class,
                'url'        => 'https://avnstars-media.s3.amazonaws.com',
                'needProtect' => true,
                's3'         => [
                    'region' => 'us-east-1',
                    'bucket' => 'avnstars-media',
                    'key'    => 'AKIAJNM2CVRJSS3CFXIA',
                    'secret' => '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'
                ],
                'transcoder' => [
                    'region'   => 'us-east-1',
                    'bucket'   => 'avnstars-media',
                    'key'      => 'AKIAJQCE37RKJ3OTOIZA',
                    'secret'   => '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',
                    'pipeline' => '1535375850253-dxzlpd',
                    'presets' => [
                        '1579269726017-sc9hr7' => ['name' => 'source', 'height' => null]
                    ]
                ],
                'thumbs' => [
                    'driver'     => \Converter\components\drivers\LocalDriver::class,
                    'engine'     => 'imagick',
                    'maxCount'   => 5,
                    'thumbSizes' => [
                        [
                            'name'   => 'thumb',
                            'width'  => 100,
                            'height' => 100,
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
                'thumbSizes' => [
                    [
                        'name' => 'source',
                        'maxSize' => 3840,
                        'fixRatio' => false,
                    ],
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
                        'blur'    => 10,
                        'watermark' => false,
                        'fixRatio' => false,
                    ]
                ]
            ],
            'storage'  => [
                'driver' => \Converter\components\storages\S3Storage::class,
                'url'    => 'https://avnstars-media.s3.amazonaws.com',
                'region' => 'us-east-1',
                'bucket' => 'avnstars-media',
                'key'    => 'AKIAJNM2CVRJSS3CFXIA',
                'secret' => '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'
            ]
        ],
    ]
];