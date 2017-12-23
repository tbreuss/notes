<?php

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', sprintf('%s/log/%s-error.log', dirname(__DIR__), date('Y-m')));

require '../vendor/autoload.php';
require '../src/functions.php';

use Phroute\Phroute\Dispatcher;
use Phroute\Phroute\Exception\HttpRouteNotFoundException;
use Phroute\Phroute\RouteCollector;

//sleep(1);

if (get_request_method() === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    exit;
}

$router = new RouteCollector();

$router->get('/articles', function (): array {

    $q = get_query_var('q', '');
    $tags = get_query_var('tags', []);
    $page = max(get_query_var('page', 1), 1);
    $order = get_query_var('sort', 'default');
    $itemsPerPage = 20;

    $articles = find_all_articles($q, $tags, $order, $page, $itemsPerPage);
    $totalCount = find_found_rows();
    $paging = get_paging($totalCount, $page, $itemsPerPage);

    return [
        'articles' => $articles,
        'paging' => $paging
    ];
});

$router->get('/articles/{id}', function (int $id): array {
    $article = find_one_article($id);
    update_article_views($id);
    return $article;
});

$router->post('/add-article', function (): array {
    $data = get_php_input();
    $errors = validate_article($data);
    if (empty($errors)) {
        add_article($data);
        return ['success' => true];
    }
    return [
        'success' => false,
        'errors' => $errors
    ];
});

$router->get('/selectedtags', function (): array {
    $q = get_query_var('q', '');
    $tags = get_query_var('tags', []);
    $selected = find_selected_tags($q, $tags);
    return $selected;
});

$router->get('/tags', function (): array {
    $sort = get_query_var('sort', 'name');
    return find_all_tags($sort);
});

$router->get('/tags/{id}', function (int $id): array {
    return find_one_tag($id);
});

$router->get('/popular', function (): array {
    return find_selected_articles(['views' => 'DESC']);
});

$router->get('/latest', function (): array {
    return find_selected_articles(['created' => 'DESC']);
});

$router->get('/modified', function (): array {
    return find_selected_articles(['modified' => 'DESC']);
});

$router->post('/auth/login', function (): array {
    $data = get_php_input();
    $errors = validate_login($data);
    if (empty($errors)) {
        $user = auth_user($data['username'], $data['password']);
        if (empty($user)) {
            $errors['form'] = 'Benutzername oder Passwort ungültig';
        } else {
            $token = generate_token($user);
            return [
                'token' => $token
            ];
        }
    }
    return [
        'errors' => $errors
    ];
});

$router->post('/auth/logout', function (): array {
    auth_logout();
    return [
        true
    ];
});

function validate_login(array $data): array
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

function auth_logout(string $token)
{
}

function auth_user(string $username, string $password): array
{
    $user = find_user($username);
    if (!empty($user)) {
        if (validate_password($password, $user)) {
            return $user;
        }
    }
    return [];
}

function generate_token(array $user): string
{
    return 'generated_token';
}

function find_user(string $username): array
{
    $user = get_database()->get('users', '*', [
        'username' => $username,
        'deleted' => 0
    ]);
    if (empty($user)) {
        return [];
    }
    return $user;
}

try {
    $dispatcher = new Dispatcher($router->getData());
    $response = $dispatcher->dispatch(get_request_method(), get_url_path());
    header('Access-Control-Allow-Origin: *');
    echo json_encode($response);

} catch (HttpRouteNotFoundException $e) {

    header('HTTP/1.0 404 Not Found');
    echo json_encode(['error' => $e->getMessage()]);

} catch (\Exception $e) {

    header('HTTP/1.0 500');
    echo json_encode(['error' => $e->getMessage()]);

}
