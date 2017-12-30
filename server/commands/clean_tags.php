<?php

ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', sprintf('%s/log/%s-error.log', dirname(__DIR__), date('Y-m')));

require '../vendor/autoload.php';

$medoo = common\medoo();

// Alle angewendeten Tags aus Tabelle "articles"
$tags = db\article\find_all_tags();

// Alle verwaisten Tags aus Tabelle "tags"
$ids = $medoo->select('tags', 'id', [
    'name[!]' => $tags
]);

if (empty($ids)) {
    echo "Alles okay";
} else {

    // Verwaiste Tags entfernen
    $medoo->delete('tags', [
        'id' => $ids
    ]);
    echo sprintf("Verwaiste Tags mit ID (%s) entfernt", implode(',', $ids));
}
