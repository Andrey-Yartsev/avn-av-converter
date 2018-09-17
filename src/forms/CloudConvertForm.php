<?php
/**
 * User: pel
 * Date: 14/09/2018
 */

namespace Converter\forms;


use CloudConvert\Api;
use Converter\components\Config;
use Converter\components\Form;
use Converter\components\Redis;

class CloudConvertForm extends Form
{
    public $filePath;
    public $callback;
    
    /**
     * @return int
     * @throws \CloudConvert\Exceptions\ApiException
     * @throws \CloudConvert\Exceptions\InvalidParameterException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function processVideo()
    {
        $rules = [
            'required' => ['filePath', 'callback'],
            'url' => ['filePath', 'callback'],
        ];
        
        if (!$this->validate($rules)) {
            return false;
        }
        $cloudConvertConfig = Config::getInstance()->get('cloudconverter');
        
        $api = new Api($cloudConvertConfig['token']);
        $pathParts = pathinfo($this->filePath);
        $process = $api->createProcess([
            'inputformat' => $pathParts['extension'],
            'outputformat' => 'mp4',
        ]);
        $process->start([
            'outputformat' => 'mp4',
            'converteroptions' => [
                'command' => "-i {INPUTFILE} {OUTPUTFILE} -f mp4 -vcodec libx264 -movflags +faststart -pix_fmt yuv420p -preset veryslow -b:v 512k -maxrate 512k -profile:v high -level 4.2 -acodec aac -threads 0",
            ],
            'input' => 'download',
            'file' => $this->filePath,
            'callback' => Config::getInstance()->get('baseUrl') . '/video/cloudconvert/callback'
        ]);
        Redis::getInstance()->set('cc:' . $process->id, json_encode(['callback' => $this->callback]));
        return $process->id;
    }
}