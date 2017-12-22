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
        $q = '%' . $q . '%';
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
        'created' => 'created DESC',
        'default' => 'title ASC',
        'popular' => 'views DESC'
    ];
    if (isset($orders[$orderKey])) {
        $sql .= " ORDER BY " . $orders[$orderKey];
    }

    $sql .= " LIMIT " . ($page - 1) * 20 . ', ' . 20;

    $stmt = get_pdo()->prepare($sql);
    $stmt->execute($params);

    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = 'SELECT FOUND_ROWS()';
    $stmt = get_pdo()->prepare($sql);
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
        $data['tags'] = sanitize_tags($data['tags']);
        $database->insert('articles', $data);
        save_tags($data['tags']);
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

    $sql = "
		SELECT t.name, count(a.id) AS frequency
		FROM tags t
		INNER JOIN articles a ON FIND_IN_SET(t.name, a.tags)>0 
		WHERE 1=1
	";

    $params = [];

    if (!empty($q)) {
        $q = '%' . $q . '%';
        $sql .= " AND (a.title LIKE ? OR a.abstract LIKE ? OR a.content LIKE ?)";
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    if (!empty($tags)) {
        foreach ($tags as $tag) {
            $sql .= " AND FIND_IN_SET(?, a.tags)>0";
            $params[] = $tag;
        }
    }

    $sql .= "
		GROUP BY t.name
		ORDER BY frequency DESC
		LIMIT 40
	";

    $stmt = $database->pdo->prepare($sql);
    $stmt->execute($params);
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

    sort($tags);
    return $tags;
});

$router->get('/tags', function () {
    $orders = [
        'name' => ['name' => 'ASC'],
        'frequency' => ['frequency' => 'DESC'],
        'changed' => ['modified' => 'DESC'],
        'created' => ['created' => 'DESC']
    ];
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
    $order = isset($orders[$sort]) ? $orders[$sort] : $orders['name'];
    return find_all_tags($order);
});

$router->get('/tags/{id}', function ($id) {
    return find_one_tag($id);
});

$router->get('/popular', function () {
    return find_selected_articles(['views' => 'DESC']);
});

$router->get('/latest', function () {
    return find_selected_articles(['created' => 'DESC']);
});

$router->get('/modified', function () {
    return find_selected_articles(['modified' => 'DESC']);
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

function sanitize_tags($strtags)
{
    $tags = explode(',', $strtags);
    $sanitized = array_map('trim', $tags);
    return implode(',', $sanitized);
}

function save_tags($strtags)
{
    $tags = explode(',', $strtags);
    foreach ($tags as $tag) {
        save_tag($tag);
    }
}

function save_tag($tag)
{
    $database = get_database();
    $id = $database->get('tags', 'id', [
        'name' => $tag
    ]);
    if ($id > 0) {
        $database->update('tags', [
            'frequency[+]' => 1,
            'modified' => date('Y-m-d H:i:s')
        ], [
            'id' => $id
        ]);
    } else {
        $database->insert('tags', [
            'name' => $tag,
            'frequency' => 1,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s')
        ]);
    }
}

function get_database()
{
    static $database;
    if (is_null($database)) {
        $config = require '../config/database.php';
        $database = new Medoo($config);
    }
    return $database;
}

function get_pdo()
{
    $database = get_database();
    return $database->pdo;
}

function find_selected_articles(array $order)
{
    $where = [];
    $where['ORDER'] = $order;
    $where['LIMIT'] = 5;
    $articles = get_database()->select('articles', ['id', 'title', 'abstract', 'modified'], $where);
    return $articles;
}

function find_all_tags(array $order)
{
    $articles = get_database()->select('tags', ['id', 'name', 'frequency'], ['ORDER' => $order]);
    return $articles;
}

function find_one_tag($id)
{
    $article = get_database()->get('tags', '*', ['id' => $id]);
    return $article;
}
