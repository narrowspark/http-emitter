<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;

/**
 * Have headers been sent?
 *
 * @param string|null $file
 * @param int|null    $line
 *
 * @return false
 */
function headers_sent($file, $line): bool
{
    return false;
}

/**
 * Emit a header, without creating actual output artifacts.
 *
 * @param string $value
 */
function header($value)
{
    HeaderStack::push($value);
}
