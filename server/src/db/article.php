<?php

namespace db\article;

use db\tag;
use db\user;
use jwt;
use function common\{
    medoo, pdo, array_iunique
};

function find_selected(array $fields, array $order): array
{
    $where = [];
    $where['ORDER'] = $order;
    $where['LIMIT'] = 5;
    $articles = medoo()->select('articles', $fields, $where);
    return $articles;
}

function find_all_tags()
{
    $columns = medoo()->select('articles', 'tags');
    $tags = [];
    foreach ($columns as $strTags) {
        $arrTags = explode(',', $strTags);
        $tags = array_merge($tags, $arrTags);
    }
    $tags = array_iunique($tags);
    sort($tags);
    return $tags;
}

function find_one(int $id, bool $throwException = true): array
{
    $article = medoo()->get('articles', '*', ['id' => $id]);
    if ($throwException && empty($article)) {
        throw new \Exception('Not found');
    }
    $ids = [$article['created_by'], $article['modified_by']];
    $users = user\find_by_user_ids($ids);
    $article['created_by_user'] = $users[$article['created_by']] ?? [];
    $article['modified_by_user'] = $users[$article['modified_by']] ?? [];
    $article['tags'] = explode(',', $article['tags']);
    return $article;
}

function increase_views(int $id)
{
    $user = jwt\get_user_from_token();
    $data = [
        'article_id' => $id,
        'user_id' => $user['id'],
        'created' => date('Y-m-d')
    ];
    $count = medoo()->count('article_views', $data);
    if ($count == 0) {
        medoo()->insert('article_views', $data);
        medoo()->update('articles', ['views[+]' => 1], ['id' => $id]);
    }
}

function insert(array $data): int
{
    $user = jwt\get_user_from_token();
    $data['created'] = date('Y-m-d H:i:s');
    $data['created_by'] = $user['id'];
    $data['tags'] = sanitize_tags($data['tags']);
    medoo()->insert('articles', $data);
    $id = medoo()->id();
    tag\save_all($data['tags'], $user);
    return $id;
}

function update($id, array $data): int
{
    $user = jwt\get_user_from_token();
    $old = find_one($id, true);

    if (is_identic($old, $data)) {
        return 0;
    }

    $data['modified'] = date('Y-m-d H:i:s');
    $data['modified_by'] = $user['id'];
    $data['tags'] = sanitize_tags($data['tags']);
    medoo()->update('articles', $data, ['id' => $id]);
    tag\update_all($old['tags'], explode(',', $data['tags']), $user);
    return 1;
}

function is_identic(array $old, array $new)
{
    if (strcmp($old['title'], $new['title']) !== 0) {
        return false;
    }
    if (strcmp($old['content'], $new['content']) !== 0) {
        return false;
    }

    $arrNewTags = explode(',',$new['tags']);
    $diff1 = array_udiff($old['tags'], $arrNewTags, "strcasecmp");
    $diff2 = array_udiff($arrNewTags, $old['tags'], "strcasecmp");
    if (!empty($diff1) || !empty($diff2)) {
        return false;
    }
    return true;
}

function delete($id)
{
    $user = jwt\get_user_from_token();
    $article = find_one($id, true);
    tag\update_all($article['tags'], [], $user);
    medoo()->delete('articles', ['id' => $id]);
    return true;
}

function validate(array $data): array
{
    $errors = [];
    if (empty($data['title'])) {
        $errors['title'] = 'Bitte einen Titel eingeben';
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
    $sql = 'SELECT SQL_CALC_FOUND_ROWS id, title, tags FROM articles WHERE 1=1';

    $params = [];

    if (!empty($q)) {
        $q = '%' . $q . '%';
        $sql .= ' AND (title LIKE ? OR content LIKE ?)';
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

    $stmt = pdo()->prepare($sql);
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
    $stmt = pdo()->prepare($sql);
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

function sanitize_tags(string $strtags): string
{
    $tags = explode(',', $strtags);
    $sanitized = array_map('trim', $tags);
    $sanitized = array_iunique($sanitized);
    return implode(',', $sanitized);
}
