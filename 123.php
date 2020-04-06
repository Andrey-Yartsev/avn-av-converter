<?php
/**
 * User: pel
 * Date: 2020-04-06
 */

require __DIR__ . '/vendor/autoload.php';

$file = new SplFileObject('log1.log');
$client = new \GuzzleHttp\Client();
$processIds = [];

while (!$file->eof()) {
    $row = $file->fgets();
    $row = substr($row, strpos($row, '{'));
    $json = json_decode($row, true);
    $processIds[] = $json['processId'];
}

$processIds = array_unique($processIds);
foreach (array_chunk($processIds, 10) as $processes) {
    $json = [];
    foreach ($processes as $processId) {
        $json['processes'][] = ['id' => $processId, 'preset' => 'of'];
    }
    
    $response = $client->post('https://convert.onlyfans.com/process/restart', [
        'json' => $json,
    ]);
    var_dump($response->getBody()->getContents());
}