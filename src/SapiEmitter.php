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
    public function emit(ResponseInterface $response)
    {
        if (headers_sent()) {
            throw new RuntimeException('Unable to emit response; headers already sent');
        }

        $response = $this->injectContentLength($response);

        $this->emitStatusLine($response);
        $this->emitHeaders($response);
        $this->terminateOutputBuffering(0);
        $this->emitBody($response);
        $this->cleanUp();
    }

    /**
     * Emit the message body.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    protected function emitBody(ResponseInterface $response)
    {
        echo (string) $response->getBody();
    }
}
