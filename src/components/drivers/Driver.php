<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\components\drivers;


interface Driver
{
    public function processVideo($filePath, $callback);
}