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

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Expose-Headers: Range');
header('Access-Control-Allow-Headers: Accept,Authorization,Cache-Control,Content-Type,DNT,If-Modified-Since,Keep-Alive,Origin,User-Agent,X-Requested-With,Content-Disposition,Content-Range,Content-Length,TE');
header('Allow: PUT, GET, DELETE, POST');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

$application = new Application();
$application->addGetRoute('/video/cloudconvert/callback', [CloudConverterController::class, 'callback']);
$application->addPostRoute('/aws/sns', [AmazonController::class, 'sns']);

$application->addPostRoute('/file/upload', [ProcessController::class, 'upload']);
$application->addPostRoute('/process/exists', [ProcessController::class, 'exists']);
$application->addPostRoute('/process/start', [ProcessController::class, 'start']);
$application->addPostRoute('/process/restart', [ProcessController::class, 'restart']);
$application->addGetRoute('/process/([\w-]+)/status', [ProcessController::class, 'status']);

$application->addGetRoute('/status', [SystemController::class, 'status']);
$application->addGetRoute('/', [SystemController::class, 'index']);

$application->run();
