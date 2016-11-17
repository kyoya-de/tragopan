<?php
include __DIR__ . '/../vendor/autoload.php';

use KyoyaDe\Tragopan\Application;

$app = new Application(
    [
        'debug' => false,
        'kernel.root_dir' => __DIR__ . '/..',
        'kernel.cache_dir' => __DIR__ . '/../var/cache',
    ]
);
$app->boot();
$app->addRoutes();
$app->run();

