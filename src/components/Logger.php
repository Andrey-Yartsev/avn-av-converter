<?php
/**
 * User: pel
 * Date: 08/10/2018
 */

namespace Converter\components;

use Converter\components\logs\File;
use Converter\components\logs\Graylog;
use Psr\Log\LogLevel;

class Logger
{
    /** @var File|Graylog */
    protected static $driver;
    
    /**
     * @param $message
     * @param array $context
     * @param string $level
     */
    public static function send($message, array $context = [], $level = LogLevel::INFO)
    {
        if (empty(self::$driver)) {
            self::$driver = self::getDriver();
        }
        self::$driver->send($message, $context, $level);
    }
    
    protected static function getDriver()
    {
        $config = Config::getInstance()->get('log');
        if ($config === null) {
            return new File();
        }
        if (!empty($config['driver']) && class_exists($config['driver'])) {
            $driverName = $config['driver'];
            unset($config['driver']);
            return new $driverName($config);
        }
    }
}