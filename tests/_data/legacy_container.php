<?php

use Mouf\Picotainer\Picotainer;
use Jasny\Router;
use Jasny\Router\Routes\Glob as Routes;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

ob_start();

return new Picotainer([
    Router::class => function() {
        return new Router(new Routes([
            '/' => ['controller' => 'test'],
            '/api/ping' => ['controller' => 'test', 'action' => 'ping'],
            '/rest' => ['controller' => 'test', 'action' => 'rest']
        ]));
    },
    ServerRequestInterface::class => function() {
        return (new ServerRequest())->withGlobalEnvironment(true);
    },
    ResponseInterface::class => function() {
        return (new Response())->withGlobalEnvironment(true);
    }
]);
