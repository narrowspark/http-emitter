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

use Narrowspark\HttpEmitter\SapiEmitter;
use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;
use PHPUnit\Framework\TestCase;
use Zend\Diactoros\Response;

abstract class AbstractEmitterTest extends TestCase
{
    /**
     * @var SapiEmitter
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

        self::assertContains('HTTP/1.1 200 OK', HeaderStack::stack());
        self::assertContains('Content-Type: text/plain', HeaderStack::stack());
        self::assertContains('Content-Length: 8', HeaderStack::stack());
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
        $stream = $this->prophesize('Psr\Http\Message\StreamInterface');
        $stream->__toString()->willReturn('Content!');
        $stream->getSize()->willReturn(null);

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream->reveal());

        ob_start();

        $this->emitter->emit($response);

        ob_end_clean();

        foreach (HeaderStack::stack() as $header) {
            self::assertNotContains('Content-Length:', $header);
        }
    }
}
