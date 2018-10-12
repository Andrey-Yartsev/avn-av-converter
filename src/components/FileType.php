<?php
/**
 * User: pel
 * Date: 12/10/2018
 */

namespace Converter\components;


use Dflydev\ApacheMimeTypes\PhpRepository;

class FileType
{
    /** @var PhpRepository */
    private static $instance;
    
    /**
     * @return PhpRepository
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new PhpRepository();
        }
        
        return self::$instance;
    }
    
    private function __construct() {}
    
    private function __clone() {}
    
    private function __wakeup() {}
}