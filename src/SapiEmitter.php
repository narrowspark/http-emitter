<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Psr\Http\Message\ResponseInterface;

class SapiEmitter extends AbstractSapiEmitter
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

        $this->sendBody($response);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif ('cli' !== PHP_SAPI) {
            Util::closeOutputBuffers(0, true);
        }
    }

    /**
     * Sends the message body of the response.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    private function sendBody(ResponseInterface $response): void
    {
        echo $response->getBody();
    }
}
