<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter\Tests;

use Narrowspark\HttpEmitter\SwooleEmitter;
use PHPUnit\Framework\TestCase;
use Swoole\Http\Response as SwooleResponse;
use Zend\Diactoros\Response;

/**
 * @internal
 */
final class SwooleEmitterTest extends TestCase
{
    /**
     * @var \Narrowspark\HttpEmitter\SwooleEmitter
     */
    private $emitter;

    /**
     * @var
     */
    private $swooleResponse;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        if (! \extension_loaded('swoole')) {
            $this->markTestSkipped('The Swoole extension is not available');
        }

        $this->swooleResponse = $this->prophesize(SwooleResponse::class);
        $this->emitter        = new SwooleEmitter($this->swooleResponse->reveal());
    }

    public function testEmitsMessageBody(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        $this->emitter->emit($response);

        $this->swooleResponse
            ->status(200)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Content-Type', 'text/plain')
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->end('Content!')
            ->shouldHaveBeenCalled();
    }

    public function testMultipleHeaders(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('Content-Length', '256');

        $this->emitter->emit($response);

        $this->swooleResponse
            ->status(200)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Content-Type', 'text/plain')
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Content-Length', '256')
            ->shouldHaveBeenCalled();
    }

    public function testMultipleSetCookieHeadersAreNotReplaced(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Set-Cookie', 'foo=bar')
            ->withAddedHeader('Set-Cookie', 'bar=baz');

        $this->emitter->emit($response);

        $this->swooleResponse
            ->status(200)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Set-Cookie', 'foo=bar, bar=baz')
            ->shouldHaveBeenCalled();
    }

    public function testEmitWithBigContentBody(): void
    {
        $content = \base64_encode(\random_bytes($this->emitter->getChunkSize())); // CHUNK_SIZE * 1.33333

        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write($content);

        $this->emitter->emit($response);

        $this->swooleResponse
            ->status(200)
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->header('Content-Type', 'text/plain')
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->write(\substr($content, 0, $this->emitter->getChunkSize()))
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->write(\substr($content, $this->emitter->getChunkSize()))
            ->shouldHaveBeenCalled();
        $this->swooleResponse
            ->end()
            ->shouldHaveBeenCalled();
    }
}
