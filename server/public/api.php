<?php

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', sprintf('%s/log/%s-error.log', dirname(__DIR__), date('Y-m')));

require '../vendor/autoload.php';

use Phroute\Phroute\Dispatcher;
use Phroute\Phroute\Exception\HttpRouteNotFoundException;
use Phroute\Phroute\RouteCollector;

#sleep(1);

if (request\method() === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    exit;
}

$router = new RouteCollector();

$router->filter('auth', function(){
    $jwt = jwt\get_bearer_token();
    if (empty($jwt)) {
        header('HTTP/1.0 401 Unauthorized');
        exit;
    }
});

$router->get('/articles', function (): array {

    $q = request\get_var('q', '');
    $tags = request\get_var('tags', []);
    $page = max(request\get_var('page', 1), 1);
    $order = request\get_var('sort', 'default');
    $itemsPerPage = 20;

    $articles = db\article\find_all($q, $tags, $order, $page, $itemsPerPage);
    $totalCount = db\article\found_rows();
    $paging = db\article\paging($totalCount, $page, $itemsPerPage);

    return [
        'articles' => $articles,
        'paging' => $paging
    ];
});

$router->get('/articles/{id}', function (int $id): array {
    $article = db\article\find_one($id);
    db\article\increase_views($id);
    return $article;
});

$router->post('/add-article', function (): array {
    $data = request\php_input();
    $errors = db\article\validate($data);
    if (empty($errors)) {
        db\article\insert($data);
        header('HTTP/1.0 201 Created');
        return [];
    }
    header('HTTP/1.0 400 Validation failed');
    return $errors;
}, ['before' => 'auth']);

$router->put('/articles/{id}', function (int $id): array {
    $data = request\php_input();
    $errors = db\article\validate($data);
    if (empty($errors)) {
        db\article\update($id, $data);
        header('HTTP/1.0 201 Created');
        return [];
    }
    header('HTTP/1.0 400 Validation failed');
    return $errors;
}, ['before' => 'auth']);

$router->get('/selectedtags', function (): array {
    $q = request\get_var('q', '');
    $tags = request\get_var('tags', []);
    $selected = db\tag\find_selected_tags($q, $tags);
    return $selected;
});

$router->get('/tags', function (): array {
    $sort = request\get_var('sort', 'name');
    return db\tag\find_all($sort);
});

$router->get('/tags/{id}', function (int $id): array {
    return db\tag\find_one($id);
});

$router->get('/popular', function (): array {
    return db\article\find_selected(['id', 'title', 'abstract', 'views'], ['views' => 'DESC']);
});

$router->get('/latest', function (): array {
    return db\article\find_selected(['id', 'title', 'abstract', 'created'], ['created' => 'DESC']);
});

$router->get('/modified', function (): array {
    return db\article\find_selected(['id', 'title', 'abstract', 'modified'], ['modified' => 'DESC']);
});

$router->post('/auth/login', function () {
    $data = request\php_input();
    $errors = db\user\validate_credentials($data);
    if (empty($errors)) {
        $user = db\user\authenticate($data['username'], $data['password']);
        if (empty($user)) {
            $errors['password'] = 'Benutzername oder Passwort ungültig';
        } else {
            $token = jwt\generate_token($user);
            return $token;
        }
    }
    header('HTTP/1.0 400 Validation failed');
    return $errors;
});


try {
    $dispatcher = new Dispatcher($router->getData());
    $response = $dispatcher->dispatch(request\method(), request\url_path());
    header('Access-Control-Allow-Origin: *');
    echo json_encode($response);

} catch (HttpRouteNotFoundException $e) {

    header('HTTP/1.0 404 Not Found');
    echo json_encode(['error' => $e->getMessage()]);

} catch (\Exception $e) {

    header('HTTP/1.0 500');
    echo json_encode(['error' => $e->getMessage()]);

}
