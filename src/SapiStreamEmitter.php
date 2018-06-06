<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Psr\Http\Message\ResponseInterface;

class SapiStreamEmitter extends AbstractSapiEmitter
{
    /**
     * Maximum output buffering size for each iteration.
     *
     * @var int
     */
    protected $maxBufferLength = 8192;

    /**
     * Set the maximum output buffering level.
     *
     * @param int $maxBufferLength
     *
     * @return \Narrowspark\HttpEmitter\EmitterInterface
     */
    public function setMaxBufferLength(int $maxBufferLength): EmitterInterface
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

        $range = $this->parseContentRange($response->getHeaderLine('Content-Range'));

        if (\is_array($range) && $range[0] === 'bytes') {
            $this->emitBodyRange($range, $response, $this->maxBufferLength);

            return;
        }

        $this->sendBody($response, $this->maxBufferLength);
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
