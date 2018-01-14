<?php

namespace db\tag;

use function common\{
    medoo, pdo
};

function find_all(string $sort): array
{
    $orders = [
        'name' => ['name' => 'ASC'],
        'frequency' => ['frequency' => 'DESC', 'name' => 'ASC'],
        'changed' => ['modified' => 'DESC', 'name' => 'ASC'],
        'created' => ['created' => 'DESC', 'name' => 'ASC'],
        'default' => ['name' => 'ASC']
    ];
    $order = isset($orders[$sort]) ? $orders[$sort] : $orders['default'];
    $articles = medoo()->select('tags', ['id', 'name', 'frequency'], ['ORDER' => $order]);
    return $articles;
}

function find_one(int $id): array
{
    $article = medoo()->get('tags', '*', ['id' => $id]);
    return $article;
}

function update_all(array $oldTags, array $newTags, array $user)
{
    $medoo = medoo();

    $tagsToRemove = array_udiff($oldTags, $newTags, "strcasecmp");
    $tagsToAdd = array_udiff($newTags, $oldTags, "strcasecmp");

    foreach ($tagsToRemove as $tag) {
        $medoo->update('tags', [
            'frequency[-]' => 1,
            'modified' => date('Y-m-d H:i:s'),
            'modified_by' => $user['id']
        ], [
            'name' => $tag
        ]);
    }

    foreach ($tagsToAdd as $tag) {
        $medoo->insert('tags', [
            'name' => $tag,
            'frequency' => 1,
            'created' => date('Y-m-d H:i:s'),
            'created_by' => $user['id']
        ]);
    }

    delete_all_unused();
}

function delete_all_unused()
{
    medoo()->delete('tags', [
        'frequency[<=]' => 0
    ]);
}

function save_all(string $strtags, array $user)
{
    $tags = explode(',', $strtags);
    foreach ($tags as $tag) {
        save_one($tag, $user);
    }
}

function save_one(string $tag, array $user): int
{
    $medoo = medoo();
    $id = $medoo->get('tags', 'id', [
        'name' => $tag
    ]);
    if ($id > 0) {
        $medoo->update('tags', [
            'frequency[+]' => 1,
            'modified' => date('Y-m-d H:i:s'),
            'modified_by' => $user['id']
        ], [
            'id' => $id
        ]);
        return $id;
    } else {
        $medoo->insert('tags', [
            'name' => $tag,
            'frequency' => 1,
            'created' => date('Y-m-d H:i:s'),
            'created_by' => $user['id']
        ]);
        return $medoo->id();
    }
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
        $sql .= ' AND (a.title LIKE ? OR a.content LIKE ?)';
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

    $stmt = pdo()->prepare($sql);
    $stmt->execute($params);
    $tags = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    sort($tags);
    return $tags;
}
