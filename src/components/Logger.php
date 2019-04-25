<?php
/**
 * User: pel
 * Date: 08/10/2018
 */

namespace Converter\components;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Logger implements LoggerInterface
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

    public function emergency($message, array $context = array())
    {
        self::send('custom', ['text' => $message], LogLevel::EMERGENCY);
    }

    public function alert($message, array $context = array())
    {
        self::send('custom', ['text' => $message], LogLevel::ALERT);
    }

    public function critical($message, array $context = array())
    {
        self::send('custom', ['text' => $message], LogLevel::CRITICAL);
    }

    public function error($message, array $context = array())
    {
        self::send('custom', ['text' => $message], LogLevel::ERROR);
    }

    public function warning($message, array $context = array())
    {
        self::send('custom', ['text' => $message], LogLevel::WARNING);
    }

    public function notice($message, array $context = array())
    {
        self::send('custom', ['text' => $message], LogLevel::NOTICE);
    }

    public function info($message, array $context = array())
    {
        self::send('custom', ['text' => $message], LogLevel::INFO);
    }

    public function debug($message, array $context = array())
    {
        self::send('custom', ['text' => $message], LogLevel::DEBUG);
    }

    public function log($level, $message, array $context = array())
    {
        self::send('custom', ['text' => $message], $level);
    }

}