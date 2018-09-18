<?php
/**
 * User: pel
 * Date: 06/09/2018
 */

use Converter\Application;
use Converter\controllers\CloudConverterController;
use Converter\controllers\AmazonController;
use Converter\controllers\VideoController;

require __DIR__ . '/../vendor/autoload.php';

$application = new Application();
$application->addPostRoute('/video/process', [VideoController::class, 'process']);
$application->addGetRoute('/video/cloudconvert/callback', [CloudConverterController::class, 'callback']);

$application->run();