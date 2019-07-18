<?php
/**
 * User: pel
 * Date: 2019-07-18
 */

namespace Converter\helpers;


use Converter\components\Logger;

class CliHelper
{
    public static function run($commandName, $params = [])
    {
        $commandName = escapeshellarg($commandName);
        foreach ($params as $index => $param) {
            $params[$index] = '"' . escapeshellarg($param) . '"';
        }
        $paramsString = implode(' ', $params);
        $command = 'php ' . __DIR__ . "/../../console/index.php {$commandName} {$paramsString} /dev/null 2>/dev/null &";
        Logger::send('cli.exec', [
            'command' => $command
        ]);
        @exec($command, $output, $error);
        if ($error) {
            return false;
        }
        
        return true;
    }
}