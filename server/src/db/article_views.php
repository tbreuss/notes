<?php

namespace db\article_views;

use function common\{
    medoo
};

function find_latest_date(int $articleId): string
{
    $date = medoo()->get(
        'article_views',
        'created',
        [
            'article_id' => $articleId,
            'ORDER' => ['created' => 'DESC']
        ]
    );
    return $date;
}
