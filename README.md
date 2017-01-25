# Codeception Jasny MVC Module

[![Build Status](https://travis-ci.org/jasny/codeception-module.svg?branch=master)](https://travis-ci.org/jasny/codeception-module)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/codeception-module/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/codeception-module/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/codeception-module/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/codeception-module/?branch=master)
[![Packagist Stable Version](https://img.shields.io/packagist/v/jasny/codeception-module.svg)](https://packagist.org/packages/jasny/codeception-module)
[![Packagist License](https://img.shields.io/packagist/l/jasny/codeception-module.svg)](https://packagist.org/packages/jasny/codeception-module)

This module allows you to run tests using [Jasny MVC](http://www.github.com/jasny/mvc/).
Based on the [Slim Module](https://github.com/herloct/codeception-slim-module).

## Install

Via commandline:

```shell
composer require --dev jasny/codeception-module
```

Via `composer.json`:

```json
{
  "require-dev": {
    "jasny/codeception-module": "^1.0"
  }
}
```

## Config

* container: Path to file that returns a
  [`Interop\Container\ContainerInterface`](https://github.com/container-interop/container-interop).

```yaml
class_name: FunctionalTester
modules:
    enabled:
        - \Helper\Functional
        - \Jasny\Codeception\Module:
            container: tests/_data/container.php
        - REST:
            depends: \Jasny\Codeception\Module
```

### Container

The container must contain an item for `Jasny\RouterInterface`.

Example of `container.php` using [Picotainer](https://github.com/thecodingmachine/picotainer).

```php
use Mouf\Picotainer\Picotainer;
use Jasny\Router;
use Jasny\Router\Routes\Glob as Routes;
use Jasny\RouterInterface;

return new Picotainer([
    RouterInterface::class => function() {
        return new Router(new Routes([
            '/' => ['controller' => 'foo'],
            // ...
        ]));
    }
]);
```

The cointain may have a `Psr\Http\Message\ServerRequestInterface` and `Psr\Http\Message\ResponseInterface` item.

```php
use Mouf\Picotainer\Picotainer;
use Jasny\Router;
use Jasny\Router\Routes\Glob as Routes;
use Jasny\RouterInterface;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

return new new Picotainer([
    RouterInterface::class => function() {
        return new Router(new Routes([
            '/' => ['controller' => 'foo'],
            // ...
        ]));
    },
    ServerRequestInterface::class => function() {
        return new ServerRequest();
    },
    ResponseInterface::class => function() {
        return new Response();
    }
]);
```

### Legacy code

The Jasny PSR-7 http message implementation is capable of dealing with legacy code by [binding to the global
environment](https://github.com/jasny/http-message#testing-legacy-code).

This allows testing of code that accesses superglobals like `$_GET` and `$_POST` and outputs using `echo` and
`headers()`.

Use `withGlobalEnvironment(true)` for both request and response object. The Codeception module will make sure
output buffering starts and everything is restored after each test.

```php
use Mouf\Picotainer\Picotainer;
use Jasny\Router;
use Jasny\Router\Routes\Glob as Routes;
use Jasny\RouterInterface;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

return new Picotainer([
    RouterInterface::class => function() {
        return new Router(new Routes([
            '/' => ['controller' => 'foo'],
            // ...
        ]));
    },
    ServerRequestInterface::class => function() {
        return (new ServerRequest())->withGlobalEnvironment(true);
    },
    ResponseInterface::class => function() {
        return (new Response())->withGlobalEnvironment(true);
    }
]);
```

## API

* container - The container
* client - [BrowserKit](http://symfony.com/doc/current/components/browser_kit.html) client
