<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter\Tests;

/*
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\Response;

/**
 * @internal
 */
abstract class AbstractEmitterTest extends TestCase
{
    /**
     * @var \Narrowspark\HttpEmitter\AbstractSapiEmitter
     */
    protected $emitter;

    /**
     * {@inheritdoc}
     */
    protected function tearDown(): void
    {
        HeaderStack::reset();
    }

    public function testEmitsMessageBody(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        $this->expectOutputString('Content!');

        $this->emitter->emit($response);

        $this->assertTrue(HeaderStack::has('HTTP/1.1 200 OK'));
        $this->assertTrue(HeaderStack::has('Content-Type: text/plain'));
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

        $this->assertSame($expectedStack, HeaderStack::stack());
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

        $this->assertSame($expectedStack, HeaderStack::stack());
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

        $this->assertSame($expectedStack, HeaderStack::stack());
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
            $this->assertNotContains('Content-Length:', $header['header']);
        }
    }
}
