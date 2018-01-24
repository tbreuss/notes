<?php

namespace db\user;

use function common\{
    medoo
};

function authenticate(string $username, string $password): array
{
    $user = find_one($username);
    if (!empty($user)) {
        if (validate_password($password, $user)) {
            update_last_login($username);
            return $user;
        }
    }
    return [];
}

function find_all(string $sort): array
{
    $orders = [
        #'name' => ['name' => 'ASC'],
        #'frequency' => ['frequency' => 'DESC', 'name' => 'ASC'],
        #'changed' => ['modified' => 'DESC', 'name' => 'ASC'],
        #'created' => ['created' => 'DESC', 'name' => 'ASC'],
        'default' => ['name' => 'ASC']
    ];
    $order = isset($orders[$sort]) ? $orders[$sort] : $orders['default'];
    $articles = medoo()->select('users', ['id', 'name', 'article_likes', 'article_views', 'lastlogin', 'created', 'modified'], [
        'deleted' => 0,
        'ORDER' => $order
    ]);
    return $articles;
}

function find_by_user_ids(array $ids): array
{
    $ids = array_filter($ids);
    if (empty($ids)) {
        return [];
    }
    $users = [];
    foreach (medoo()->select('users', ['id', 'username', 'name', 'email', 'lastlogin', 'created', 'modified', 'deleted'], ['id' => $ids]) as $row) {
        $users[$row['id']] = $row;
    }
    return $users;
}

function update_last_login(string $username): int
{
    $pdo = medoo()->update('users', ['lastlogin' => date('Y-m-d H:i:s')], ['username' => $username]);
    return $pdo->rowCount();
}

function find_one(string $username): array
{
    $user = medoo()->get('users', '*', [
        'username' => $username,
        'deleted' => 0
    ]);
    if (empty($user)) {
        return [];
    }
    return $user;
}

function validate_password(string $password, array $user): bool
{
    return hash_password($password, $user['salt']) === $user['password'];
}

function hash_password(string $password, string $salt): string
{
    return md5($salt . $password);
}

function generate_salt(): string
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

function create(array $data): int
{
    $salt = generate_salt();
    $data['password'] = hash_password($data['password'], $salt);
    $data['salt'] = $salt;
    $data['created'] = date('Y-m-d H:i:s');
    medoo()->insert('users', $data);
    return medoo()->id();
}

function renew_password(string $username, string $password): int
{
    $salt = generate_salt();
    $data = [
        'password' => hash_password($password, $salt),
        'salt' => $salt,
        'modified' =>  date('Y-m-d H:i:s')
    ];
    $where = [
        'username' => $username
    ];
    $pdo = medoo()->update('users', $data, $where);
    $error = medoo()->error();

    if (!empty($error[1])) {
        throw new \Exception($error[2], $error[1]);
    }
    return $pdo->rowCount();
}
