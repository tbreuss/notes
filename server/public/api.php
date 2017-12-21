<?php

ini_set('display_errors', 1);
ini_set("log_errors", 1);
ini_set("error_log", sprintf("%s/log/%s-error.log", dirname(__DIR__), date('Y-m')));

require '../vendor/autoload.php';

use Medoo\Medoo;
use Phroute\Phroute\Dispatcher;
use Phroute\Phroute\RouteCollector;

//sleep(1);

$config = require '../config/database.php';
$database = new Medoo($config);

$router = new RouteCollector();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    exit;
}


$router->get('/articles', function () use ($database) {

    $q = isset($_GET['q']) ? $_GET['q'] : '';
    $tags = isset($_GET['tags']) ? $_GET['tags'] : [];
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    if ($page < 1) {
        $page = 1;
    }
    $orderKey = isset($_GET['sort']) ? $_GET['sort'] : 'default';

    $sql = "SELECT SQL_CALC_FOUND_ROWS id, title, abstract, tags FROM articles WHERE 1=1";

    $params = [];

    if (!empty($q)) {
        $sql .= " AND (title LIKE ? OR abstract LIKE ? OR content LIKE ?)";
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    if (!empty($tags)) {
        foreach ($tags as $tag) {
            $sql .= " AND FIND_IN_SET(?, tags)>0";
            $params[] = $tag;
        }
    }

    $orders = [
        'title' => 'title ASC',
        'changed' => 'modified DESC',
        'default' => 'title ASC'
    ];
    if (isset($orders[$orderKey])) {
        $sql .= " ORDER BY " . $orders[$orderKey];
    }

    $sql .= " LIMIT " . ($page - 1) * 20 . ', ' . 20;

    $stmt = $database->pdo->prepare($sql);
    $stmt->execute($params);

    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = 'SELECT FOUND_ROWS()';
    $stmt = $database->pdo->prepare($sql);
    $stmt->execute();
    $totalCount = $stmt->fetchColumn();

    foreach ($articles as $i => $a) {
        $articles[$i]['tags'] = explode(',', $a['tags']);
    }

    return [
        'articles' => $articles,
        'paging' => [
            'itemsPerPage' => 20,
            'totalItems' => $totalCount,
            'currentPage' => $page,
            'pageCount' => ceil($totalCount / 20)
        ]
    ];
});

$router->get('/articles/{id}', function ($id) use ($database) {
    $article = $database->get('articles', '*', ['id' => $id]);
    if (empty($article)) {
        throw new \Exception('Not found');
    }
    $database->update('articles', ["views[+]" => 1], ['id' => $id]);
    $article = handle_custom_tags($article, 'content');
    $article['tags'] = explode(',', $article['tags']);
    return $article;
});

$router->post('/add-article', function () use ($database) {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $errors = [];
    if (empty($data['title'])) {
        $errors['title'] = 'Bitte einen Titel eingeben';
    }
    if (empty($data['abstract'])) {
        $errors['abstract'] = 'Bitte einen Abstract eingeben';
    }
    if (empty($data['content'])) {
        $errors['content'] = 'Bitte einen Content eingeben';
    }
    if (empty($data['tags'])) {
        $errors['tags'] = 'Bitte Tags eingeben';
    }

    if (empty($errors)) {
        $data['created'] = date('Y-m-d H:i:s');
        $data['modified'] = date('Y-m-d H:i:s');
        $database->insert('articles', $data);
        return ['success' => true];
    }

    return [
        'success' => false,
        'errors' => $errors
    ];
});

$router->get('/selectedtags', function () use ($database) {

    $q = isset($_GET['q']) ? $_GET['q'] : '';
    $tags = isset($_GET['tags']) ? $_GET['tags'] : [];

    $sql = "SELECT tags FROM articles WHERE 1=1";

    $params = [];

    if (!empty($q)) {
        $sql .= " AND (title LIKE ? OR abstract LIKE ? OR content LIKE ?)";
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    if (!empty($tags)) {
        foreach ($tags as $tag) {
            $sql .= " AND FIND_IN_SET(?, tags)>0";
            $params[] = $tag;
        }
    }

    $stmt = $database->pdo->prepare($sql);
    $stmt->execute($params);
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 'tags');

    $tags = [];
    foreach ($columns as $column) {
        $tags = array_merge($tags, explode(',', $column));
    }

    $tags = array_unique($tags);
    sort($tags);

    return $tags;
});

$router->get('/tags', function () use ($database) {
    $orders = [
        'name' => ['name' => 'ASC'],
        'frequency' => ['frequency' => 'DESC'],
        'changed' => ['modified' => 'DESC']
    ];
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
    $order = isset($orders[$sort]) ? $orders[$sort] : $orders['name'];
    $articles = $database->select('tags', ['id', 'name', 'frequency'], ['ORDER' => $order]);
    return $articles;
});

$router->get('/tags/{id}', function ($id) use ($database) {
    $article = $database->get('tags', '*', ['id' => $id]);
    return $article;
});

$router->get('/popular', function () use ($database) {
    $where = [];
    $where['ORDER'] = ['views' => 'DESC'];
    $where['LIMIT'] = 5;
    $articles = $database->select('articles', ['id', 'title', 'abstract', 'views'], $where);
    return $articles;
});

$router->get('/latest', function () use ($database) {
    $where = [];
    $where['ORDER'] = ['created' => 'DESC'];
    $where['LIMIT'] = 5;
    $articles = $database->select('articles', ['id', 'title', 'abstract', 'created'], $where);
    return $articles;
});

$router->get('/modified', function () use ($database) {
    $where = [];
    $where['ORDER'] = ['modified' => 'DESC'];
    $where['LIMIT'] = 5;
    $articles = $database->select('articles', ['id', 'title', 'abstract', 'modified'], $where);
    return $articles;
});

header("Access-Control-Allow-Origin: *");

$dispatcher = new Dispatcher($router->getData());

$strPathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$response = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], parse_url($strPathInfo, PHP_URL_PATH));

echo json_encode($response);


function handle_custom_tags($article, $key)
{
    $content = $article[$key];

    $replacements = [
        '<css>' => "``` css",
        '</css>' => "```",
        '<html>' => "``` html",
        '</html>' => "```",
        '<javascript>' => "``` javascript",
        '</javascript>' => "```",
        '<php>' => "``` php",
        '</php>' => "```",
        '<shell>' => "``` shell",
        '</shell>' => "```",
        '<sql>' => "``` sql",
        '</sql>' => "```",
        '<text>' => "``` text",
        '</text>' => "```",
        '<typoscript>' => "``` typoscript",
        '</typoscript>' => "```",
    ];

    $content = str_replace(array_keys($replacements), array_values($replacements), $content);

    $article[$key] = $content;
    return $article;
}
