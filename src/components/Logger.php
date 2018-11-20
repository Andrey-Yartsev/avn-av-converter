<?php
/**
 * User: pel
 * Date: 08/10/2018
 */

namespace Converter\components;

use Psr\Log\LogLevel;

class Logger
{
    static protected $logs = [];
    static protected $id = null;
    
    /**
     * @param $message
     * @param array $context
     * @param string $level
     */
    public static function send($message, array $context = [], $level = LogLevel::INFO)
    {
        if (!self::$id && isset($context['id'])) {
            self::$id = $context['id'];
        }
        self::$logs[] = [
            'message' => $message,
            'context' => json_encode($context)
        ];
    }
    
    public function __destruct()
    {
        $folder = PUBPATH . '/../logs/' . date('Y') . '/' . date('m') . '/' . date('d') . '/';
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        $fileName = microtime() . self::$id;
        $f = fopen($folder . $fileName . '.log', 'at');
        foreach (self::$logs as $row) {
            fwrite($f, date('H:i:s') . "\t" . $row['message'] . "\t" . $row['context'] . PHP_EOL);
        }
        fclose($f);
    }
}