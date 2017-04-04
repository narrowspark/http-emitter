<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;

/**
 * Have headers been sent?
 *
 * @return false
 */
function headers_sent(): bool
{
    return false;
}

/**
 * Emit a header, without creating actual output artifacts
 *
 * @param string $value
 */
function header($value)
{
    HeaderStack::push($value);
}

