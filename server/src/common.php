<?php

namespace common;

use Medoo\Medoo as Medoo;
use PDO;

function config($key)
{
    static $config;
    if (is_null($config)) {
        $config = require '../config/main.php';
    }
    return $config[$key];
}

function medoo(): Medoo
{
    return database();
}

function database(): Medoo
{
    static $database;
    if (is_null($database)) {
        $config = require '../config/database.php';
        $database = new Medoo($config);
    }
    return $database;
}

function pdo(): PDO
{
    $database = database();
    return $database->pdo;
}
