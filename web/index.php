<?php
/**
 * User: pel
 * Date: 06/09/2018
 */

use Converter\Application;
use Converter\controllers\CloudConverterController;
use Converter\controllers\VideoController;
use Converter\controllers\ProcessController;

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
$application->addPostRoute('/video/process', [VideoController::class, 'process']);
$application->addPostRoute('/video/start', [VideoController::class, 'start']);
$application->addGetRoute('/video/cloudconvert/callback', [CloudConverterController::class, 'callback']);

$application->addPostRoute('/file/upload', [ProcessController::class, 'upload']);
$application->addPostRoute('/process/exists', [ProcessController::class, 'exists']);
$application->addPostRoute('/process/start', [ProcessController::class, 'start']);

$application->run();