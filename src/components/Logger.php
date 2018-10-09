<?php
/**
 * User: pel
 * Date: 08/10/2018
 */

namespace Converter\components;


use Gelf\Publisher;
use Gelf\Transport\UdpTransport;
use Psr\Log\LogLevel;

class Logger
{
    private static $instance;
    
    private function __construct()
    {
        $config = Config::getInstance()->get('graylog');
        $transport = new UdpTransport($config['connection']['host'], $config['connection']['port'], UdpTransport::CHUNK_SIZE_LAN);
        $publisher = new Publisher();
        $publisher->addTransport($transport);
        self::$instance = new \Gelf\Logger($publisher, 'converter_' . $config['facility']);
    }
    
    /**
     * @return \Gelf\Logger
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Logger();
        }
        
        return self::$instance;
    }
    
    /**
     * @param $message
     * @param array $context
     * @param string $level
     */
    public static function send($message, array $context = array(), $level = LogLevel::INFO)
    {
        $instance = self::getInstance();
        $instance->log($level, $message, $context);
    }
    
    private function __clone() {}
    
    private function __wakeup() {}
}