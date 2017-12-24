<?php

namespace request;

function php_input(): array
{
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    return $data;
}

function method(): string
{
    return $_SERVER['REQUEST_METHOD'];
}

// todo: doesn't work properly
function get_var(string $name, $default = null)
{
    $type = gettype($default);
    switch ($type) {
        case 'integer':
            $input = (int)filter_input(INPUT_GET, $name, FILTER_SANITIZE_NUMBER_INT);
            break;
        case 'string':
            $input = (string)filter_input(INPUT_GET, $name, FILTER_SANITIZE_STRING);
            break;
        case 'array':
            $input = (array)filter_input(INPUT_GET, $name, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY);
            break;
        default:
            $input = filter_input(INPUT_GET, $name, FILTER_SANITIZE_STRING);
    }
    $realtype = gettype($input);
    if ($realtype != $type) {
        $message = sprintf('Type mismatch for input variable "%s". Given type is "%s", required type is "%s".',
            $name,
            $realtype,
            $type
        );
        throw new \Exception($message);
    }
    if ($input === null) {
        $input = $default;
    }
    return $input;
}

function url_path(): string
{
    $strPathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    $urlPath = parse_url($strPathInfo, PHP_URL_PATH);
    return $urlPath;
}
