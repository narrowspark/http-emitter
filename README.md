<h2 align="center">Http Response Emitter</h2>
<h3 align="center">Emits a Response to the PHP Server API.</h3>
<p align="center">
    <a href="https://github.com/narrowspark/http-emitter/releases"><img src="https://img.shields.io/packagist/v/narrowspark/http-emitter.svg?style=flat-square"></a>
    <a href="https://php.net/"><img src="https://img.shields.io/badge/php-%5E7.1.0-8892BF.svg?style=flat-square"></a>
    <a href="https://codecov.io/gh/narrowspark/http-emitter"><img src="https://img.shields.io/codecov/c/github/narrowspark/http-emitter/master.svg?style=flat-square"></a>
    <a href="http://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square"></a>
</p>

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

$emitter = new SapiEmitter()
$emitter->emit(new Response());
```

Contributing
------------

If you would like to help take a look at the [list of issues](http://github.com/narrowspark/http-emitter/issues) and check our [Contributing](CONTRIBUTING.md) guild.

> **Note:** Please note that this project is released with a Contributor Code of Conduct. By participating in this project you agree to abide by its terms.


License
---------------

The Narrowspark http-emitter is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)
