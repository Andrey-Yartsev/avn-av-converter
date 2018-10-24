<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\components\drivers;


interface Driver
{
    public function processPhoto($filePath, $callback, $processId = null);
    
    public function processAudio($filePath, $callback, $processId = null);
    
    public function processVideo($filePath, $callback, $processId = null);
}