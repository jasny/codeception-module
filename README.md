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

* router: Function name that returns the router or path to file that returns the router

```yaml
\Jasny\Codeception\Module:
  router: App::router()
```

OR

```yaml
\Jasny\Codeception\Module:
  router: path/to/router.php
```

Example of `router.php`.

```php
use Jasny\Router;
use Jasny\Router\Routes\Glob as Routes;

return new Router(new Routes([
    '/' => ['controller' => 'foo'],
    // ...
]));

```

## API

* router -  instance of `\Jasny\Router`
* client - [BrowserKit](http://symfony.com/doc/current/components/browser_kit.html) client
