<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

return [
    'baseUrl' => '',
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379
    ],
    'cloudconverter' => [
        'token' => 'fYu7Qu2yue99aZ_yRBnJM4WJtHDAMGyEef6s_KZAUeqDAy34YkcfmPXb-eFrtf2lgraBOCLtQ38A-no0fdrkjQ'
    ],
    'amazon' => [
        'url' => 'https://avnsocial-dev.s3.amazonaws.com',
        's3' => [
            'region' => 'us-east-1',
            'bucket' => 'avnsocial-dev',
            'key' => 'AKIAJNM2CVRJSS3CFXIA',
            'secret' => '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'
        ],
        'transcoder' => [
            'region' => 'us-east-1',
            'bucket' => 'avnsocial-dev',
            'key' => 'AKIAJQCE37RKJ3OTOIZA',
            'secret' => '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',
            'pipeline' => '1535392359956-nn60uk',
            'preset' => '1535376372750-5aclv6'
        ]
    ]
];