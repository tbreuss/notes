<?php

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', sprintf('%s/log/%s-error.log', dirname(__DIR__), date('Y-m')));

require '../vendor/autoload.php';

$longopts  = ['username:', 'password:', 'name:', 'email:'];
$options = getopt('', $longopts);

if (empty($options['username'])) {
    echo 'username is required';
    exit;
}
if (empty($options['password'])) {
    echo 'password is required';
    exit;
}
if (empty($options['name'])) {
    echo 'name is required';
    exit;
}
if (empty($options['email'])) {
    echo 'email is required';
    exit;
}

$medoo = common\medoo();

$user = db\user\find_one($options['username']);

if (!empty($user)) {
    echo 'username already exists';
    exit;
}

db\user\create($options);

echo 'user created successfully';
