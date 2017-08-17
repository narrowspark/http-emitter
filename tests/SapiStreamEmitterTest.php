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

use Narrowspark\HttpEmitter\SapiStreamEmitter;
use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;
use Prophecy\Argument;
use Psr\Http\Message\StreamInterface;
use Zend\Diactoros\CallbackStream;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response\TextResponse;

class SapiStreamEmitterTest extends AbstractEmitterTest
{
    public function setUp(): void
    {
        HeaderStack::reset();
        $this->emitter = new SapiStreamEmitter();
    }

    public function testEmitCallbackStreamResponse(): void
    {
        $stream = new CallbackStream(function () {
            return 'it works';
        });

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);
        ob_start();

        $this->emitter->emit($response);

        self::assertEquals('it works', ob_get_clean());
    }

    public function testDoesNotInjectContentLengthHeaderIfStreamSizeIsUnknown(): void
    {
        $stream = $this->prophesize(StreamInterface::class);
        $stream->__toString()->willReturn('Content!');
        $stream->isSeekable()->willReturn(false);
        $stream->isReadable()->willReturn(false);
        $stream->eof()->willReturn(true);
        $stream->rewind()->willReturn(true);
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

    public function emitStreamResponseProvider()
    {
        return [
            [true,   true,    '01234567890987654321',   10],
            [true,   true,    '01234567890987654321',   20],
            [true,   true,    '01234567890987654321',  100],
            [true,   true, '01234567890987654321012',   10],
            [true,   true, '01234567890987654321012',   20],
            [true,   true, '01234567890987654321012',  100],
            [true,  false,    '01234567890987654321',   10],
            [true,  false,    '01234567890987654321',   20],
            [true,  false,    '01234567890987654321',  100],
            [true,  false, '01234567890987654321012',   10],
            [true,  false, '01234567890987654321012',   20],
            [true,  false, '01234567890987654321012',  100],
            [false,  true,    '01234567890987654321',   10],
            [false,  true,    '01234567890987654321',   20],
            [false,  true,    '01234567890987654321',  100],
            [false,  true, '01234567890987654321012',   10],
            [false,  true, '01234567890987654321012',   20],
            [false,  true, '01234567890987654321012',  100],
            [false, false,    '01234567890987654321',   10],
            [false, false,    '01234567890987654321',   20],
            [false, false,    '01234567890987654321',  100],
            [false, false, '01234567890987654321012',   10],
            [false, false, '01234567890987654321012',   20],
            [false, false, '01234567890987654321012',  100],
        ];
    }

    /**
     * @param bool   $seekable        Indicates if stream is seekable
     * @param bool   $readable        Indicates if stream is readable
     * @param string $contents        Contents stored in stream
     * @param int    $maxBufferLength maximum buffer length used in the emitter call
     *
     * @dataProvider emitStreamResponseProvider
     */
    public function testEmitStreamResponse(bool $seekable, bool $readable, string $contents, int $maxBufferLength): void
    {
        $size               = mb_strlen($contents);
        $startPosition      = 0;
        $peakBufferLength   = 0;
        $rewindCalled       = false;
        $fullContentsCalled = false;

        $stream = $this->setUpStreamProphecy(
            $contents,
            $size,
            $startPosition,
            function ($bufferLength) use (&$peakBufferLength): void {
                if ($bufferLength > $peakBufferLength) {
                    $peakBufferLength = $bufferLength;
                }
            }
        );

        $stream->isSeekable()->willReturn($seekable);
        $stream->isReadable()->willReturn($readable);

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream->reveal());

        ob_start();
        $this->emitter->setMaxBufferLength($maxBufferLength);
        $this->emitter->emit($response);
        $emittedContents = ob_get_clean();

        if ($seekable) {
            $rewindPredictionClosure = function () use (&$rewindCalled): void {
                $rewindCalled = true;
            };

            $stream->rewind()->should($rewindPredictionClosure);
            $stream->seek(0)->should($rewindPredictionClosure);
            $stream->seek(0, SEEK_SET)->should($rewindPredictionClosure);
        } else {
            $stream->rewind()->shouldNotBeCalled();
            $stream->seek(Argument::type('integer'), Argument::any())->shouldNotBeCalled();
        }

        if ($readable) {
            $stream->__toString()->shouldNotBeCalled();
            $stream->read(Argument::type('integer'))->shouldBeCalled();
            $stream->eof()->shouldBeCalled();
            $stream->getContents()->shouldNotBeCalled();
        } else {
            $fullContentsPredictionClosure = function () use (&$fullContentsCalled): void {
                $fullContentsCalled = true;
            };

            $stream->__toString()->should($fullContentsPredictionClosure);
            $stream->read(Argument::type('integer'))->shouldNotBeCalled();
            $stream->eof()->shouldNotBeCalled();

            if ($seekable) {
                $stream->getContents()->should($fullContentsPredictionClosure);
            } else {
                $stream->getContents()->shouldNotBeCalled();
            }
        }

        $stream->checkProphecyMethodsPredictions();

        self::assertEquals($seekable, $rewindCalled);
        self::assertEquals(! $readable, $fullContentsCalled);
        self::assertEquals($contents, $emittedContents);
        self::assertLessThanOrEqual($maxBufferLength, $peakBufferLength);
    }

    public function emitRangeStreamResponseProvider()
    {
        return [
            [true,   true, ['bytes', 10,  20, '*'],    '01234567890987654321',   5],
            [true,   true, ['bytes', 10,  20, '*'],    '01234567890987654321',  10],
            [true,   true, ['bytes', 10,  20, '*'],    '01234567890987654321', 100],
            [true,   true, ['bytes', 10,  20, '*'], '01234567890987654321012',   5],
            [true,   true, ['bytes', 10,  20, '*'], '01234567890987654321012',  10],
            [true,   true, ['bytes', 10,  20, '*'], '01234567890987654321012', 100],
            [true,   true, ['bytes', 10, 100, '*'],    '01234567890987654321',   5],
            [true,   true, ['bytes', 10, 100, '*'],    '01234567890987654321',  10],
            [true,   true, ['bytes', 10, 100, '*'],    '01234567890987654321', 100],
            [true,   true, ['bytes', 10, 100, '*'], '01234567890987654321012',   5],
            [true,   true, ['bytes', 10, 100, '*'], '01234567890987654321012',  10],
            [true,   true, ['bytes', 10, 100, '*'], '01234567890987654321012', 100],
            [true,  false, ['bytes', 10,  20, '*'],    '01234567890987654321',   5],
            [true,  false, ['bytes', 10,  20, '*'],    '01234567890987654321',  10],
            [true,  false, ['bytes', 10,  20, '*'],    '01234567890987654321', 100],
            [true,  false, ['bytes', 10,  20, '*'], '01234567890987654321012',   5],
            [true,  false, ['bytes', 10,  20, '*'], '01234567890987654321012',  10],
            [true,  false, ['bytes', 10,  20, '*'], '01234567890987654321012', 100],
            [true,  false, ['bytes', 10, 100, '*'],    '01234567890987654321',   5],
            [true,  false, ['bytes', 10, 100, '*'],    '01234567890987654321',  10],
            [true,  false, ['bytes', 10, 100, '*'],    '01234567890987654321', 100],
            [true,  false, ['bytes', 10, 100, '*'], '01234567890987654321012',   5],
            [true,  false, ['bytes', 10, 100, '*'], '01234567890987654321012',  10],
            [true,  false, ['bytes', 10, 100, '*'], '01234567890987654321012', 100],
            [false,  true, ['bytes', 10,  20, '*'],    '01234567890987654321',   5],
            [false,  true, ['bytes', 10,  20, '*'],    '01234567890987654321',  10],
            [false,  true, ['bytes', 10,  20, '*'],    '01234567890987654321', 100],
            [false,  true, ['bytes', 10,  20, '*'], '01234567890987654321012',   5],
            [false,  true, ['bytes', 10,  20, '*'], '01234567890987654321012',  10],
            [false,  true, ['bytes', 10,  20, '*'], '01234567890987654321012', 100],
            [false,  true, ['bytes', 10, 100, '*'],    '01234567890987654321',   5],
            [false,  true, ['bytes', 10, 100, '*'],    '01234567890987654321',  10],
            [false,  true, ['bytes', 10, 100, '*'],    '01234567890987654321', 100],
            [false,  true, ['bytes', 10, 100, '*'], '01234567890987654321012',   5],
            [false,  true, ['bytes', 10, 100, '*'], '01234567890987654321012',  10],
            [false,  true, ['bytes', 10, 100, '*'], '01234567890987654321012', 100],
            [false, false, ['bytes', 10,  20, '*'],    '01234567890987654321',   5],
            [false, false, ['bytes', 10,  20, '*'],    '01234567890987654321',  10],
            [false, false, ['bytes', 10,  20, '*'],    '01234567890987654321', 100],
            [false, false, ['bytes', 10,  20, '*'], '01234567890987654321012',   5],
            [false, false, ['bytes', 10,  20, '*'], '01234567890987654321012',  10],
            [false, false, ['bytes', 10,  20, '*'], '01234567890987654321012', 100],
            [false, false, ['bytes', 10, 100, '*'],    '01234567890987654321',   5],
            [false, false, ['bytes', 10, 100, '*'],    '01234567890987654321',  10],
            [false, false, ['bytes', 10, 100, '*'],    '01234567890987654321', 100],
            [false, false, ['bytes', 10, 100, '*'], '01234567890987654321012',   5],
            [false, false, ['bytes', 10, 100, '*'], '01234567890987654321012',  10],
            [false, false, ['bytes', 10, 100, '*'], '01234567890987654321012', 100],
        ];
    }

    /**
     * @param bool   $seekable        Indicates if stream is seekable
     * @param bool   $readable        Indicates if stream is readable
     * @param array  $range           Emitted range of data [$unit, $first, $last, $length]
     * @param string $contents        Contents stored in stream
     * @param int    $maxBufferLength maximum buffer length used in the emitter call
     *
     * @dataProvider emitRangeStreamResponseProvider
     */
    public function testEmitRangeStreamResponse(bool $seekable, bool $readable, array $range, string $contents, int $maxBufferLength): void
    {
        [$unit, $first, $last, $length]     = $range;
        $size                               = mb_strlen($contents);

        if ($readable && ! $seekable) {
            $startPosition = $first;
        } else {
            $startPosition = 0;
        }

        $peakBufferLength = 0;
        $seekCalled       = false;

        $stream = $this->setUpStreamProphecy(
            $contents,
            $size,
            $startPosition,
            function ($bufferLength) use (&$peakBufferLength): void {
                if ($bufferLength > $peakBufferLength) {
                    $peakBufferLength = $bufferLength;
                }
            }
        );
        $stream->isSeekable()->willReturn($seekable);
        $stream->isReadable()->willReturn($readable);

        $response = (new Response())
            ->withStatus(200)
            ->withHeader('Content-Range', 'bytes ' . $first . '-' . $last . '/*')
            ->withBody($stream->reveal());

        ob_start();
        $this->emitter->setMaxBufferLength($maxBufferLength);
        $this->emitter->emit($response);
        $emittedContents = ob_get_clean();

        $stream->rewind()->shouldNotBeCalled();

        if ($seekable) {
            $seekPredictionClosure = function () use (&$seekCalled): void {
                $seekCalled = true;
            };

            $stream->seek($first)->should($seekPredictionClosure);
            $stream->seek($first, SEEK_SET)->should($seekPredictionClosure);
        } else {
            $stream->seek(Argument::type('integer'), Argument::any())->shouldNotBeCalled();
        }

        $stream->__toString()->shouldNotBeCalled();

        if ($readable) {
            $stream->read(Argument::type('integer'))->shouldBeCalled();
            $stream->eof()->shouldBeCalled();
            $stream->getContents()->shouldNotBeCalled();
        } else {
            $stream->read(Argument::type('integer'))->shouldNotBeCalled();
            $stream->eof()->shouldNotBeCalled();
            $stream->getContents()->shouldBeCalled();
        }

        $stream->checkProphecyMethodsPredictions();

        self::assertEquals($seekable, $seekCalled);
        self::assertEquals(mb_substr($contents, $first, $last - $first + 1), $emittedContents);
        self::assertLessThanOrEqual($maxBufferLength, $peakBufferLength);
    }

    public function emitMemoryUsageProvider()
    {
        return [
            [true,   true,  1000,   20,       null,  512],
            [true,   true,  1000,   20,       null, 4096],
            [true,   true,  1000,   20,       null, 8192],
            [true,  false,   100,  320,       null,  512],
            [true,  false,   100,  320,       null, 4096],
            [true,  false,   100,  320,       null, 8192],
            [false,  true,  1000,   20,       null,  512],
            [false,  true,  1000,   20,       null, 4096],
            [false,  true,  1000,   20,       null, 8192],
            [false, false,   100,  320,       null,  512],
            [false, false,   100,  320,       null, 4096],
            [false, false,   100,  320,       null, 8192],
            [true,   true,  1000,   20,   [25, 75],  512],
            [true,   true,  1000,   20,   [25, 75], 4096],
            [true,   true,  1000,   20,   [25, 75], 8192],
            [false,  true,  1000,   20,   [25, 75],  512],
            [false,  true,  1000,   20,   [25, 75], 4096],
            [false,  true,  1000,   20,   [25, 75], 8192],
            [true,   true,  1000,   20, [250, 750],  512],
            [true,   true,  1000,   20, [250, 750], 4096],
            [true,   true,  1000,   20, [250, 750], 8192],
            [false,  true,  1000,   20, [250, 750],  512],
            [false,  true,  1000,   20, [250, 750], 4096],
            [false,  true,  1000,   20, [250, 750], 8192],
        ];
    }

    /**
     * @param bool       $seekable         Indicates if stream is seekable
     * @param bool       $readable         Indicates if stream is readable
     * @param int        $sizeBlocks       Number the blocks of stream data.
     *                                     Block size is equal to $maxBufferLength.
     * @param int        $maxAllowedBlocks maximum allowed memory usage in block units
     * @param null|array $rangeBlocks      emitted range of data in block units [$firstBlock, $lastBlock]
     * @param int        $maxBufferLength  maximum buffer length used in the emitter call
     *
     * @dataProvider emitMemoryUsageProvider
     */
    public function testEmitMemoryUsage(
        bool $seekable,
        bool $readable,
        int $sizeBlocks,
        int $maxAllowedBlocks,
        ?array $rangeBlocks,
        int $maxBufferLength
    ): void {
        $sizeBytes             = $maxBufferLength * $sizeBlocks;
        $maxAllowedMemoryUsage = $maxBufferLength * $maxAllowedBlocks;
        $peakBufferLength      = 0;
        $peakMemoryUsage       = 0;

        $position = 0;

        if ($rangeBlocks) {
            $first    = $maxBufferLength * $rangeBlocks[0];
            $last     = ($maxBufferLength * $rangeBlocks[1]) + $maxBufferLength - 1;

            if ($readable && ! $seekable) {
                $position = $first;
            }
        }

        $closureTrackMemoryUsage = function () use (&$peakMemoryUsage): void {
            $peakMemoryUsage = max($peakMemoryUsage, memory_get_usage());
        };

        $stream = $this->setUpStreamProphecy(
            function ($position, $length = null) use (&$sizeBytes) {
                if (! $length) {
                    $length = $sizeBytes - $position;
                }

                return str_repeat('0', $length);
            },
            $sizeBytes,
            $position,
            function ($bufferLength) use (&$peakBufferLength): void {
                if ($bufferLength > $peakBufferLength) {
                    $peakBufferLength = $bufferLength;
                }
            }
        );
        $stream->isSeekable()->willReturn($seekable);
        $stream->isReadable()->willReturn($readable);

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream->reveal());

        if ($rangeBlocks) {
            $response = $response->withHeader('Content-Range', 'bytes ' . $first . '-' . $last . '/*');
        }

        ob_start(
            function () use (&$closureTrackMemoryUsage) {
                $closureTrackMemoryUsage();

                return '';
            },
            $maxBufferLength
        );

        gc_collect_cycles();

        gc_disable();

        $this->emitter->setMaxBufferLength($maxBufferLength);
        $this->emitter->emit($response);

        ob_end_flush();

        gc_enable();

        gc_collect_cycles();

        $localMemoryUsage = memory_get_usage();

        self::assertLessThanOrEqual($maxBufferLength, $peakBufferLength);
        self::assertLessThanOrEqual($maxAllowedMemoryUsage, $peakMemoryUsage - $localMemoryUsage);
    }

    public function testEmitEmptyResponse(): void
    {
        $response = (new EmptyResponse())
            ->withStatus(204);

        ob_start();

        $this->emitter->emit($response);

        self::assertEmpty($response->getHeaderLine('content-type'));
        self::assertEmpty(ob_get_clean());
    }

    public function testEmitHtmlResponse(): void
    {
        $contents = '<!DOCTYPE html>'
                  . '<html>'
                  . '    <body>'
                  . '        <h1>Hello world</h1>'
                  . '    </body>'
                  . '</html>';

        $response = (new HtmlResponse($contents))
            ->withStatus(200);

        ob_start();
        $this->emitter->emit($response);
        self::assertEquals('text/html; charset=utf-8', $response->getHeaderLine('content-type'));
        self::assertEquals($contents, ob_get_clean());
    }

    public function emitJsonResponseProvider()
    {
        return [
            [0.1],
            ['test'],
            [true],
            [1],
            [['key1' => 'value1']],
            [null],
            [[[0.1, 0.2], ['test', 'test2'], [true, false], ['key1' => 'value1'], [null]]],
        ];
    }

    /**
     * @param null|array|string $contents Contents stored in stream
     *
     * @dataProvider emitJsonResponseProvider
     */
    public function testEmitJsonResponse($contents): void
    {
        $response = (new JsonResponse($contents))
            ->withStatus(200);

        ob_start();

        $this->emitter->emit($response);

        self::assertEquals('application/json', $response->getHeaderLine('content-type'));
        self::assertEquals(json_encode($contents), ob_get_clean());
    }

    public function testEmitTextResponse(): void
    {
        $contents = 'Hello world';

        $response = (new TextResponse($contents))
            ->withStatus(200);

        ob_start();
        $this->emitter->emit($response);
        self::assertEquals('text/plain; charset=utf-8', $response->getHeaderLine('content-type'));
        self::assertEquals($contents, ob_get_clean());
    }

    public function contentRangeProvider()
    {
        return [
            ['bytes 0-2/*', 'Hello world', 'Hel'],
            ['bytes 3-6/*', 'Hello world', 'lo w'],
            ['items 0-0/1', 'Hello world', 'Hello world'],
        ];
    }

    /**
     * @dataProvider contentRangeProvider
     *
     * @param mixed $header
     * @param mixed $body
     * @param mixed $expected
     */
    public function testContentRange($header, $body, $expected): void
    {
        $response = (new Response())
            ->withHeader('Content-Range', $header);

        $response->getBody()->write($body);

        ob_start();
        $this->emitter->emit($response);
        self::assertEquals($expected, ob_get_clean());
    }

    public function testContentRangeUnseekableBody(): void
    {
        $body = new CallbackStream(function () {
            return 'Hello world';
        });
        $response = (new Response())
            ->withBody($body)
            ->withHeader('Content-Range', 'bytes 3-6/*');

        ob_start();
        $this->emitter->emit($response);
        self::assertEquals('lo w', ob_get_clean());
    }

    /**
     * Create a new stream prophecy and setup common promises.
     *
     * @param callable|string $contents              stream contents
     * @param int             $size                  size of stream contents
     * @param int             $startPosition         start position of internal stream data pointer
     * @param null|callable   $trackPeakBufferLength Called on "read" calls.
     *                                               Receives data length (i.e. data length <= buffer length).
     *
     * @return ObjectProphecy returns new stream prophecy
     */
    private function setUpStreamProphecy($contents, int $size, int $startPosition, ?callable $trackPeakBufferLength = null)
    {
        $position = $startPosition;

        $stream = $this->prophesize(StreamInterface::class);

        $stream->__toString()->will(function () use ($contents, $size, &$position) {
            if (is_callable($contents)) {
                $data = $contents(0);
            } else {
                $data = $contents;
            }

            $position = $size;

            return $data;
        });

        $stream->getSize()->willReturn($size);

        $stream->tell()->will(function () use (&$position) {
            return $position;
        });

        $stream->eof()->will(function () use ($size, &$position) {
            return $position >= $size;
        });

        $stream->seek(Argument::type('integer'), Argument::any())->will(function ($args) use ($size, &$position) {
            if ($args[0] < $size) {
                $position = $args[0];

                return true;
            }

            return false;
        });

        $stream->rewind()->will(function () use (&$position) {
            $position = 0;

            return true;
        });

        $stream->read(Argument::type('integer'))
            ->will(function ($args) use ($contents, &$position, &$trackPeakBufferLength) {
                if (is_callable($contents)) {
                    $data = $contents($position, $args[0]);
                } else {
                    $data = mb_substr($contents, $position, $args[0]);
                }

                if ($trackPeakBufferLength) {
                    $trackPeakBufferLength($args[0]);
                }

                $position += mb_strlen($data);

                return $data;
            });

        $stream->getContents()->will(function () use ($contents, &$position) {
            if (is_callable($contents)) {
                $remainingContents = $contents($position);
            } else {
                $remainingContents = mb_substr($contents, $position);
            }

            $position += mb_strlen($remainingContents);

            return $remainingContents;
        });

        return $stream;
    }
}
