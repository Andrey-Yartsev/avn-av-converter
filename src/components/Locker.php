<?php
/**
 * User: pel
 * Date: 27.06.2020
 */

namespace Converter\components;


class Locker
{
    /**
     * @param $actionCode
     * @return string
     */
    protected static function getKey($actionCode)
    {
        return "lock:{$actionCode}";
    }
    
    /**
     * @param $actionCode
     * @param int $seconds
     */
    public static function lock($actionCode, $seconds = 5)
    {
        Redis::getInstance()->setex(self::getKey($actionCode), $seconds, true);
    }
    
    /**
     * @param $actionCode
     * @return bool|int
     */
    public static function isLocked($actionCode)
    {
        return Redis::getInstance()->exists(self::getKey($actionCode));
    }
    
    public static function unlock($actionCode)
    {
        Redis::getInstance()->del(self::getKey($actionCode));
    }
}