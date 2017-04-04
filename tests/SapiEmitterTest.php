<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\Response;
use Narrowspark\HttpEmitter\SapiEmitter;
use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;

class SapiEmitterTest extends TestCase
{
    /**
     * @var \Narrowspark\HttpEmitter\SapiEmitter
     */
    protected $emitter;

    public function setUp()
    {
        HeaderStack::reset();

        $this->emitter = new SapiEmitter();
    }

    public function tearDown()
    {
        HeaderStack::reset();
    }

    public function testEmitsResponseHeaders()
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        ob_start();

        $this->emitter->emit($response);

        ob_end_clean();

        $this->assertContains('HTTP/1.1 200 OK', HeaderStack::stack());
        $this->assertContains('Content-Type: text/plain', HeaderStack::stack());
        $this->assertContains('Content-Length: 8', HeaderStack::stack());
    }

    public function testEmitsMessageBody()
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        $this->expectOutputString('Content!');

        $this->emitter->emit($response);
    }

    public function testDoesNotInjectContentLengthHeaderIfStreamSizeIsUnknown()
    {
        $stream = $this->prophesize(StreamInterface::class);
        $stream->__toString()->willReturn('Content!');
        $stream->getSize()->willReturn(null);
        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream->reveal());

        ob_start();

        $this->emitter->emit($response);

        ob_end_clean();

        foreach (HeaderStack::stack() as $header) {
            $this->assertNotContains('Content-Length:', $header);
        }
    }
}
