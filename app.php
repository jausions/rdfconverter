#!/usr/bin/env php
<?php
declare(strict_types=1);

use Symfony\Component;

require __DIR__ . '/vendor/autoload.php';

$container = new Component\DependencyInjection\ContainerBuilder();
(new Component\DependencyInjection\Loader\PhpFileLoader(
    $container,
    new Component\Config\FileLocator(__DIR__ . '/config'),
))
    ->load('services.php');

$container->compile();

($container->get(App\App::class))
    ->run();
