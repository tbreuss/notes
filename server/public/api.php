<?php

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', sprintf('%s/log/%s-error.log', dirname(__DIR__), date('Y-m')));

require '../vendor/autoload.php';

use Phroute\Phroute\Dispatcher;
use Phroute\Phroute\Exception\HttpRouteNotFoundException;
use Phroute\Phroute\RouteCollector;

set_error_handler("exception_error_handler");

try {

    #sleep(1);

    if (request\method() === 'OPTIONS') {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        exit;
    }

    $dispatcher = get_dispatcher();
    $response = $dispatcher->dispatch(request\method(), request\url_path());
    header('Access-Control-Allow-Origin: *');
    echo json_encode($response);

} catch (HttpRouteNotFoundException $e) {

    header('Access-Control-Allow-Origin: *');
    header('HTTP/1.0 404 Not Found');
    echo json_encode(['error' => $e->getMessage()]);

} catch (\Exception $e) {

    $code = empty($e->getCode()) ? 500 : $e->getCode();
    header('Access-Control-Allow-Origin: *');
    header('HTTP/1.0 ' . $code);
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

}

function get_router()
{
    $router = new RouteCollector();

    $router->filter('auth', function(){
        $jwt = jwt\get_bearer_token();
        if (empty($jwt)) {
            header('HTTP/1.0 401 Unauthorized');
            exit;
        }
    });

    // Public
    $router->get('/ping', function (): array {
        return [
            'name' => 'ch.tebe.notes',
            'time' => date('c'),
            // todo: determine correct version
            'version' => '0.5'
        ];
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

    // With Authorization
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
    }, ['before' => 'auth']);

    $router->get('/articles/{id}', function (int $id): array {
        $article = db\article\find_one($id);
        db\article\increase_views($id);
        return $article;
    }, ['before' => 'auth']);

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
            $updated = db\article\update($id, $data);
            if ($updated) {
                header('HTTP/1.0 201 Created');
            } else {
                header('HTTP/1.0 200 Ok');
            }
            return [];
        }
        header('HTTP/1.0 400 Validation failed');
        return $errors;
    }, ['before' => 'auth']);

    $router->delete('/articles/{id}', function (int $id) {
        db\article\delete($id);
    }, ['before' => 'auth']);

    $router->get('/selectedtags', function (): array {
        $q = request\get_var('q', '');
        $tags = request\get_var('tags', []);
        $selected = db\tag\find_selected_tags($q, $tags);
        return $selected;
    }, ['before' => 'auth']);

    $router->get('/users', function (): array {
        $sort = request\get_var('sort', 'name');
        return db\user\find_all($sort);
    }, ['before' => 'auth']);

    $router->get('/tags', function (): array {
        $sort = request\get_var('sort', 'name');
        return db\tag\find_all($sort);
    }, ['before' => 'auth']);

    $router->get('/tags/{id}', function (int $id): array {
        return db\tag\find_one($id);
    }, ['before' => 'auth']);

    $router->get('/popular', function (): array {
        return db\article\find_selected(['id', 'title', 'views'], ['views' => 'DESC']);
    }, ['before' => 'auth']);

    $router->get('/latest', function (): array {
        return db\article\find_selected(['id', 'title', 'created'], ['created' => 'DESC']);
    }, ['before' => 'auth']);

    $router->get('/modified', function (): array {
        return db\article\find_selected(['id', 'title', 'modified'], ['modified' => 'DESC']);
    }, ['before' => 'auth']);

    $router->get('/liked', function (): array {
        return db\article\find_selected(['id', 'title', 'likes'], ['likes' => 'DESC']);
    }, ['before' => 'auth']);

    $router->post('/upload', function () {

        $user = jwt\get_user_from_token();

        if (empty($_FILES['file'])) {
            throw new \Exception('No file uploaded', 400);
        }

        $fileEndings = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/png' => 'png'
        ];

        $file = $_FILES['file'];

        if ($file['error'] > 0) {
            throw new \Exception('An error occured', 400);
        }

        if (!in_array($file['type'], array_keys($fileEndings))) {
            throw new \Exception('Invalid file type', 400);
        }

        if ($file['size'] > 1000000) {
            throw new \Exception('File size to big', 400);
        }

        $basename = md5_file($file['tmp_name']);
        if ($basename === false) {
            throw new \Exception('Could not create md5 sum', 400);
        }

        $pathname = sprintf('media/%s/', $user['id']);
        if (!is_dir($pathname)) {
            mkdir($pathname);
        }

        $filename = $pathname . $basename . '.' . $fileEndings[$file['type']];

        if (!move_uploaded_file($file['tmp_name'], $filename)) {
            throw new \Exception('Could not move uploaded file', 400);
        }

        return [
            'name' => $file['name'],
            'location' => '/' . $filename
        ];

    }, ['before' => 'auth']);

    return $router;
}

function get_dispatcher()
{
    $router = get_router();
    $dispatcher = new Dispatcher($router->getData());
    return $dispatcher;
}

function exception_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // Dieser Fehlercode ist nicht in error_reporting enthalten
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}
