<?php

declare(strict_types=1);

/**
 * Copyright (c) 2017-2021 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/http-emitter
 */

/** @noRector \Rector\PSR4\Rector\FileWithoutNamespace\NormalizeNamespaceByPSR4ComposerAutoloadRector */

namespace Narrowspark\HttpEmitter;

use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;
use function function_exists;

if (! function_exists('Narrowspark\\HttpEmitter\\headers_sent')) {
    /**
     * Have headers been sent?
     *
     * @return bool false
     */
    function headers_sent(?string &$file = null, ?int &$line = null): bool
    {
        $sent = HeaderStack::$headersSent;

        if ($sent) {
            $file = HeaderStack::$headersFile;
            $line = HeaderStack::$headersLine;
        }

        return $sent;
    }
}

if (! function_exists('Narrowspark\\HttpEmitter\\header')) {
    /**
     * Emit a header, without creating actual output artifacts.
     */
    function header(string $string, bool $replace = true, ?int $statusCode = null): void
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
