<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Psr\Http\Message\ResponseInterface;

class SapiStreamEmitter extends AbstractSapiEmitter
{
    /**
     * {@inheritdoc}
     */
    public function emit(ResponseInterface $response): void
    {
        $this->assertHeadersNotSent();

        $this->emitStatusLine($response);
        $this->emitHeaders($response);

        Util::closeOutputBuffers($this->maxBufferLevel ?? \ob_get_level(), true);

        $range = $this->parseContentRange($response->getHeaderLine('Content-Range'));

        if (is_array($range) && $range[0] === 'bytes') {
            $this->emitBodyRange($range, $response, $this->maxBufferLength);

            return;
        }

        $this->sendBody($response, $this->maxBufferLength);

        if (function_exists('\fastcgi_finish_request')) {
            \fastcgi_finish_request();
        } elseif ('cli' !== PHP_SAPI) {
            Util::closeOutputBuffers(0, true);
        }
    }

    /**
     * Sends the message body of the response.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param int                                 $maxBufferLength
     */
    private function sendBody(ResponseInterface $response, int $maxBufferLength): void
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
        }
    }

    /**
     * Emit a range of the message body.
     *
     * @param array                               $range
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param int                                 $maxBufferLength
     */
    private function emitBodyRange(array $range, ResponseInterface $response, int $maxBufferLength): void
    {
        [$unit, $first, $last, $length] = $range;

        $body = $response->getBody();

        $length = $last - $first + 1;

        if ($body->isSeekable()) {
            $body->seek($first);
            $first = 0;
        }

        if (! $body->isReadable()) {
            echo \mb_substr($body->getContents(), $first, $length);

            return;
        }

        $remaining = $length;

        while ($remaining >= $maxBufferLength && ! $body->eof()) {
            $contents   = $body->read($maxBufferLength);
            $remaining -= \mb_strlen($contents);

            echo $contents;
        }

        if ($remaining > 0 && ! $body->eof()) {
            echo $body->read($remaining);
        }
    }

    /**
     * Parse content-range header
     * http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.16.
     *
     * @param string $header
     *
     * @return array|false [unit, first, last, length]; returns false if no
     *                     content range or an invalid content range is provided
     */
    private function parseContentRange($header)
    {
        if (\preg_match('/(?P<unit>[\w]+)\s+(?P<first>\d+)-(?P<last>\d+)\/(?P<length>\d+|\*)/', $header, $matches)) {
            return [
                $matches['unit'],
                (int) $matches['first'],
                (int) $matches['last'],
                $matches['length'] === '*' ? '*' : (int) $matches['length'],
            ];
        }

        return false;
    }
}
