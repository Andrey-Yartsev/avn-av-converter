<?php
/**
 * User: pel
 * Date: 14/09/2018
 */

namespace Converter\forms;


use CloudConvert\Api;
use Converter\components\Form;

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
    public function start()
    {
        $api = new Api('token');
        
        $process = $api->createProcess([
            'inputformat' => 'mp4', // @TODO replace on real
            'outputformat' => 'mp4',
        ]);
        $process->start([
            'outputformat' => 'mp4',
            'converteroptions' => [
                "command" => "-i {INPUTFILE} {OUTPUTFILE} -f mp4 -vcodec libx264 -movflags +faststart -pix_fmt yuv420p -preset veryslow -b:v 512k -maxrate 512k -profile:v high -level 4.2 -acodec aac -threads 0",
            ],
            'input' => 'download',
            'file' => $this->filePath,
            'callback' => 'callback_url_here' // @TODO replace on real
        ]);
        // send to redis task
        return rand(10000, 99999);
    }
}