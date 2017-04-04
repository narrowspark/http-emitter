<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;

/**
 * Have headers been sent?
 *
 * @param string $file
 * @param int    $line
 *
 * @return false
 */
function headers_sent(string &$file, int &$line): bool
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
