<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class SapiStreamEmitter extends AbstractSapiEmitter
{
    /**
     * Maximum output buffering size for each iteration.
     *
     * @var int
     */
    private $maxBufferLength = 8192;

    /**
     * Set the maximum output buffering level.
     *
     * @param int $maxBufferLength
     *
     * @return self
     */
    public function setMaxBufferLength(int $maxBufferLength): self
    {
        $this->maxBufferLength = $maxBufferLength;

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

            Util::closeOutputBuffers($this->maxBufferLength, true);
        }

        $range = $this->parseContentRange($response->getHeaderLine('Content-Range'));

        if (is_array($range) && $range[0] === 'bytes') {
            $this->emitBodyRange($range, $response, $this->maxBufferLength);

            return;
        }

        $this->emitBody($response, $this->maxBufferLength);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * Emit the message body.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param int                                 $maxBufferLength
     */
    private function emitBody(ResponseInterface $response, int $maxBufferLength)
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
    private function emitBodyRange(array $range, ResponseInterface $response, int $maxBufferLength)
    {
        list($unit, $first, $last, $length) = $range;

        $body = $response->getBody();

        $length = $last - $first + 1;

        if ($body->isSeekable()) {
            $body->seek($first);
            $first = 0;
        }

        if (! $body->isReadable()) {
            echo mb_substr($body->getContents(), $first, $length);

            return;
        }

        $remaining = $length;

        while ($remaining >= $maxBufferLength && ! $body->eof()) {
            $contents   = $body->read($maxBufferLength);
            $remaining -= mb_strlen($contents);

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
     * @return false|array [unit, first, last, length]; returns false if no
     *                     content range or an invalid content range is provided
     */
    private function parseContentRange($header)
    {
        if (preg_match('/(?P<unit>[\w]+)\s+(?P<first>\d+)-(?P<last>\d+)\/(?P<length>\d+|\*)/', $header, $matches)) {
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
