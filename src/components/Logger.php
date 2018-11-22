<?php
/**
 * User: pel
 * Date: 08/10/2018
 */

namespace Converter\components;

use Psr\Log\LogLevel;

class Logger
{
    /**
     * @param $message
     * @param array $context
     * @param string $level
     */
    public static function send($message, array $context = [], $level = LogLevel::INFO)
    {
        $folder = PUBPATH . '/../logs/' . date('Y') . '/' . date('m') . '/' . date('d') . '/';
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        $f = fopen($folder . $message . '.log', 'at');
        fwrite($f, date('H:i:s') . "\t" . json_encode($context) . PHP_EOL);
        fclose($f);
    }
}