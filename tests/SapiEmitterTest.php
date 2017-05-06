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
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\Response;

class SapiEmitterTest extends AbstractEmitterTest
{
    public function setUp()
    {
        HeaderStack::reset();
        $this->emitter = new SapiEmitter();
    }

    public function testEmitsBufferLevel()
    {
        ob_start();
        echo 'level' . ob_get_level() . ' '; // 2
        ob_start();
        echo 'level' . ob_get_level() . ' '; // 3
        ob_start();
        echo 'level' . ob_get_level() . ' '; // 4

        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        ob_start();

        $this->emitter->emit($response);

        self::assertEquals('Content!', ob_get_contents());

        ob_end_clean();

        self::assertEquals('level4 ', ob_get_contents(), 'current buffer level string must remains after emit');

        ob_end_clean();

        $this->emitter->setMaxBufferLevel(2)->emit($response);

        self::assertEquals('level2 level3 Content!', ob_get_contents(), 'must buffer until specified level');

        ob_end_clean();
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
            self::assertNotContains('Content-Length:', $header);
        }
    }
}
