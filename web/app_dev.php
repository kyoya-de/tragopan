<?php
include __DIR__ . '/../vendor/autoload.php';

use KyoyaDe\Tragopan\Application;

$app = new Application(
    [
        'debug' => true,
        'kernel.root_dir' => __DIR__ . '/..',
        'kernel.cache_dir' => __DIR__ . '/../var/cache',
    ]
);
$app->addRoutes();
$app->run();

