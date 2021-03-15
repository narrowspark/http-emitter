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

use Psr\Http\Message\ResponseInterface;
use const CONNECTION_NORMAL;
use function Safe\preg_match;
use function Safe\substr;
use function strlen;

/**
 * @see \Narrowspark\HttpEmitter\Tests\SapiStreamEmitterTest
 */
final class SapiStreamEmitter extends AbstractSapiEmitter
{
    private const CONTENT_PATTERN_REGEX = '/(?P<unit>[\w]+)\s+(?P<first>\d+)-(?P<last>\d+)\/(?P<length>\d+|\*)/';

    /**
     * Maximum output buffering size for each iteration.
     */
    protected int $maxBufferLength = 8192;

    /**
     * Set the maximum output buffering level.
     */
    public function setMaxBufferLength(int $maxBufferLength): static
    {
        $this->maxBufferLength = $maxBufferLength;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function emit(ResponseInterface $response): void
    {
        $this->assertNoPreviousOutput();

        $this->emitHeaders($response);

        // Set the status _after_ the headers, because of PHP's "helpful" behavior with location headers.
        $this->emitStatusLine($response);

        flush();

        $range = $this->parseContentRange($response->getHeaderLine('Content-Range'));

        if ($range !== null && $range[0] === 'bytes') {
            $this->emitBodyRange($range, $response, $this->maxBufferLength);
        } else {
            $this->emitBody($response, $this->maxBufferLength);
        }

        $this->closeConnection();
    }

    /**
     * Parse content-range header
     * http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.16.
     *
     * @psalm-return null|array{0: string, 1: int, 2: int, 3: string|int} returns null if no content range or an invalid content range is provided
     */
    private function parseContentRange(string $header): ?array
    {
        if (preg_match(self::CONTENT_PATTERN_REGEX, $header, $matches) === 1) {
            return [
                (string) $matches['unit'],
                (int) $matches['first'],
                (int) $matches['last'],
                $matches['length'] === '*' ? '*' : (int) $matches['length'],
            ];
        }

        return null;
    }

    /**
     * Emit a range of the message body.
     *
     * @psalm-param array{0: string, 1: int, 2: int, 3: string|int} $range
     */
    private function emitBodyRange(array $range, ResponseInterface $response, int $maxBufferLength): void
    {
        [/* $unit */, $first, $last, /* $length */] = $range;

        $body = $response->getBody();

        $length = $last - $first + 1;

        if ($body->isSeekable()) {
            $body->seek($first);
            $first = 0;
        }

        if (! $body->isReadable()) {
            echo substr($body->getContents(), $first, $length);

            return;
        }

        $remaining = $length;

        while ($remaining >= $maxBufferLength && ! $body->eof()) {
            $contents = $body->read($maxBufferLength);
            $remaining -= strlen($contents);

            echo $contents;

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }

        if ($remaining <= 0) {
            return;
        }

        if ($body->eof()) {
            return;
        }

        echo $body->read($remaining);
    }

    /**
     * Sends the message body of the response.
     */
    private function emitBody(ResponseInterface $response, int $maxBufferLength): void
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        if (! $body->isReadable()) {
            echo $body;

            return;
        }

        while (! $body->eof()) {
            echo $body->read($maxBufferLength);

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
    }
}
