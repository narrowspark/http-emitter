<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class Emitter extends AbstractSapiEmitter
{
    /**
     * {@inheritdoc}
     */
    public function emit(ResponseInterface $response, int $maxBufferLevel = 0)
    {
        if (headers_sent()) {
            throw new RuntimeException('Unable to emit response: headers already sent.');
        }

        $response = $this->injectContentLength($response);

        //Emit the HTTP status line
        $this->emitStatusLine($response);
        //Emit the HTTP headers
        $this->emitHeaders($response);
        $this->terminateOutputBuffering($maxBufferLevel);
        //Emit the body
        $this->sendBody($response);
        $this->cleanUp();
    }

    /**
     * Sends the body of the response.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    protected function sendBody(ResponseInterface $response)
    {
        echo (string) $response->getBody();
    }
}
