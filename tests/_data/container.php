<?php

use Mouf\Picotainer\Picotainer;
use Jasny\Router;
use Jasny\Router\Routes\Glob as Routes;
use Jasny\RouterInterface;

return new Picotainer([
    RouterInterface::class => function() {
        return new Router(new Routes([
            '/' => ['controller' => 'test'],
            '/api/ping' => ['controller' => 'test', 'action' => 'ping'],
            '/rest' => ['controller' => 'test', 'action' => 'rest']
        ]));
    }
]);
