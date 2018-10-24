<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\components;


class Redis
{
    private static $instance;
    /** @var \Redis */
    protected $redisClient;
    
    private function __construct()
    {
        $redisConfig = Config::getInstance()->get('redis');
        if ($redisConfig === null) {
        
        }
        $this->redisClient = new \Redis();
        $this->redisClient->connect($redisConfig['host'], $redisConfig['port']);
        if (isset($redisConfig['database'])) {
            $this->redisClient->select($redisConfig['database']);
        }
    }
    
    /**
     * @return \Redis
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Redis();
        }
        
        return self::$instance->redisClient;
    }
    
    private function __clone() {}
    
    private function __wakeup() {}
}