<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class SapiEmitter extends AbstractSapiEmitter
{
    /**
     * Maximum output buffering level to unwrap.
     *
     * @var int|null
     */
    protected $maxBufferLevel;

    /**
     * Set the maximum output buffering level.
     *
     * @param int $maxBufferLevel
     */
    public function setMaxBufferLevel(int $maxBufferLevel)
    {
        $this->maxBufferLevel = $maxBufferLevel;
    }

    /**
     * {@inheritdoc}
     */
    public function emit(ResponseInterface $response)
    {
        if (headers_sent()) {
            throw new RuntimeException('Unable to emit response: headers already sent.');
        }

        $response = $this->injectContentLength($response);

        // Emit the HTTP status line
        $this->emitStatusLine($response);
        // Emit the HTTP headers
        $this->emitHeaders($response);
        // Emit the body
        $this->sendBody($response);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (php_sapi_name() == 'cli' || php_sapi_name() == 'phpdbg') {
            // Command line output buffering is disabled in cli by default.
            $this->collectGarbage();

            Util::closeOutputBuffers($this->maxBufferLevel ?? ob_get_level(), true);
        }
    }

    /**
     * Sends the body of the response.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     */
    protected function sendBody(ResponseInterface $response)
    {
        echo $response->getBody();
    }
}
