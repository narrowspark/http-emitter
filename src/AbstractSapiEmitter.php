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

namespace Narrowspark\HttpEmitter;

use Narrowspark\HttpEmitter\Contract\RuntimeException;
use Psr\Http\Message\ResponseInterface;
use const PHP_SAPI;
use function function_exists;
use function in_array;
use function Safe\fastcgi_finish_request;
use function Safe\sprintf;
use function Safe\vsprintf;

abstract class AbstractSapiEmitter
{
    /**
     * Emit a response.
     *
     * Emits a response, including status line, headers, and the message body,
     * according to the environment.
     *
     * Implementations of this method may be written in such a way as to have
     * side effects, such as usage of header() or pushing output to the
     * output buffer.
     *
     * Implementations MAY raise exceptions if they are unable to emit the
     * response; e.g., if headers have already been sent.
     */
    abstract public function emit(ResponseInterface $response): void;

    /**
     * Assert either that no headers been sent or the output buffer contains no content.
     *
     * @throws \Narrowspark\HttpEmitter\Contract\RuntimeException
     */
    protected function assertNoPreviousOutput(): void
    {
        $file = $line = null;

        if (headers_sent($file, $line)) {
            throw new RuntimeException(sprintf(
                'Unable to emit response: Headers already sent in file %s on line %s. '
                . 'This happens if echo, print, printf, print_r, var_dump, var_export or similar statement that writes to the output buffer are used.',
                $file,
                (string) $line
            ));
        }

        if (ob_get_level() <= 0) {
            return;
        }

        if (ob_get_length() <= 0) {
            return;
        }

        throw new RuntimeException('Output has been emitted previously; cannot emit response.');
    }

    /**
     * Emit the status line.
     *
     * Emits the status line using the protocol version and status code from
     * the response; if a reason phrase is availble, it, too, is emitted.
     *
     * It's important to mention that, in order to prevent PHP from changing
     * the status code of the emitted response, this method should be called
     * after `emitBody()`
     */
    protected function emitStatusLine(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        header(
            vsprintf(
                'HTTP/%s %d%s',
                [
                    $response->getProtocolVersion(),
                    $statusCode,
                    rtrim(' ' . $response->getReasonPhrase()),
                ]
            ),
            true,
            $statusCode
        );
    }

    /**
     * Emit response headers.
     *
     * Loops through each header, emitting each; if the header value
     * is an array with multiple values, ensures that each is sent
     * in such a way as to create aggregate headers (instead of replace
     * the previous).
     */
    protected function emitHeaders(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        foreach ($response->getHeaders() as $header => $values) {
            $name = $this->toWordCase($header);
            $first = $name !== 'Set-Cookie';

            foreach ($values as $value) {
                header(
                    sprintf(
                        '%s: %s',
                        $name,
                        $value
                    ),
                    $first,
                    $statusCode
                );

                $first = false;
            }
        }
    }

    /**
     * Converts header names to wordcase.
     */
    protected function toWordCase(string $header): string
    {
        $filtered = str_replace('-', ' ', $header);
        $filtered = ucwords($filtered);

        return str_replace(' ', '-', $filtered);
    }

    /**
     * Flushes output buffers and closes the connection to the client,
     * which ensures that no further output can be sent.
     */
    protected function closeConnection(): void
    {
        if (! in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
            Util::closeOutputBuffers(0, true);
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }
}
