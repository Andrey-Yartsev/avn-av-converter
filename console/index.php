#!/usr/bin/env php
<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

require __DIR__ . '/../vendor/autoload.php';

define('PUBPATH', __DIR__ . '/../web');

use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new \Converter\commands\AmazonUploadCommand());
$application->add(new \Converter\commands\AmazonQueueCommand());
$application->add(new \Converter\commands\RetryCommand());
$application->add(new \Converter\commands\UploadCommand());
$application->run();