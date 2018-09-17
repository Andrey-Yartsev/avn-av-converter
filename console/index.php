#!/usr/bin/env php
<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new \Converter\commands\AmazonUploadCommand());
//$application->add(new FooLockCommand())
$application->run();