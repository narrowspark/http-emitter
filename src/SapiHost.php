<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter;

use Nyholm\Psr7Server\ServerRequestCreator;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * This class implements a SAPI-environment Host for a PSR-15 HTTP Handler.
 */
class SapiHost
{
    /**
     * @var ServerRequestFactoryInterface
     */
    private $serverRequestFactory;

    /**
     * @var UriFactoryInterface
     */
    private $uriFactory;

    /**
     * @var UploadedFileFactoryInterface
     */
    private $uploadedFileFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var ServerRequestCreatorInterface
     */
    private $serverRequestCreator;

    /**
     * Maximum output buffering size for each iteration.
     *
     * @var int
     */
    private $maxBufferLength;

    public function __construct(
        ServerRequestFactoryInterface $serverRequestFactory,
        UriFactoryInterface $uriFactory,
        UploadedFileFactoryInterface $uploadedFileFactory,
        StreamFactoryInterface $streamFactory,
        ResponseFactoryInterface $responseFactory,
        int $maxBufferLength = 8192,
        ?ServerRequestCreatorInterface $serverRequestCreator = null
    ) {
        $this->serverRequestFactory = $serverRequestFactory;
        $this->uriFactory = $uriFactory;
        $this->uploadedFileFactory = $uploadedFileFactory;
        $this->streamFactory = $streamFactory;
        $this->responseFactory = $responseFactory;
        $this->maxBufferLength = $maxBufferLength;

        $this->serverRequestCreator = $serverRequestCreator
            ?: new ServerRequestCreator(
                $serverRequestFactory,
                $uriFactory,
                $uploadedFileFactory,
                $streamFactory
            );
    }

    /**
     * Dispatch the given Handler in the PHP SAPI-environment.
     *
     * Processes the incoming HTTP Request from the SAPI-environment and emits the Response.
     *
     * @param RequestHandlerInterface $handler
     */
    public function dispatch(RequestHandlerInterface $handler): void
    {
        $request = $this->serverRequestCreator->fromGlobals();

        $response = $handler->handle($request);

        $this->assertNoPreviousOutput();

        $this->emitHeaders($response);

        // Set the status _after_ the headers, because of PHP's "helpful" behavior with location headers.
        $this->emitStatusLine($response);

        $range = $this->parseContentRange($response->getHeaderLine('Content-Range'));

        if (\is_array($range) && $range[0] === 'bytes') {
            $this->emitBodyRange($range, $response, $this->maxBufferLength);
        } else {
            $this->emitBody($response, $this->maxBufferLength);
        }

        $this->closeConnection();
    }

    /**
     * Sends the message body of the response.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param int                                 $maxBufferLength
     */
    private function emitBody(ResponseInterface $response, int $maxBufferLength): void
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
    private function emitBodyRange(array $range, ResponseInterface $response, int $maxBufferLength): void
    {
        [$unit, $first, $last, $length] = $range;

        $body = $response->getBody();

        $length = $last - $first + 1;

        if ($body->isSeekable()) {
            $body->seek($first);
            $first = 0;
        }

        if (! $body->isReadable()) {
            echo \substr($body->getContents(), $first, (int) $length);

            return;
        }

        $remaining = $length;

        while ($remaining >= $maxBufferLength && ! $body->eof()) {
            $contents   = $body->read($maxBufferLength);
            $remaining -= \strlen($contents);

            echo $contents;
        }

        if ($remaining > 0 && ! $body->eof()) {
            echo $body->read((int) $remaining);
        }
    }

    /**
     * Parse content-range header
     * http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.16.
     *
     * @param string $header
     *
     * @return null|array [unit, first, last, length]; returns false if no
     *                    content range or an invalid content range is provided
     */
    private function parseContentRange($header): ?array
    {
        if (\preg_match('/(?P<unit>[\w]+)\s+(?P<first>\d+)-(?P<last>\d+)\/(?P<length>\d+|\*)/', $header, $matches) === 1) {
            return [
                $matches['unit'],
                (int) $matches['first'],
                (int) $matches['last'],
                $matches['length'] === '*' ? '*' : (int) $matches['length'],
            ];
        }

        return null;
    }

    /**
     * Assert either that no headers been sent or the output buffer contains no content.
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    private function assertNoPreviousOutput(): void
    {
        $file = $line = null;

        if (headers_sent($file, $line)) {
            throw new RuntimeException(\sprintf(
                'Unable to emit response: Headers already sent in file %s on line %s. ' .
                'This happens if echo, print, printf, print_r, var_dump, var_export or similar statement that writes to the output buffer are used.',
                $file,
                $line
            ));
        }

        if (\ob_get_level() > 0 && \ob_get_length() > 0) {
            throw new RuntimeException('Output has been emitted previously; cannot emit response.');
        }
    }

    /**
     * Emit the status line.
     *
     * Emits the status line using the protocol version and status code from
     * the response; if a reason phrase is availble, it, too, is emitted.
     *
     * It's important to mention that, in order to prevent PHP from changing
     * the status code of the emitted response, this method should be called
     * after `emitBody()`
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     *
     * @return void
     */
    private function emitStatusLine(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        header(
            \vsprintf(
                'HTTP/%s %d%s',
                [
                    $response->getProtocolVersion(),
                    $statusCode,
                    \rtrim(' ' . $response->getReasonPhrase()),
                ]
            ),
            true,
            $statusCode
        );
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
    private function emitHeaders(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        foreach ($response->getHeaders() as $header => $values) {
            $name  = $this->toWordCase($header);
            $first = $name !== 'Set-Cookie';

            foreach ($values as $value) {
                header(
                    \sprintf(
                        '%s: %s',
                        $name,
                        $value
                    ),
                    $first,
                    $statusCode
                );

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
    private function toWordCase(string $header): string
    {
        $filtered = \str_replace('-', ' ', $header);
        $filtered = \ucwords($filtered);

        return \str_replace(' ', '-', $filtered);
    }

    /**
     * Flushes output buffers and closes the connection to the client,
     * which ensures that no further output can be sent.
     *
     * @return void
     */
    private function closeConnection(): void
    {
        if (! \in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            $status = \ob_get_status(true);
            $level  = \count($status);
            $flags  = \PHP_OUTPUT_HANDLER_REMOVABLE | (\PHP_OUTPUT_HANDLER_FLUSHABLE);

            while ($level-- > 0 && (bool) ($s = $status[$level]) && ($s['del'] ?? ! isset($s['flags']) || $flags === ($s['flags'] & $flags))) {
                \ob_end_flush();
            }
        }

        if (\function_exists('fastcgi_finish_request')) {
            \fastcgi_finish_request();
        }
    }
}
