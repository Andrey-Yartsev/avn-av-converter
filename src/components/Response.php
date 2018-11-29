<?php
/**
 * User: pel
 * Date: 07/09/2018
 */

namespace Converter\components;


abstract class Response implements \JsonSerializable
{
    public function __construct($values = [])
    {
        foreach ($values as $key => $value) {
            $this->{$key} = $value;
        }
    }
    
    /**
     * @param $value
     * @return false|string
     */
    public function formattedDate($value)
    {
        return is_numeric($value) ? date('c', $value) : date('c', strtotime($value));
    }

    /**
     * @param $content
     * @param bool $doubleEncode
     * @return string
     */
    public function encode($content, $doubleEncode = true)
    {
        return htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }
}