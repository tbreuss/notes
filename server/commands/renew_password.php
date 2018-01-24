<?php

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', sprintf('%s/log/%s-error.log', dirname(__DIR__), date('Y-m')));

require '../vendor/autoload.php';

$longopts  = ['username:', 'password:'];
$options = getopt('', $longopts);
$options = array_map('trim', $options);

if (empty($options['username'])) {
    echo 'username is required';
    exit;
}
if (empty($options['password'])) {
    echo 'password is required';
    exit;
}

$user = db\user\find_one($options['username']);

if (empty($user)) {
    echo 'Sorry, username doesnt exist';
    exit;
}

db\user\renew_password($options['username'], $options['password']);

echo 'password for user renewed successfully';
