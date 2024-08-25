<?php
declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Console\Command\Command;

return static function (ContainerConfigurator $configurator) {
    $services = $configurator->services()
        ->defaults()
        ->autowire(true);

    $services->set('EasyRdf\Graph');

    $services->instanceof(Command::class)
        ->tag('app.command');

    $services->load('App\\', '../src/*');

    $services->set(\App\App::class)
        ->public()
        ->args([tagged_iterator('app.command')]);
};
