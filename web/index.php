<?php
/**
 * User: pel
 * Date: 06/09/2018
 */

use Converter\Application;

include '../vendor/autoload.php';

$application = new Application();
$application->addGetRoute('/users/([0-9]+)', [\Converter\controllers\TestController::class, 'test']);