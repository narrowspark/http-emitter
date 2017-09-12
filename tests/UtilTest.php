<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter\Tests;

use Narrowspark\HttpEmitter\SapiEmitter;
use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;
use Narrowspark\HttpEmitter\Util;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\Response;

class UtilTest extends TestCase
{
    public function setUp(): void
    {
        HeaderStack::reset();

        $this->emitter = new SapiEmitter();
    }

    public function testEmitsResponseHeaders(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        $response = Util::injectContentLength($response);

        ob_start();
        $this->emitter->emit($response);
        ob_end_clean();

        self::assertContains('HTTP/1.1 200 OK', HeaderStack::stack());
        self::assertContains('Content-Type: text/plain', HeaderStack::stack());
        self::assertContains('Content-Length: 8', HeaderStack::stack());
    }

    public function testDoesNotInjectContentLengthHeaderIfStreamSizeIsUnknown(): void
    {
        $stream = $this->prophesize(StreamInterface::class);
        $stream->__toString()->willReturn('Content!');
        $stream->getSize()->willReturn(null);

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream->reveal());

        $response = Util::injectContentLength($response);

        ob_start();
        $this->emitter->emit($response);
        ob_end_clean();

        foreach (HeaderStack::stack() as $header) {
            self::assertNotContains('Content-Length:', $header);
        }
    }

    public function testCloseOutputBuffersWithFlush()
    {
        $response = (new Response())
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

    public function testCloseOutputBuffersWithClean()
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        ob_start();
        $this->emitter->emit($response);

        $content = \ob_get_contents(); //'Content!'

        // clear
        Util::closeOutputBuffers(1, false);

        self::assertNotSame(\ob_get_contents(), $content);
    }
}
