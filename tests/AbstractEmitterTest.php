<?php

declare(strict_types=1);

/**
 * This file is part of Narrowspark.
 *
 * (c) Daniel Bannert <d.bannert@anolilab.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Narrowspark\HttpEmitter\Tests;

/*
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

use Narrowspark\HttpEmitter\Contract\RuntimeException;
use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\Response;

/**
 * @internal
 */
abstract class AbstractEmitterTest extends TestCase
{
    /** @var \Narrowspark\HttpEmitter\AbstractSapiEmitter */
    protected $emitter;

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        HeaderStack::reset();

        HeaderStack::$headersSent = false;
        HeaderStack::$headersFile = null;
        HeaderStack::$headersLine = null;
    }

    public function testEmitThrowsSentHeadersException(): void
    {
        HeaderStack::$headersSent = true;
        HeaderStack::$headersFile = 'src/AbstractSapiEmitter.php';
        HeaderStack::$headersLine = 20;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf(
            'Unable to emit response: Headers already sent in file %s on line %s. This happens if echo, print, printf, print_r, var_dump, var_export or similar statement that writes to the output buffer are used.',
            HeaderStack::$headersFile,
            (string) HeaderStack::$headersLine
        ));

        $this->emitter->emit($this->arrangeStatus200AndTypeTextResponse());
    }

    public function testEmitsMessageBody(): void
    {
        $response = $this->arrangeStatus200AndTypeTextResponse();
        $response->getBody()->write('Content!');

        $this->expectOutputString('Content!');

        $this->emitter->emit($response);

        self::assertTrue(HeaderStack::has('HTTP/1.1 200 OK'));
        self::assertTrue(HeaderStack::has('Content-Type: text/plain'));
    }

    public function testMultipleSetCookieHeadersAreNotReplaced(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Set-Cookie', 'foo=bar')
            ->withAddedHeader('Set-Cookie', 'bar=baz');

        $this->emitter->emit($response);

        $expectedStack = [
            ['header' => 'Set-Cookie: foo=bar', 'replace' => false, 'status_code' => 200],
            ['header' => 'Set-Cookie: bar=baz', 'replace' => false, 'status_code' => 200],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'status_code' => 200],
        ];

        self::assertSame($expectedStack, HeaderStack::stack());
    }

    public function testDoesNotLetResponseCodeBeOverriddenByPHP(): void
    {
        $response = (new Response())
            ->withStatus(202)
            ->withAddedHeader('Location', 'http://api.my-service.com/12345678')
            ->withAddedHeader('Content-Type', 'text/plain');

        $this->emitter->emit($response);

        $expectedStack = [
            ['header' => 'Location: http://api.my-service.com/12345678', 'replace' => true, 'status_code' => 202],
            ['header' => 'Content-Type: text/plain', 'replace' => true, 'status_code' => 202],
            ['header' => 'HTTP/1.1 202 Accepted', 'replace' => true, 'status_code' => 202],
        ];

        self::assertSame($expectedStack, HeaderStack::stack());
    }

    public function testEmitterRespectLocationHeader(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Location', 'http://api.my-service.com/12345678');

        $this->emitter->emit($response);

        $expectedStack = [
            ['header' => 'Location: http://api.my-service.com/12345678', 'replace' => true, 'status_code' => 200],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'status_code' => 200],
        ];

        self::assertSame($expectedStack, HeaderStack::stack());
    }

    public function testDoesNotInjectContentLengthHeaderIfStreamSizeIsUnknown(): void
    {
        $stream = $this->prophesize(StreamInterface::class);
        $stream->__toString()->willReturn('Content!');
        $stream->getSize()->willReturn(null);
        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream->reveal());

        \ob_start();
        $this->emitter->emit($response);
        \ob_end_clean();

        foreach (HeaderStack::stack() as $header) {
            self::assertStringNotContainsStringIgnoringCase('Content-Length:', $header['header']);
        }
    }

    /**
     * @return \Psr\Http\Message\MessageInterface|Response
     */
    private function arrangeStatus200AndTypeTextResponse()
    {
        return (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
    }
}
