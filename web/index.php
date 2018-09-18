<?php
/**
 * User: pel
 * Date: 06/09/2018
 */

use Converter\Application;
use Converter\controllers\CloudConverterController;
use Converter\controllers\AmazonController;

require __DIR__ . '/../vendor/autoload.php';

$application = new Application();
$application->addPostRoute('/video/cloudconvert', [CloudConverterController::class, 'process']);
$application->addGetRoute('/video/cloudconvert/callback', [CloudConverterController::class, 'callback']);

$application->addPostRoute('/video/amazon', [AmazonController::class, 'process']);
$application->run();