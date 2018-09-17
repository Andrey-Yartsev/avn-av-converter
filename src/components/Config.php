<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\components;


class Config
{
    private static $instance;
    protected $config = [];
    
    private function __construct()
    {
        $filePath = __DIR__ . '/../config/config.php';
        if (file_exists($filePath)) {
            $this->config = require $filePath;
        }
    }
    
    /**
     * @return Config
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Config();
        }
        
        return self::$instance;
    }
    
    /**
     * @param $name
     * @param null $default
     * @return null
     */
    public function get($name, $default = null)
    {
        return $this->config[$name] ?: $default;
    }
    
    private function __clone() {}
    
    private function __wakeup() {}
}