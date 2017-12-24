<?php

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', sprintf('%s/log/%s-error.log', dirname(__DIR__), date('Y-m')));

require '../vendor/autoload.php';

$db = common\medoo();

