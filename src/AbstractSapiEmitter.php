<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Psr\Http\Message\ResponseInterface;

abstract class AbstractSapiEmitter implements EmitterInterface
{
    /**
     * Inject the Content-Length header if is not already present.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function injectContentLength(ResponseInterface $response): ResponseInterface
    {
        if (! $response->hasHeader('Content-Length')) {
            // PSR-7 indicates int OR null for the stream size; for null values,
            // we will not auto-inject the Content-Length.
            if (null !== $response->getBody()->getSize()) {
                return $response->withHeader('Content-Length', (string) $response->getBody()->getSize());
            }
        }

        return $response;
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
        header(vsprintf(
            'HTTP/%s %d%s',
            [
                $response->getProtocolVersion(),
                $response->getStatusCode(),
                rtrim(' ' . $response->getReasonPhrase())
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
            $first = true;

            foreach ($values as $value) {
                header(sprintf(
                    '%s: %s',
                    $name,
                    $value
                ), $first);

                $first = false;
            }
        }
    }

    /**
     * Perform garbage collection.
     *
     * @return void
     */
    protected function collectGarbage(): void
    {
        // try to enable garbage collection
        if (! gc_enabled()) {
            @gc_enable();
        }
        // collect garbage only if garbage; collection is enabled
        if (gc_enabled()) {
            gc_collect_cycles();
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
        $filtered = str_replace('-', ' ', $header);
        $filtered = ucwords($filtered);

        return str_replace(' ', '-', $filtered);
    }
}
