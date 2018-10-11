<?php
/**
 * User: pel
 * Date: 06/09/2018
 */

use Converter\Application;
use Converter\controllers\CloudConverterController;
use Converter\controllers\VideoController;

define('PUBPATH', __DIR__);

require __DIR__ . '/../vendor/autoload.php';

$application = new Application();
$application->addPostRoute('/video/process', [VideoController::class, 'process']);
$application->addPostRoute('/video/start', [VideoController::class, 'start']);
$application->addGetRoute('/video/cloudconvert/callback', [CloudConverterController::class, 'callback']);

$application->run();