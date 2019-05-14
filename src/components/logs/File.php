<?php
/**
 * User: pel
 * Date: 2019-05-14
 */

namespace Converter\components\logs;

use Psr\Log\LogLevel;

class File
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