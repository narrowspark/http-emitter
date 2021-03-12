<?php

declare(strict_types=1);

/**
 * Copyright (c) 2017-2021 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/http-emitter
 */

namespace Narrowspark\HttpEmitter\Tests;

use Laminas\Diactoros\Response;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Narrowspark\HttpEmitter\SapiEmitter;
use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;
use Narrowspark\HttpEmitter\Util;
use Psr\Http\Message\StreamInterface;
use function Safe\ob_end_clean;

/**
 * @internal
 *
 * @medium
 * @covers \Narrowspark\HttpEmitter\Util
 */
final class UtilTest extends MockeryTestCase
{
    private SapiEmitter $emitter;

    protected function setUp(): void
    {
        parent::setUp();

        HeaderStack::reset();

        HeaderStack::$headersSent = false;
        HeaderStack::$headersFile = null;
        HeaderStack::$headersLine = null;

        $this->emitter = new SapiEmitter();
    }

    public function testEmitsResponseHeaders(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        ob_start();

        $this->emitter->emit(Util::injectContentLength($response));

        ob_end_clean();

        self::assertTrue(HeaderStack::has('HTTP/1.1 200 OK'));
        self::assertTrue(HeaderStack::has('Content-Type: text/plain'));
        self::assertTrue(HeaderStack::has('Content-Length: 8'));
    }

    public function testDoesNotInjectContentLengthHeaderIfStreamSizeIsUnknown(): void
    {
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('__toString')
            ->once()
            ->andReturn('Content!');
        $stream->shouldReceive('getSize')
            ->andReturnNull();

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);

        $response = Util::injectContentLength($response);

        ob_start();

        $this->emitter->emit($response);

        ob_end_clean();

        foreach (HeaderStack::stack() as $header) {
            self::assertStringNotContainsStringIgnoringCase('Content-Length:', (string) $header['header']);
        }
    }

    /**
     * @
     */
    public function testCloseOutputBuffersWithFlush(): void
    {
        $response = new Response();
        $response
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        ob_start();

        $this->emitter->emit($response);

        self::assertSame(2, ob_get_level());
        // flush
        Util::closeOutputBuffers(1, true);

        self::assertSame(1, ob_get_level());
    }

    public function testCloseOutputBuffersWithClean(): void
    {
        $response = new Response();
        $response
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        ob_start();

        $this->emitter->emit($response);

        $content = ob_get_contents(); // 'Content!'

        // clear
        Util::closeOutputBuffers(1, false);

        self::assertNotSame(ob_get_contents(), $content);
    }
}
