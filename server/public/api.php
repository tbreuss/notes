<?php

ini_set('display_errors', 1);
ini_set("log_errors", 1);
ini_set("error_log", sprintf("%s/log/%s-error.log", dirname(__DIR__), date('Y-m')));

require '../vendor/autoload.php';

use Phroute\Phroute\RouteCollector;
use Phroute\Phroute\Dispatcher;
use Phroute\Phroute\Exception\HttpRouteNotFoundException;
use Medoo\Medoo;

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

function get_articles_where()
{
	$where = [];
	
	$q = isset($_GET['q']) ? $_GET['q'] : '';
	if (!empty($q)) {
		$where["AND"] = [
			"OR" => [
				"title[~]" => $q,
				"abstract[~]" => $q,
				"content[~]" => $q,
			]
		];
	}
	
	$tags = isset($_GET['tags']) ? $_GET['tags'] : [];
	if (!empty($tags)) {
		#$where[] = [];
	}
	
	return $where;
}

$router->get('/articles', function() use ($database) {
	$where = [];
	$q = isset($_GET['q']) ? $_GET['q'] : '';
	if (!empty($q)) {
		$where = [
			"OR" => [
				"title[~]" => $q,
				"abstract[~]" => $q,
				"content[~]" => $q,
			]
		];
	}
	
	// Pagination
	$totalCount = $database->count('articles', $where);
		
	$orders = [
		'title' => ['title' => 'ASC'],
		'changed' => ['modified' => 'DESC']
	];
	$orderKey = isset($_GET['sort']) ? $_GET['sort'] : 'title';
	$where['ORDER'] = isset($orders[$orderKey]) ? $orders[$orderKey] : '';
	
	
	$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
	if ($page < 1) {
		$page = 1;
	}
	
	$where['LIMIT'] = [($page - 1) * 20, 20];
	
	
    $articles = $database->select('articles', ['id', 'title', 'abstract', 'tags'], $where);
	
	foreach ($articles as $i => $a) {
		$articles[$i]['tags'] = explode(',', $a['tags']);
	}
	
    return [
    	'articles' => $articles,
    	'tags' => array_unique($tags),
    	'paging' => [
    		'itemsPerPage' => 20,
			'totalItems' => $totalCount,
			'currentPage' => $page,
			'pageCount' => ceil($totalCount / 20)
    	]
    ];
});

$router->get('/articles/{id}', function($id) use ($database) {
    $article = $database->get('articles', '*', ['id' => $id]);
    if (empty($article)) {
        throw new \Exception('Not found');
    }
    $database->update('articles', [	"views[+]" => 1], ['id' => $id]);
    $article = handle_custom_tags($article, 'content');
	$article['tags'] = explode(',', $article['tags']);    
    return $article;
});

$router->post('/add-article', function() use ($database) {
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

$router->get('/selectedtags', function() use ($database) {
	$where = get_articles_where();
	
	$sql = "
		SELECT tags 
		FROM articles
		WHERE 1=1
	";
	
	$conditions = [];
	$q = isset($_GET['q']) ? $_GET['q'] : '';
	if (!empty($q)) {
		$conditions[] = "title LIKE '%${q}%'";
		$conditions[] = "abstract LIKE '%${q}%'";
		$conditions[] = "content LIKE '%${q}%'";
	}	
	if (!empty($conditions)) {
		$sql .= " AND (" . implode(' OR ', $conditions) . ")";
	}
	
	$conditions = [];
	$tags = isset($_GET['tags']) ? $_GET['tags'] : [];
	if (!empty($tags)) {
		#$where[] = [];
		foreach ($tags as $tag) {
			$conditions[] = "FIND_IN_SET('${tag}', tags) > 0";
		}
	}	
	if (!empty($conditions)) {
		$sql .= " AND " . implode(' AND ', $conditions);
	}

    $rows = $database->query($sql)->fetchAll();    
        
	$tags = [];    
    foreach ($rows as $row) {
	    $tags = array_merge($tags, explode(',', $row['tags']));
    }

	$tags = array_unique($tags);
	sort($tags);
	
	return $tags;
});

$router->get('/tags', function() use ($database) {
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

$router->get('/tags/{id}', function($id) use ($database) {
    $article = $database->get('tags', '*', ['id' => $id]);
    return $article;
});

$router->get('/popular', function() use ($database) {
	$where = [];
	$where['ORDER'] = ['views' => 'DESC'];
	$where['LIMIT'] = 5;
    $articles = $database->select('articles', ['id', 'title', 'abstract', 'views'], $where);
    return $articles;
});

$router->get('/latest', function() use ($database) {
	$where = [];
	$where['ORDER'] = ['created' => 'DESC'];
	$where['LIMIT'] = 5;
    $articles = $database->select('articles', ['id', 'title', 'abstract', 'created'], $where);
    return $articles;
});

$router->get('/modified', function() use ($database) {
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
