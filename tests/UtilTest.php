<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter\Tests;

use Narrowspark\HttpEmitter\SapiEmitter;
use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;
use Narrowspark\HttpEmitter\Util;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\Response;

/**
 * @internal
 */
final class UtilTest extends TestCase
{
    protected function setUp(): void
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

        \ob_start();
        $this->emitter->emit($response);
        \ob_end_clean();

        $this->assertTrue(HeaderStack::has('HTTP/1.1 200 OK'));
        $this->assertTrue(HeaderStack::has('Content-Type: text/plain'));
        $this->assertTrue(HeaderStack::has('Content-Length: 8'));
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

        \ob_start();
        $this->emitter->emit($response);
        \ob_end_clean();

        foreach (HeaderStack::stack() as $header) {
            $this->assertNotContains('Content-Length:', $header['header']);
        }
    }

    public function testCloseOutputBuffersWithFlush(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        \ob_start();
        $this->emitter->emit($response);

        $this->assertSame(2, \ob_get_level());
        // flush
        Util::closeOutputBuffers(1, true);

        $this->assertSame(1, \ob_get_level());
    }

    public function testCloseOutputBuffersWithClean(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        \ob_start();
        $this->emitter->emit($response);

        $content = \ob_get_contents(); //'Content!'

        // clear
        Util::closeOutputBuffers(1, false);

        $this->assertNotSame(\ob_get_contents(), $content);
    }
}
