<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;

/**
 * Have headers been sent?
 *
 * @param null|string $file
 * @param null|int    $line
 *
 * @return bool false
 */
function headers_sent($file, $line): bool
{
    return false;
}

/**
 * Emit a header, without creating actual output artifacts.
 *
 * @param string   $string
 * @param bool     $replace
 * @param int|null $statusCode
 *
 * @return void
 */
function header($string, $replace = true, $statusCode = null): void
{
    HeaderStack::push(
        [
            'header'      => $string,
            'replace'     => $replace,
            'status_code' => $statusCode,
        ]
    );
}
