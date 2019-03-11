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
    'presets' => [
        'avn' => [
            'callback' => 'https://stars.avn.com/api2/v2/converter/ready',
            'video'    => [
                'driver'     => \Converter\components\drivers\AmazonDriver::class,
                'url'        => 'https://avnsocial-media.s3.amazonaws.com',
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
                    'preset'   => '1535376372750-5aclv6'
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
            'video'    => [
                'driver'       => \Converter\components\drivers\CloudConvertDriver::class,
                'token'        => 'fYu7Qu2yue99aZ_yRBnJM4WJtHDAMGyEef6s_KZAUeqDAy34YkcfmPXb-eFrtf2lgraBOCLtQ38A-no0fdrkjQ',
                'outputFormat' => 'mp4',
                'command'      => "-i {INPUTFILE} {OUTPUTFILE} -f mp4 -vcodec libx264 -movflags +faststart -pix_fmt yuv420p -preset veryslow -b:v 512k -maxrate 512k -profile:v high -level 4.2 -acodec aac -threads 0",
                'previews'     => [
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
                'url'    => 'https://avnstars-media.s3.amazonaws.com',
                'region' => 'us-east-1',
                'bucket' => 'avnstars-media',
                'key'    => 'AKIAJNM2CVRJSS3CFXIA',
                'secret' => '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'
            ]
        ],
    ]
];