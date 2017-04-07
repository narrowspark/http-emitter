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
     *
     * @return self
     */
    public function setMaxBufferLevel(int $maxBufferLevel): self
    {
        $this->maxBufferLevel = $maxBufferLevel;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function emit(ResponseInterface $response)
    {
        $file = $line = null;

        if (headers_sent($file, $line)) {
            throw new RuntimeException(sprintf(
                'Unable to emit response: Headers already sent in file %s on line %s.',
                $file,
                $line
            ));
        }

        $response = $this->injectContentLength($response);

        $this->emitStatusLine($response);
        $this->emitHeaders($response);

        // Command line output buffering is disabled in cli by default.
        if (php_sapi_name() == 'cli' || php_sapi_name() == 'phpdbg') {
            $this->collectGarbage();

            Util::closeOutputBuffers($this->maxBufferLevel ?? ob_get_level(), true);
        }

        $this->sendBody($response);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
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
