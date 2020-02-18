<?php

$cfg = require(__DIR__ . '/config.prod.of.php');

$cfg['baseUrl'] = 'https://convert2.onlyfans.com';
$cfg['presets']['of_beta']['video']['transcoder']['pipeline']
    = $cfg['presets']['of_beta2']['video']['transcoder']['pipeline']
    = $cfg['presets']['of_geo_big']['video']['transcoder']['pipeline']
    = '1580329941216-vjf638';

return $cfg;
