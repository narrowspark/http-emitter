<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Psr\Http\Message\ResponseInterface;

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
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public static function injectContentLength(ResponseInterface $response): ResponseInterface
    {
        // PSR-7 indicates int OR null for the stream size; for null values,
        // we will not auto-inject the Content-Length.
        if (! $response->hasHeader('Content-Length') &&
            $response->getBody()->getSize() !== null
        ) {
            $response = $response->withHeader('Content-Length', (string) $response->getBody()->getSize());
        }

        return $response;
    }
}
