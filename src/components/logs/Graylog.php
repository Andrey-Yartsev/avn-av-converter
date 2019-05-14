<?php
/**
 * User: pel
 * Date: 2019-05-14
 */

namespace Converter\components\logs;


use Gelf\Logger;
use Gelf\Publisher;
use Gelf\Transport\UdpTransport;
use Psr\Log\LogLevel;

class Graylog
{
    protected $logger;
    
    public function __construct($settings)
    {
        $transport = new UdpTransport($settings['host'], $settings['port'], UdpTransport::CHUNK_SIZE_LAN);
        $publisher = new Publisher();
        $publisher->addTransport($transport);
        $this->logger = new Logger($publisher, $settings['facility']);
    }
    
    public function send($message, array $context = [], $level = LogLevel::INFO)
    {
        $this->logger->log($level, $message, $context);
    }
}