<?php

$cfg = [
    'isProd' => true,
    'baseUrl' => 'https://convert.onlyfans.com',
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 1
    ],
    'protect' => [
        'type' => 'cloudfront',
        'key_pair_id' => 'APKAJZU4IULC2OKULHGA',
        'private_key' => '/var/www/convert/src/config/pk.of.cf.pem',
        'expires' => '+1 year',
        'url' => 'https://cdn2.onlyfans.com',
    ],
    'presets' => [
        'of_beta' => [
            'callback' => 'https://onlyfans.com/converter/geo',
            'audio' => [
                'driver' => \Converter\components\drivers\CloudConvertDriver::class,
                'token' => 'TnjiK5KWTh4PU9ceXIiQ9PoRGK_PXZOyR7whEi3rpAK8mweQJyuq650aWorqA2p78ohq2MYoHH9PjrEkzQEG7w',
                'outputFormat' => 'mp3',
                'needPreviewOnStart' => false,
            ],
            'video' => [
                'driver' => \Converter\components\drivers\AmazonDriver::class,
                'url' => 'https://of2transcoder.s3-accelerate.amazonaws.com',
                'needProtect' => true,
                's3' => [
                    'region' => 'us-east-1',
                    'bucket' => 'of2transcoder',
                    'key' => 'AKIAUSX4CWPPFHYXZ6PQ',
                    'secret' => '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'
                ],
                'transcoder' => [
                    'region' => 'us-east-1',
                    'bucket' => 'of2transcoder',
                    'key' => 'AKIAUSX4CWPPFHYXZ6PQ',
                    'secret' => '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',
                    'pipeline' => '1542729803060-wvvyxu',
                    'presets' => [
                        '1579715526552-cf3kd8' => ['name' => 'source'],
                        '1351620000001-000010' => ['name' => '720p'],
                        '1351620000001-000040' => ['name' => '360p'],
                    ]
                ],
                'thumbs' => [
                    'driver' => \Converter\components\drivers\LocalDriver::class,
                    'engine' => 'imagick',
                    'maxCount' => 1,
                    'thumbSizes' => [
                        [
                            'name' => 'thumb',
                            'maxSize' => 300,
                            'fixRatio' => false,
                        ],
                    ]
                ],
                'previews' => [
                    'driver' => \Converter\components\drivers\LocalDriver::class,
                    'engine' => 'imagick',
                    'thumbSizes' => [
                        [
                            'name' => 'preview',
                            'maxSize' => 760,
                            'fixRatio' => false,
                        ],
                        [
                            'name' => 'square_preview',
                            'width' => 960,
                            'height' => 960,
                        ],
                        [
                            'name' => 'thumb',
                            'width' => 150,
                            'height' => 150,
                        ],
                        [
                            'name' => 'locked',
                            'maxSize' => 30,
                            'blur' => 1,
                            'watermark' => false,
                            'fixRatio' => false,
                        ]
                    ]
                ],
                'needPreviewOnStart' => false,
            ],
            'image' => [
                'driver' => \Converter\components\drivers\LocalDriver::class,
                'engine' => 'imagick',
                'thumbSizes' => [
                    [
                        'name' => 'source',
                        'maxSize' => 3840,
                        'fixRatio' => false,
                    ],
                    [
                        'name' => 'preview',
                        'maxSize' => 960,
                        'fixRatio' => false,
                    ],
                    [
                        'name' => 'square_preview',
                        'width' => 960,
                        'height' => 960,
                    ],
                    [
                        'name' => 'thumb',
                        'width' => 300,
                        'height' => 300,
                    ],
                    [
                        'name' => 'locked',
                        'maxSize' => 30,
                        'blur' => 1,
                        'watermark' => false,
                        'fixRatio' => false,
                    ]
                ],
                'needProtect' => true,
                'thumbs' => [
                    'driver' => \Converter\components\drivers\LocalDriver::class,
                    'engine' => 'imagick',
                    'thumbSizes' => [
                        [
                            'name' => 'thumb',
                            'maxSize' => 300,
                            'fixRatio' => false,
                        ],
                    ]
                ],
                'needPreviewOnStart' => false,
            ],
            'storage' => [
                'driver' => \Converter\components\storages\S3Storage::class,
                'url' => 'https://of2media.s3.amazonaws.com',
                'region' => 'us-east-1',
                'bucket' => 'of2media',
                'key' => 'AKIAUSX4CWPPFHYXZ6PQ',
                'secret' => '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'
            ]
        ],
        'of_geo_big' => [
            'callback' => 'https://onlyfans.com/converter/geo',
            'audio' => [
                'driver' => \Converter\components\drivers\CloudConvertDriver::class,
                'token' => 'TnjiK5KWTh4PU9ceXIiQ9PoRGK_PXZOyR7whEi3rpAK8mweQJyuq650aWorqA2p78ohq2MYoHH9PjrEkzQEG7w',
                'outputFormat' => 'mp3',
            ],
            'video' => [
                'driver' => \Converter\components\drivers\AmazonDriver::class,
                'url' => 'https://of2transcoder.s3.amazonaws.com',
                's3' => [
                    'region' => 'us-east-1',
                    'bucket' => 'of2transcoder',
                    'key' => 'AKIAUSX4CWPPFHYXZ6PQ',
                    'secret' => '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'
                ],
                'transcoder' => [
                    'region' => 'us-east-1',
                    'bucket' => 'of2transcoder',
                    'key' => 'AKIAUSX4CWPPFHYXZ6PQ',
                    'secret' => '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',
                    'pipeline' => '1542729803060-wvvyxu',
                    'preset' => '1569329259686-o0zwis'
                ],
                'previews' => [
                    'driver' => \Converter\components\drivers\LocalDriver::class,
                    'engine' => 'imagick',
                    'thumbSizes' => [
                        [
                            'name' => 'preview',
                            'maxSize' => 760,
                        ],
                        [
                            'name' => 'thumb',
                            'width' => 150,
                            'height' => 150,
                        ],
                        [
                            'name' => 'locked',
                            'maxSize' => 30,
                            'blur' => 10
                        ]
                    ]
                ]
            ],
            'image' => [
                'driver' => \Converter\components\drivers\LocalDriver::class,
                'engine' => 'imagick',
                'withSource' => true,
                'thumbSizes' => [
                    [
                        'name' => 'source',
                        'width' => 3840,
                        'height' => 2160,
                    ],
                    [
                        'name' => 'preview',
                        'maxSize' => 960,
                    ],
                    [
                        'name' => 'square_preview',
                        'width' => 960,
                        'height' => 960,
                    ],
                    [
                        'name' => 'thumb',
                        'width' => 300,
                        'height' => 300,
                    ],
                    [
                        'name' => 'locked',
                        'maxSize' => 30,
                        'blur' => 10
                    ]
                ]
            ],
            'storage' => [
                'driver' => \Converter\components\storages\S3Storage::class,
                'url' => 'https://s3.amazonaws.com/of2media',
                'region' => 'us-east-1',
                'bucket' => 'of2media',
                'key' => 'AKIAUSX4CWPPFHYXZ6PQ',
                'secret' => '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'
            ]
        ],
        'of_geo' => [
            'callback' => 'https://onlyfans.com/converter/geo?beta=a919992d95bbfafb47b2c6f5b0109e73',
            'audio' => [
                'driver' => \Converter\components\drivers\CloudConvertDriver::class,
                'token' => 'TnjiK5KWTh4PU9ceXIiQ9PoRGK_PXZOyR7whEi3rpAK8mweQJyuq650aWorqA2p78ohq2MYoHH9PjrEkzQEG7w',
                'outputFormat' => 'mp3',
            ],
            'video' => [
                'driver' => \Converter\components\drivers\CloudConvertDriver::class,
                'token' => 'TnjiK5KWTh4PU9ceXIiQ9PoRGK_PXZOyR7whEi3rpAK8mweQJyuq650aWorqA2p78ohq2MYoHH9PjrEkzQEG7w',
                'outputFormat' => 'mp4',
                'command' => "-i {INPUTFILE} {watermark} {OUTPUTFILE} -c:v libx264 -preset slow -crf 23 -profile:v baseline -level 3.0 -b:v 250k -maxrate 250k -bufsize 500k -movflags +faststart -pix_fmt yuv420p -c:a libfdk_aac -b:a 128k",
                'previews' => [
                    'driver' => \Converter\components\drivers\LocalDriver::class,
                    'engine' => 'imagick',
                    'thumbSizes' => [
                        [
                            'name' => 'preview',
                            'maxSize' => 760,
                        ],
                        [
                            'name' => 'thumb',
                            'width' => 150,
                            'height' => 150,
                        ],
                        [
                            'name' => 'locked',
                            'maxSize' => 30,
                            'blur' => 10
                        ]
                    ]
                ]
            ],
            'image' => [
                'driver' => \Converter\components\drivers\LocalDriver::class,
                'engine' => 'imagick',
                'withSource' => true,
                'thumbSizes' => [
                    [
                        'name' => 'source',
                        'width' => 3840,
                        'height' => 2160,
                    ],
                    [
                        'name' => 'preview',
                        'maxSize' => 960,
                    ],
                    [
                        'name' => 'square_preview',
                        'width' => 960,
                        'height' => 960,
                    ],
                    [
                        'name' => 'thumb',
                        'width' => 300,
                        'height' => 300,
                    ],
                    [
                        'name' => 'locked',
                        'maxSize' => 30,
                        'blur' => 10
                    ]
                ]
            ],
            'storage' => [
                'driver' => \Converter\components\storages\S3Storage::class,
                'url' => 'https://s3.amazonaws.com/of2media',
                'region' => 'us-east-1',
                'bucket' => 'of2media',
                'key' => 'AKIAUSX4CWPPFHYXZ6PQ',
                'secret' => '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'
            ]
        ],
    ]
];

$cfg['presets']['of_beta2'] = $cfg['presets']['of_beta'];
$cfg['presets']['of_beta2']['callback'] .= '?beta=a919992d95bbfafb47b2c6f5b0109e73';

return $cfg;