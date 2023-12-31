<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

return [
    'isProd'  => false,
    'baseUrl' => 'https://converter.imageservice.me',
    'redis'   => [
        'host'     => '127.0.0.1',
        'port'     => 6379,
        'database' => 1
    ],
    'presets' => [
        'team' => [
            'callback' => 'https://onmyteam.com/api2/v2/converter/ready',
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
                            'name'   => 'preview',
                            'width'  => null,
                            'height' => 760,
                        ], [
                            'name'   => 'thumb',
                            'width'  => 440,
                            'height' => 440,
                        ], [
                            'name'   => 'locked',
                            'width'  => 50,
                            'height' => 50,
                            'blur'   => 10
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
                    ], [
                        'name'   => 'thumb',
                        'width'  => 440,
                        'height' => 440,
                    ], [
                        'name'    => 'locked',
                        'maxSize' => 50,
                        'blur'    => 10
                    ]
                ]
            ],
            'storage'  => [
                'driver' => \Converter\components\storages\EllipticsStorage::class,
                'bucket' => 'dev',
                'url'    => 'https://storage.onmyteam.com'
            ]
        ],
        'avn'  => [
            'video' => [
                'driver'     => \Converter\components\drivers\AmazonDriver::class,
                'url'        => 'https://avnsocial-media.s3.amazonaws.com',
                's3'         => [
                    'region' => 'us-east-1',
                    'bucket' => 'avnsocial-media',
                    'key'    => 'AKIAJNM2CVRJSS3CFXIA',
                    'secret' => '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'
                ],
                'transcoder' => [
                    'region'   => 'us-east-1',
                    'bucket'   => 'avnsocial-media',
                    'key'      => 'AKIAJQCE37RKJ3OTOIZA',
                    'secret'   => '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',
                    'pipeline' => '1535375850253-dxzlpd',
                    'preset'   => '1535376372750-5aclv6'
                ]
            ]
        ],
//        'chat'     => [
//            'driver'       => \Converter\components\drivers\CloudConvertDriver::class,
//            'token'        => 'fYu7Qu2yue99aZ_yRBnJM4WJtHDAMGyEef6s_KZAUeqDAy34YkcfmPXb-eFrtf2lgraBOCLtQ38A-no0fdrkjQ',
//            'outputFormat' => 'mp4',
//            'command'      => "-i {INPUTFILE} {OUTPUTFILE} -f mp4 -vcodec libx264 -movflags +faststart -pix_fmt yuv420p -preset veryslow -b:v 512k -maxrate 512k -profile:v high -level 4.2 -acodec aac -threads 0",
//            'storage'      => [
//                'driver' => \Converter\components\storages\EllipticsStorage::class,
//                'bucket' => 'c1',
//                'url'    => 'https://storage.chatservice.me'
//            ]
//        ],
//        'teletest' => [
//            'driver'       => \Converter\components\drivers\CloudConvertDriver::class,
//            'token'        => 'fYu7Qu2yue99aZ_yRBnJM4WJtHDAMGyEef6s_KZAUeqDAy34YkcfmPXb-eFrtf2lgraBOCLtQ38A-no0fdrkjQ',
//            'outputFormat' => 'mp4',
//            'command'      => "-i {INPUTFILE} {OUTPUTFILE} -f mp4 -vcodec libx264 -movflags +faststart -pix_fmt yuv420p -preset veryslow -b:v 512k -maxrate 512k -profile:v high -level 4.2 -acodec aac -threads 0",
//            'storage'      => [
//                'driver' => \Converter\components\storages\EllipticsStorage::class,
//                'bucket' => 'dev/teletest',
//                'url'    => 'https://storage.view.me'
//            ]
//        ]
    ],
];