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

use Psr\Http\Message\ResponseInterface;
use const PHP_OUTPUT_HANDLER_CLEANABLE;
use const PHP_OUTPUT_HANDLER_FLUSHABLE;
use const PHP_OUTPUT_HANDLER_REMOVABLE;
use function count;
use function Safe\ob_end_clean;
use function Safe\ob_end_flush;

final class Util
{
    /**
     * Private constructor; non-instantiable.
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * Inject the Content-Length header if is not already present.
     */
    public static function injectContentLength(ResponseInterface $response): ResponseInterface
    {
        $responseBody = $response->getBody();

        // PSR-7 indicates int OR null for the stream size; for null values,
        // we will not auto-inject the Content-Length.
        if (! $response->hasHeader('Content-Length')
            && $responseBody->getSize() !== null
        ) {
            /** @var ResponseInterface $response */
            $response = $response->withHeader('Content-Length', (string) $responseBody->getSize());
        }

        return $response;
    }

    /**
     * Cleans or flushes output buffers up to target level.
     *
     * Resulting level can be greater than target level if a non-removable buffer has been encountered.
     *
     * @param int  $maxBufferLevel The target output buffering level
     * @param bool $flush          Whether to flush or clean the buffers
     */
    public static function closeOutputBuffers(int $maxBufferLevel, bool $flush): void
    {
        $status = ob_get_status(true);
        $level = count($status);
        $flags = PHP_OUTPUT_HANDLER_REMOVABLE | ($flush ? PHP_OUTPUT_HANDLER_FLUSHABLE : PHP_OUTPUT_HANDLER_CLEANABLE);

        $s = $status[$level];

        while (--$level > $maxBufferLevel && (bool) ($s) && ($s['del'] ?? ! isset($s['flags']) || $flags === ($s['flags'] & $flags))) {
            if ($flush) {
                ob_end_flush();
            } else {
                ob_end_clean();
            }
        }
    }
}
