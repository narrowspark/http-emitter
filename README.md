<h2 align="center">Http Response Emitter</h2>
<h3 align="center">Emits a Response to the PHP Server API.</h3>
<p align="center">
    <a href="https://github.com/narrowspark/http-emitter/releases"><img src="https://img.shields.io/packagist/v/narrowspark/http-emitter.svg?style=flat-square"></a>
    <a href="https://php.net/"><img src="https://img.shields.io/badge/php-%5E7.1.0-8892BF.svg?style=flat-square"></a>
    <a href="https://travis-ci.org/narrowspark/http-emitter"><img src="https://img.shields.io/travis/rust-lang/rust/master.svg?style=flat-square"></a>
    <a href="https://codecov.io/gh/narrowspark/http-emitter"><img src="https://img.shields.io/codecov/c/github/narrowspark/http-emitter/master.svg?style=flat-square"></a>
    <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square"></a>
</p>

The available emitter implementations are:

    - `Narrowspark\HttpEmitter\SapiEmitter`
    - `Narrowspark\HttpEmitter\SapiStreamEmitter`.
> **Note:** Each use the native PHP functions `header()` and ```echo``` in order to emit the response.
    
    - `Narrowspark\HttpEmitter\SwooleEmitter`
> **Note:** [Swoole](https://www.swoole.co.uk/) is an async programming Framework for PHP that can be used to create high performance HTTP server applications, e.g. web APIs.

If you are using a non-SAPI implementation, you will need to create your own ```Narrowspark\HttpEmitter\EmitterInterface``` implementation.

> Note: If headers have been sent, or the output buffer exists and has a non-zero length, the emitters raise an exception, as mixed PSR-7 / output buffer content creates a blocking issue.
>
> If you are emitting content via `echo`, `print`, `var_dump`, etc., or not catching PHP errors / exceptions, you will need to either fix your application to always work with a PSR-7 response.
> Or provide your own emitters that allow mixed output mechanisms.

Installation
------------

```bash
composer require narrowspark/http-emitter
```

Use
------------

How to use the SapiEmitter:

```php
<?php

use Narrowspark\HttpEmitter\SapiEmitter;

$response = new \Response();
$response->getBody()->write("some content\n");

$emitter = new SapiEmitter();
$emitter->emit($response);
```

How to use the SwooleEmitter:

```php
<?php

use Narrowspark\HttpEmitter\SwooleEmitter;
use Swoole\Http\Server;

$http = new Server('127.0.0.1', 9501);
 
$http->on('start', function ($server) {
    echo 'Swoole http server is started at http://127.0.0.1:9501';
});
 
$http->on("request", function ($request, $response) use ($app) {
    $psr7Response = new \Response();
    $psr7Response->getBody()->write("some content\n");
 
    $emitter = new SwooleEmitter($response);
    $emitter->emit($psr7Response);
});
 
$http->start();
```

If you missing the ```Content-Length``` header you can use the `\Narrowspark\HttpEmitter\Util\Util::injectContentLength` static method.

```php
<?php

use Narrowspark\HttpEmitter\Util;

$response = new \Response();

$response = Util::injectContentLength($response);
``` 

Contributing
------------

If you would like to help take a look at the [list of issues](https://github.com/narrowspark/http-emitter/issues) and check our [Contributing](CONTRIBUTING.md) guild.

> **Note:** Please note that this project is released with a Contributor Code of Conduct. By participating in this project you agree to abide by its terms.

License
---------------

The Narrowspark http-emitter is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT)
