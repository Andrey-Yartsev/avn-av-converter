<?php
/**
 * User: pel
 * Date: 08/10/2018
 */

namespace Converter\components\storages;


abstract class FileStorage
{
    abstract public function hasError();
    
    abstract public function getError();
    
    abstract public function upload($sourcePath, $savedPath);
    
    abstract public function delete($hash);
}