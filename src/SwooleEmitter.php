<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response as SwooleResponse;

class SwooleEmitter extends AbstractSapiEmitter
{
    /**
     * Chunk size for http.
     *
     * @var int
     *
     * @see https://www.swoole.co.uk/docs/modules/swoole-http-server/methods-properties#swoole-http-response-write
     */
    private $chunkSize;

    /**
     * A Swoole Response instance.
     *
     * @var \Swoole\Http\Response
     */
    private $swooleResponse;

    /**
     * Create a new SwooleEmitter instance.
     *
     * @param \Swoole\Http\Response $swooleResponse
     * @param int                   $chunkSize      default is 2MB
     */
    public function __construct(SwooleResponse $swooleResponse, int $chunkSize = 2097152)
    {
        $this->swooleResponse = $swooleResponse;
        $this->chunkSize      = $chunkSize;
    }

    /**
     * Get the Response chunk size.
     *
     * @return int
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * {@inheritdoc}
     */
    public function emit(ResponseInterface $response): void
    {
        $this->emitHeaders($response);

        // Set the status _after_ the headers, because of PHP's "helpful" behavior with location headers.
        $this->emitStatusLine($response);

        $this->sendBody($response);
    }

    /**
     * {@inheritdoc}
     */
    protected function emitHeaders(ResponseInterface $response): void
    {
        $this->swooleResponse->status($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            $name = $this->toWordCase($name);

            $this->swooleResponse->header($name, \implode(', ', $values));
        }
    }

    /**
     * Sends the message body of the response.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return void
     */
    private function sendBody(ResponseInterface $response): void
    {
        $body = $response->getBody();
        $body->rewind();

        if ($body->getSize() <= $this->chunkSize) {
            $this->swooleResponse->end($body->getContents());

            return;
        }

        while (! $body->eof()) {
            $this->swooleResponse->write($body->read($this->chunkSize));
        }

        $this->swooleResponse->end();
    }
}
