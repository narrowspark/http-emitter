<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

abstract class AbstractSapiEmitter implements EmitterInterface
{
    /**
     * Maximum output buffering size for each iteration.
     *
     * @var int
     */
    protected $maxBufferLength = 8192;

    /**
     * Maximum output buffering level to unwrap.
     *
     * @var null|int
     */
    protected $maxBufferLevel;

    /**
     * Set the maximum output buffering level.
     *
     * @param int $maxBufferLevel
     *
     * @return \Narrowspark\HttpEmitter\EmitterInterface
     */
    public function setMaxBufferLevel(int $maxBufferLevel): EmitterInterface
    {
        $this->maxBufferLevel = $maxBufferLevel;

        return $this;
    }

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
     * Assert that headers haven't already been sent.
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function assertHeadersNotSent(): void
    {
        $file = $line = null;

        if (headers_sent($file, $line)) {
            throw new RuntimeException(\sprintf(
                'Unable to emit response: Headers already sent in file %s on line %s.',
                $file,
                $line
            ));
        }
    }

    /**
     * Emit the status line.
     *
     * Emits the status line using the protocol version and status code from
     * the response; if a reason phrase is availble, it, too, is emitted.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return void
     */
    protected function emitStatusLine(ResponseInterface $response): void
    {
        header(\vsprintf(
            'HTTP/%s %d%s',
            [
                $response->getProtocolVersion(),
                $response->getStatusCode(),
                \rtrim(' ' . $response->getReasonPhrase()),
            ]
        ));
    }

    /**
     * Emit response headers.
     *
     * Loops through each header, emitting each; if the header value
     * is an array with multiple values, ensures that each is sent
     * in such a way as to create aggregate headers (instead of replace
     * the previous).
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return void
     */
    protected function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $header => $values) {
            $name  = $this->toWordCase($header);
            $first = $name === 'Set-Cookie' ? false : true;

            foreach ($values as $value) {
                header(\sprintf(
                    '%s: %s',
                    $name,
                    $value
                ), $first);

                $first = false;
            }
        }
    }

    /**
     * Converts header names to wordcase.
     *
     * @param string $header
     *
     * @return string
     */
    protected function toWordCase(string $header): string
    {
        $filtered = \str_replace('-', ' ', $header);
        $filtered = \ucwords($filtered);

        return \str_replace(' ', '-', $filtered);
    }
}
