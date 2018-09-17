<?php
/**
 * User: pel
 * Date: 06/09/2018
 */

use Converter\Application;
use Converter\controllers\CloudConverterController;

include '../vendor/autoload.php';

$application = new Application();
$application->addPostRoute('/video/cloudconvert', [CloudConverterController::class, 'process']);
$application->addPostRoute('/video/cloudconvert/callback', [CloudConverterController::class, 'callback']);
$application->run();