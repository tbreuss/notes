<?php

use Medoo\Medoo as Medoo;

// todo: replace this in database
function handle_custom_tags(array $article, string $key): array
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

function sanitize_tags(string $strtags): string
{
    $tags = explode(',', $strtags);
    $sanitized = array_map('trim', $tags);
    return implode(',', $sanitized);
}

function save_tags(string $strtags)
{
    $tags = explode(',', $strtags);
    foreach ($tags as $tag) {
        save_tag($tag);
    }
}

function save_tag(array $tag): int
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
        return $id;
    } else {
        $database->insert('tags', [
            'name' => $tag,
            'frequency' => 1,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s')
        ]);
        return $database->id();
    }
}

function get_database(): Medoo
{
    static $database;
    if (is_null($database)) {
        $config = require '../config/database.php';
        $database = new Medoo($config);
    }
    return $database;
}

function get_pdo(): PDO
{
    $database = get_database();
    return $database->pdo;
}

function find_selected_articles(array $order): array
{
    $where = [];
    $where['ORDER'] = $order;
    $where['LIMIT'] = 5;
    $articles = get_database()->select('articles', ['id', 'title', 'abstract', 'modified'], $where);
    return $articles;
}

function find_all_tags(string $sort): array
{
    $orders = [
        'name' => ['name' => 'ASC'],
        'frequency' => ['frequency' => 'DESC'],
        'changed' => ['modified' => 'DESC'],
        'created' => ['created' => 'DESC'],
        'default' => ['name' => 'ASC']
    ];
    $order = isset($orders[$sort]) ? $orders[$sort] : $orders['default'];
    $articles = get_database()->select('tags', ['id', 'name', 'frequency'], ['ORDER' => $order]);
    return $articles;
}

function find_one_tag(int $id): array
{
    $article = get_database()->get('tags', '*', ['id' => $id]);
    return $article;
}

function find_one_article(int $id, bool $throwException = true): array
{
    $article = get_database()->get('articles', '*', ['id' => $id]);
    if ($throwException && empty($article)) {
        throw new \Exception('Not found');
    }
    $article['tags'] = explode(',', $article['tags']);
    $article = handle_custom_tags($article, 'content');
    return $article;
}

function update_article_views(int $id): int
{
    $data = get_database()->update('articles', ['views[+]' => 1], ['id' => $id]);
    return $data->rowCount();
}

function add_article(array $data): int
{
    $data['created'] = date('Y-m-d H:i:s');
    $data['modified'] = date('Y-m-d H:i:s');
    $data['tags'] = sanitize_tags($data['tags']);
    get_database()->insert('articles', $data);
    $id = get_database()->id();
    save_tags($data['tags']);
    return $id;
}

function validate_article(array $data): array
{
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
    return $errors;
}

function get_paging(int $totalCount, int $currentPage, int $itemsPerPage): array
{
    return [
        'itemsPerPage' => $itemsPerPage,
        'totalItems' => $totalCount,
        'currentPage' => $currentPage,
        'pageCount' => ceil($totalCount / $itemsPerPage)
    ];
}

function find_all_articles(string $q, array $tags, string $order, int $page, int $itemsPerPage): array
{
    $sql = 'SELECT SQL_CALC_FOUND_ROWS id, title, abstract, tags FROM articles WHERE 1=1';

    $params = [];

    if (!empty($q)) {
        $q = '%' . $q . '%';
        $sql .= ' AND (title LIKE ? OR abstract LIKE ? OR content LIKE ?)';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    if (!empty($tags)) {
        foreach ($tags as $tag) {
            $sql .= ' AND FIND_IN_SET(?, tags)>0';
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
    if (isset($orders[$order])) {
        $sql .= ' ORDER BY ' . $orders[$order];
    }

    $sql .= ' LIMIT ' . ($page - 1) * $itemsPerPage . ', ' . $itemsPerPage;

    $stmt = get_pdo()->prepare($sql);
    $stmt->execute($params);

    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($articles as $i => $a) {
        $articles[$i]['tags'] = explode(',', $a['tags']);
    }

    return $articles;
}

function find_found_rows(): int
{
    $sql = 'SELECT FOUND_ROWS()';
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute();
    $foundRows = $stmt->fetchColumn();
    return $foundRows;
}

function get_php_input(): array
{
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    return $data;
}

function find_selected_tags(string $q, array $tags): array
{
    $sql = '
		SELECT t.name, count(a.id) AS frequency
		FROM tags t
		INNER JOIN articles a ON FIND_IN_SET(t.name, a.tags)>0 
		WHERE 1=1
	';

    $params = [];

    if (!empty($q)) {
        $q = '%' . $q . '%';
        $sql .= ' AND (a.title LIKE ? OR a.abstract LIKE ? OR a.content LIKE ?)';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    if (!empty($tags)) {
        foreach ($tags as $tag) {
            $sql .= ' AND FIND_IN_SET(?, a.tags)>0';
            $params[] = $tag;
        }
    }

    $sql .= '
		GROUP BY t.name
		ORDER BY frequency DESC
		LIMIT 40
	';

    $stmt = get_pdo()->prepare($sql);
    $stmt->execute($params);
    $tags = $stmt->fetchAll(PDO::FETCH_COLUMN);

    sort($tags);
    return $tags;
}

function get_url_path(): string
{
    $strPathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
    $urlPath = parse_url($strPathInfo, PHP_URL_PATH);
    return $urlPath;
}

function get_request_method(): string
{
    return $_SERVER['REQUEST_METHOD'];
}

// todo: doesn't work properly
function get_query_var(string $name, $default = null)
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
