# Codeception Jasny MVC Module

[![Build Status](https://travis-ci.org/jasny/codeception-module.svg?branch=master)](https://travis-ci.org/jasny/codeception-module)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/codeception-module/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/codeception-module/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/codeception-module/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/codeception-module/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/956e3ad0-ff30-4d26-acf9-2b8ea24bd1f0/mini.png)](https://insight.sensiolabs.com/projects/956e3ad0-ff30-4d26-acf9-2b8ea24bd1f0)
[![Packagist Stable Version](https://img.shields.io/packagist/v/jasny/codeception-module.svg)](https://packagist.org/packages/jasny/codeception-module)
[![Packagist License](https://img.shields.io/packagist/l/jasny/codeception-module.svg)](https://packagist.org/packages/jasny/codeception-module)

This module allows you to run tests using [Jasny MVC](http://www.github.com/jasny/mvc/).

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

The container an object that takes care or depency injection. It must be an object the implements [`Interop\Container\ContainerInterface`](https://github.com/container-interop/container-interop).
If you're project doesn't use an dependency injection container, you can use [Picotainer](https://github.com/thecodingmachine/picotainer),
which is automatically installed with this codeception module.

The container must contain an item for `Jasny\RouterInterface`.

Example of `container.php` using Picotainer.

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

## Error handler

The container may also contain a [Jasny Error Handler](https://github.com/jasny/error-handler). If a fatal error is caught
by the error handler, the output is typically a nice message intended for the end user. It doesn't contain any information
about the error itself.

If the container has a `Jasny\ErrorHandlerInterface` object, it will output the error as debug information on a failed
test. To see the error use the `--debug` flag when running `composer run`.

```php
use Mouf\Picotainer\Picotainer;
use Jasny\Router;
use Jasny\Router\Routes\Glob as Routes;
use Jasny\RouterInterface;
use Jasny\ErrorHandler;
use Jasny\ErrorHandlerInterface;

return new Picotainer([
    RouterInterface::class => function($container) {
        $router = new Router(new Routes([
            '/' => ['controller' => 'foo'],
            // ...
        ]));
        
        $errorHandler = $container->get(ErrorHandlerInterface::class);
        $router->add($errorHandler->asMiddleware());
        
        return $router;
    },
    ErrorHandlerInterface::class => function() {
        $errorHandler = new ErrorHandler();
        
        $errorHandler->logUncaught(E_PARSE | E_ERROR | E_WARNING | E_USER_WARNING);
        $errorHandler->logUncaught(Exception::class);
        $errorHandler->logUncaught(Error::class); // PHP7 only
        
        return $errorHandler;
    });
]);
```

## API

* container - The container
* client - [BrowserKit](http://symfony.com/doc/current/components/browser_kit.html) client
