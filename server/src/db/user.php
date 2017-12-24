<?php

namespace db\user;

use common;

function authenticate(string $username, string $password): array
{
    $user = find_one($username);
    if (!empty($user)) {
        if (validate_password($password, $user)) {
            return $user;
        }
    }
    return [];
}

function find_one(string $username): array
{
    $user = common\medoo()->get('users', '*', [
        'username' => $username,
        'deleted' => 0
    ]);
    if (empty($user)) {
        return [];
    }
    return $user;
}

function validate_password($password, array $user)
{
    return hash_password($password, $user['salt']) === $user['password'];
}

function hash_password($password, $salt)
{
    return md5($salt . $password);
}

function generate_salt()
{
    return uniqid('', true);
}

function validate_credentials(array $data): array
{
    $errors = [];
    if (empty($data['username'])) {
        $errors['username'] = 'Benutzername fehlt';
    }
    if (empty($data['password'])) {
        $errors['password'] = 'Passwort fehlt';
    }
    return $errors;
}
