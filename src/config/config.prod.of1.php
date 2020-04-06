<?php

$cfg = require(__DIR__ . '/config.prod.of.php');

$cfg['baseUrl'] = 'https://convert.onlyfans.com';
$cfg['presets']['of_beta']['video']['transcoder']['pipeline']
    = $cfg['presets']['of_geo_big']['video']['transcoder']['pipeline']
    = '1542729803060-wvvyxu';
$cfg['presets']['of_beta']['video'] = [
    'driver' => \Converter\components\drivers\MediaConvertDriver::class,
    'needProtect' => true,
    's3' => [
        'region' => 'us-east-1',
        'bucket' => 'of2transcoder',
        'key' => 'AKIAUSX4CWPPFHYXZ6PQ',
        'secret' => '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'
    ],
    'url' => 'https://of2transcoder.s3-accelerate.amazonaws.com',
    'mediaConfig' => [
        'region' => 'us-east-1',
        'role' => 'arn:aws:iam::315135013854:role/MediaConvert',
        'queues' => [
            'arn:aws:mediaconvert:us-east-1:315135013854:queues/Default',
            'arn:aws:mediaconvert:us-east-1:315135013854:queues/of1',
            'arn:aws:mediaconvert:us-east-1:315135013854:queues/of2',
            'arn:aws:mediaconvert:us-east-1:315135013854:queues/of3',
            'arn:aws:mediaconvert:us-east-1:315135013854:queues/of4',
            'arn:aws:mediaconvert:us-east-1:315135013854:queues/of5',
        ],
        'key' => 'AKIAUSX4CWPPFHYXZ6PQ',
        'secret' => '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',
        'endpoint' => 'https://q25wbt2lc.mediaconvert.us-east-1.amazonaws.com',
        'sourcePresets' => [
            'System-Generic_Uhd_Mp4_Hevc_Aac_16x9_3840x2160p_24Hz_8Mbps' => ['name' => 'source', 'height' => 2160],
            'System-Generic_Hd_Mp4_Avc_Aac_16x9_1920x1080p_24Hz_6Mbps' => ['name' => 'source', 'height' => 1080],
            'System-Generic_Hd_Mp4_Avc_Aac_16x9_1280x720p_50Hz_6.0Mbps' => ['name' => 'source', 'height' => 720],
            'System-Generic_Sd_Mp4_Avc_Aac_4x3_640x480p_24Hz_1.5Mbps' => ['name' => 'source', 'height' => 480],
            'System-Generic_Sd_Mp4_Avc_Aac_16x9_Sdr_640x360p_30Hz_0.8Mbps_Qvbr_Vq7' => ['name' => 'source', 'height' => 360],
        ],
        'presets' => [
            'System-Generic_Hd_Mp4_Avc_Aac_16x9_1280x720p_50Hz_6.0Mbps' => ['name' => '720p', 'height' => 720],
            'Generic_Sd_Mp4_Avc_Aac_4x3_320x240p_24Hz_1.5Mbps' => ['name' => '240p', 'height' => 240],
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
                'blur' => 10,
                'watermark' => false,
                'fixRatio' => false,
            ]
        ]
    ],
    'needPreviewOnStart' => false,
];
return $cfg;