<h2 align="center">Http Response Emitter</h2>
<h3 align="center">Emits a Response to the PHP Server API.</h3>
<p align="center">
    <a href="https://github.com/narrowspark/http-emitter/releases"><img src="https://img.shields.io/packagist/v/narrowspark/http-emitter.svg?style=flat-square"></a>
    <a href="https://php.net/"><img src="https://img.shields.io/badge/php-%5E7.1.0-8892BF.svg?style=flat-square"></a>
    <a href="https://travis-ci.org/narrowspark/http-emitter"><img src="https://img.shields.io/travis/rust-lang/rust/master.svg?style=flat-square"></a>
    <a href="https://codecov.io/gh/narrowspark/http-emitter"><img src="https://img.shields.io/codecov/c/github/narrowspark/http-emitter/master.svg?style=flat-square"></a>
    <a href="http://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square"></a>
</p>

A single implementation is currently available, ```Narrowspark\HttpEmitter\SapiEmitter```, which will use the native PHP functions ```header()``` and ```echo``` in order to emit the response.
If you are using a non-SAPI implementation, you will need to create your own ```Narrowspark\HttpEmitter\EmitterInterface``` implementation.

Installation
------------

```bash
composer require narrowspark/http-emitter
```

Use
------------

```php
<?php

use Narrowspark\HttpEmitter\SapiEmitter;

$response = new \Response();
$response->getBody()->write("some content\n");

$emitter = new SapiEmitter();
$emitter->emit($response);
```

If you missing the ```Content-Length``` header you need to use 

```php
<?php

$response = new \Response();

$response = Util::injectContentLength($response);
``` 

Contributing
------------

If you would like to help take a look at the [list of issues](http://github.com/narrowspark/http-emitter/issues) and check our [Contributing](CONTRIBUTING.md) guild.

> **Note:** Please note that this project is released with a Contributor Code of Conduct. By participating in this project you agree to abide by its terms.

License
---------------

The Narrowspark http-emitter is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
