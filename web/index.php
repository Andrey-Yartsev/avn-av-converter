<?php
/**
 * User: pel
 * Date: 06/09/2018
 */

use Converter\Application;
use Converter\controllers\AmazonController;
use Converter\controllers\CloudConverterController;
use Converter\controllers\ProcessController;
use Converter\controllers\SystemController;

define('PUBPATH', __DIR__);

require __DIR__ . '/../vendor/autoload.php';

$currentVerb = strtoupper($_SERVER["REQUEST_METHOD"]);
if ($currentVerb == 'OPTIONS') {
    http_response_code(200);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization');
    header('Allow: PUT, GET, DELETE, POST');
    exit(0);
}

$application = new Application();
$application->addGetRoute('/video/cloudconvert/callback', [CloudConverterController::class, 'callback']);
$application->addPostRoute('/aws/sns', [AmazonController::class, 'sns']);

$application->addPostRoute('/file/upload', [ProcessController::class, 'upload']);
$application->addPostRoute('/process/exists', [ProcessController::class, 'exists']);
$application->addPostRoute('/process/start', [ProcessController::class, 'start']);
$application->addGetRoute('/process/(\w+)/status', [ProcessController::class, 'status']);

$application->addGetRoute('/status', [SystemController::class, 'status']);

$application->run();