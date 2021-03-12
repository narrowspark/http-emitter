<h2 align="center">Http Response Emitter</h2>
<h3 align="center">Emits a Response to the PHP Server API.</h3>
<p align="center">
    <a href="https://github.com/narrowspark/http-emitter/releases"><img src="https://img.shields.io/packagist/v/narrowspark/http-emitter.svg?style=flat-square"></a>
    <a href="https://php.net/"><img src="https://img.shields.io/badge/php-%5E8.0.0-8892BF.svg?style=flat-square"></a>
    <a href="https://travis-ci.org/narrowspark/http-emitter"><img src="https://img.shields.io/travis/rust-lang/rust/master.svg?style=flat-square"></a>
    <a href="https://codecov.io/gh/narrowspark/http-emitter"><img src="https://img.shields.io/codecov/c/github/narrowspark/http-emitter/master.svg?style=flat-square"></a>
    <a href="https://github.com/semantic-release/semantic-release"><img src="https://img.shields.io/badge/%20%20%F0%9F%93%A6%F0%9F%9A%80-semantic--release-e10079.svg?style=flat-square"></a>
    <a href=".github/CODE_OF_CONDUCT.md"><img src="https://img.shields.io/badge/Contributor%20Covenant-2.0-4baaaa.svg?style=flat-square"></a>
    <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square"></a>
</p>

The available emitter implementations are.

    - `Narrowspark\HttpEmitter\SapiEmitter`
    - `Narrowspark\HttpEmitter\SapiStreamEmitter`.

> **Note:** each use the native PHP functions `header()` and ```echo``` to emit the response.

> **Note:** if headers have been sent, or the output buffer exists, and has a non-zero length, the emitters raise an exception, as mixed PSR-7 / output buffer content creates a blocking issue.
>
> If you are emitting content via `echo`, `print`, `var_dump`, etc., or not catching PHP errors / exceptions, you will need to either fix your app to always work with a PSR-7 response.
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

If you missing the ```Content-Length``` header you can use the `\Narrowspark\HttpEmitter\Util\Util::injectContentLength` static method.

```php
<?php

use Narrowspark\HttpEmitter\Util;

$response = new \Response();

$response = Util::injectContentLength($response);
```

## Versioning

This library follows semantic versioning, and additions to the code ruleset are performed in major releases.

## Changelog

Please have a look at [`CHANGELOG.md`](CHANGELOG.md).

## Contributing

If you would like to help take a look at the [list of issues](https://github.com/narrowspark/http-emitter/issues) and check our [Contributing](.github/CONTRIBUTING.md) guild.

## Code of Conduct

Please have a look at [`CODE_OF_CONDUCT.md`](.github/CODE_OF_CONDUCT.md).

## License

This package is licensed using the MIT License.

Please have a look at [`LICENSE.md`](LICENSE.md).
