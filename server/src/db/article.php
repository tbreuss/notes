<?php

namespace db\article;

use common;
use jwt;
use db\tag;

function find_selected(array $fields, array $order): array
{
    $where = [];
    $where['ORDER'] = $order;
    $where['LIMIT'] = 5;
    $articles = common\medoo()->select('articles', $fields, $where);
    return $articles;
}

function find_one(int $id, bool $throwException = true): array
{
    $article = common\medoo()->get('articles', '*', ['id' => $id]);
    if ($throwException && empty($article)) {
        throw new \Exception('Not found');
    }
    $article['tags'] = explode(',', $article['tags']);
    $article = handle_custom_tags($article, 'content');
    return $article;
}

function increase_views(int $id): int
{
    $data = common\medoo()->update('articles', ['views[+]' => 1], ['id' => $id]);
    return $data->rowCount();
}


function insert(array $data): int
{
    $user = jwt\get_user_from_token();
    $data['created'] = date('Y-m-d H:i:s');
    $data['created_user'] = $user['id'];
    $data['tags'] = sanitize_tags($data['tags']);
    common\medoo()->insert('articles', $data);
    $id = common\medoo()->id();
    tag\save_all($data['tags'], $user);
    return $id;
}

function validate(array $data): array
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

function find_all(string $q, array $tags, string $order, int $page, int $itemsPerPage): array
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
        'changed' => 'modified DESC, title ASC',
        'created' => 'created DESC, title ASC',
        'default' => 'title ASC',
        'popular' => 'views DESC, title ASC'
    ];
    if (isset($orders[$order])) {
        $sql .= ' ORDER BY ' . $orders[$order];
    }

    $sql .= ' LIMIT ' . ($page - 1) * $itemsPerPage . ', ' . $itemsPerPage;

    $stmt = common\pdo()->prepare($sql);
    $stmt->execute($params);

    $articles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($articles as $i => $a) {
        $articles[$i]['tags'] = explode(',', $a['tags']);
    }

    return $articles;
}

function found_rows(): int
{
    $sql = 'SELECT FOUND_ROWS()';
    $stmt = common\pdo()->prepare($sql);
    $stmt->execute();
    $foundRows = $stmt->fetchColumn();
    return $foundRows;
}

function paging(int $totalCount, int $currentPage, int $itemsPerPage): array
{
    return [
        'itemsPerPage' => $itemsPerPage,
        'totalItems' => $totalCount,
        'currentPage' => $currentPage,
        'pageCount' => ceil($totalCount / $itemsPerPage)
    ];
}

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
