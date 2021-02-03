<?php

declare(strict_types=1);

/**
 * Copyright (c) 2017-2021 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/php-library-template
 */

namespace Narrowspark\HttpEmitter;

use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;

if (! \function_exists('Narrowspark\\HttpEmitter\\headers_sent')) {
    /**
     * Have headers been sent?
     *
     * @param null|string $file
     * @param null|int    $line
     *
     * @return bool false
     */
    function headers_sent(&$file = null, &$line = null): bool
    {
        $sent = HeaderStack::$headersSent;

        if ($sent) {
            $file = HeaderStack::$headersFile;
            $line = HeaderStack::$headersLine;
        }

        return $sent;
    }
}

if (! \function_exists('Narrowspark\\HttpEmitter\\header')) {
    /**
     * Emit a header, without creating actual output artifacts.
     *
     * @param string   $string
     * @param bool     $replace
     * @param null|int $statusCode
     */
    function header($string, $replace = true, $statusCode = null): void
    {
        HeaderStack::push(
            [
                'header' => $string,
                'replace' => $replace,
                'status_code' => $statusCode,
            ]
        );
    }
}
