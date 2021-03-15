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

/*
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

use Laminas\Diactoros\CallbackStream;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use Narrowspark\HttpEmitter\AbstractSapiEmitter;
use Narrowspark\HttpEmitter\SapiStreamEmitter;
use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;
use Narrowspark\HttpEmitter\Tests\Helper\StreamMock;
use Psr\Http\Message\StreamInterface;
use function Safe\json_encode;
use function Safe\ob_end_clean;
use function Safe\ob_end_flush;
use function Safe\substr;
use function strlen;

/**
 * @internal
 *
 * @medium
 * @covers \Narrowspark\HttpEmitter\SapiStreamEmitter
 */
final class SapiStreamEmitterTest extends AbstractEmitterTest
{
    /** @var \Narrowspark\HttpEmitter\SapiStreamEmitter */
    protected AbstractSapiEmitter $emitter;

    protected function setUp(): void
    {
        HeaderStack::reset();

        HeaderStack::$headersSent = false;
        HeaderStack::$headersFile = null;
        HeaderStack::$headersLine = null;

        $this->emitter = new SapiStreamEmitter();
    }

    public function testEmitCallbackStreamResponse(): void
    {
        $stream = new CallbackStream(static fn (): string => 'it works');

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);

        ob_start();

        $this->emitter->emit($response);

        self::assertEquals('it works', ob_get_clean());
    }

    public function testDoesNotInjectContentLengthHeaderIfStreamSizeIsUnknown(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->method('__toString')
            ->willReturn('Content!');
        $stream
            ->method('isSeekable')
            ->willReturn(false);
        $stream
            ->method('isReadable')
            ->willReturn(false);
        $stream
            ->method('eof')
            ->willReturn(true);
        $stream
            ->method('rewind')
            ->willReturn(true);
        $stream
            ->method('getSize')
            ->willReturn(null);

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);

        ob_start();
        $this->emitter->emit($response);
        ob_end_clean();

        foreach (HeaderStack::stack() as $header) {
            self::assertStringNotContainsStringIgnoringCase('Content-Length:', (string) $header['header']);
        }
    }

    /**
     * @param bool   $seekable        Indicates if stream is seekable
     * @param bool   $readable        Indicates if stream is readable
     * @param string $contents        Contents stored in stream
     * @param int    $maxBufferLength maximum buffer length used in the emitter call
     *
     * @dataProvider provideEmitStreamResponseCases
     */
    public function testEmitStreamResponse(bool $seekable, bool $readable, string $contents, int $maxBufferLength): void
    {
        $size = strlen($contents);
        $startPosition = 0;
        $peakBufferLength = 0;

        $streamMock = new StreamMock(
            $contents,
            $size,
            $startPosition,
            static function (int $bufferLength) use (&$peakBufferLength): void {
                if ($bufferLength > $peakBufferLength) {
                    $peakBufferLength = $bufferLength;
                }
            }
        );

        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->method('isSeekable')
            ->willReturn($seekable);
        $stream
            ->method('isReadable')
            ->willReturn($readable);

        if ($seekable) {
            $stream
                ->expects(self::atLeastOnce())
                ->method('rewind')
                ->willReturnCallback(static fn (): bool => $streamMock->handleRewind());
            $stream
                ->method('seek')
                ->willReturnCallback(static fn ($offset, $whence): bool => $streamMock->handleSeek($offset, $whence));
        }

        if (! $seekable) {
            $stream->expects(self::never())->method('rewind');
            $stream->expects(self::never())->method('seek');
        }

        if ($readable) {
            $stream
                ->expects(self::never())
                ->method('__toString');
            $stream
                ->method('eof')
                ->willReturnCallback(static fn (): bool => $streamMock->handleEof());
            $stream
                ->method('read')
                ->willReturnCallback(static fn ($length): string => $streamMock->handleRead($length));
        }

        if (! $readable) {
            $stream
                ->expects(self::never())
                ->method('read');
            $stream
                ->expects(self::never())
                ->method('eof');

            $seekable
                ? $stream
                    ->method('getContents')
                    ->willReturnCallback(static fn (): string => $streamMock->handleGetContents())
                : $stream
                    ->expects(self::never())
                    ->method('getContents');

            $stream
                ->method('__toString')
                ->willReturnCallback(static fn (): string => $streamMock->handleToString());
        }

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);

        ob_start();

        $this->emitter->setMaxBufferLength($maxBufferLength);
        $this->emitter->emit($response);
        $emittedContents = ob_get_clean();

        self::assertEquals($contents, $emittedContents);
        self::assertLessThanOrEqual($maxBufferLength, $peakBufferLength);
    }

    /**
     * @param bool   $seekable        Indicates if stream is seekable
     * @param bool   $readable        Indicates if stream is readable
     * @param array  $range           Emitted range of data [$unit, $first, $last, $length]
     * @param string $contents        Contents stored in stream
     * @param int    $maxBufferLength maximum buffer length used in the emitter call
     *
     * @psalm-param array{0: string, 1: int, 2: int, 3: string} $range
     *
     * @dataProvider provideEmitRangeStreamResponseCases
     */
    public function testEmitRangeStreamResponse(
        bool $seekable,
        bool $readable,
        array $range,
        string $contents,
        int $maxBufferLength
    ): void {
        [/* $unit */, $first, $last, /* $length */] = $range;
        $size = strlen($contents);

        $startPosition = $readable && ! $seekable
            ? $first
            : 0;

        $peakBufferLength = 0;

        $trackPeakBufferLength = static function (int $bufferLength) use (&$peakBufferLength): void {
            if ($bufferLength > $peakBufferLength) {
                $peakBufferLength = $bufferLength;
            }
        };

        $streamMock = new StreamMock(
            $contents,
            $size,
            $startPosition,
            $trackPeakBufferLength
        );

        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->method('isSeekable')
            ->willReturn($seekable);
        $stream
            ->method('isReadable')
            ->willReturn($readable);
        $stream
            ->method('getSize')
            ->willReturn($size);
        $stream
            ->method('tell')
            ->willReturnCallback(static fn (): int => $streamMock->handleTell());

        $stream
            ->expects(self::never())
            ->method('rewind');

        if ($seekable) {
            $stream
                ->expects(self::atLeastOnce())
                ->method('seek')
                ->willReturnCallback(static fn (
                    $offset,
                    $whence
                ): bool => $streamMock->handleSeek($offset, $whence));
        } else {
            $stream
                ->expects(self::never())
                ->method('seek');
        }

        $stream
            ->expects(self::never())
            ->method('__toString');

        if ($readable) {
            $stream
                ->expects(self::atLeastOnce())
                ->method('read')
                ->with(self::isType('int'))
                ->willReturnCallback(static fn (
                    $length
                ): string => $streamMock->handleRead($length));
            $stream
                ->expects(self::atLeastOnce())
                ->method('eof')
                ->willReturnCallback(static fn (): bool => $streamMock->handleEof());
            $stream->expects(self::never())->method('getContents');
        } else {
            $stream->expects(self::never())->method('read');
            $stream->expects(self::never())->method('eof');
            $stream
                ->expects(self::atLeastOnce())
                ->method('getContents')
                ->willReturnCallback(static fn (): string => $streamMock->handleGetContents());
        }

        $response = (new Response())
            ->withStatus(200)
            ->withHeader('Content-Range', 'bytes ' . $first . '-' . $last . '/*')
            ->withBody($stream);

        ob_start();

        $this->emitter->setMaxBufferLength($maxBufferLength);
        $this->emitter->emit($response);
        $emittedContents = ob_get_clean();

        self::assertEquals(substr($contents, $first, $last - $first + 1), $emittedContents);
        self::assertLessThanOrEqual($maxBufferLength, $peakBufferLength);
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
     * @psalm-param null|array{0: int, 1: int} $rangeBlocks
     *
     * @dataProvider provideEmitMemoryUsageCases
     * @runInSeparateProcess
     */
    public function testEmitMemoryUsage(
        bool $seekable,
        bool $readable,
        int $sizeBlocks,
        int $maxAllowedBlocks,
        ?array $rangeBlocks,
        int $maxBufferLength
    ): void {
        HeaderStack::$headersSent = false;

        $sizeBytes = $maxBufferLength * $sizeBlocks;
        $maxAllowedMemoryUsage = $maxBufferLength * $maxAllowedBlocks;
        $peakBufferLength = 0;
        $peakMemoryUsage = 0;

        $position = 0;
        $first = 0;
        $last = 0;

        if ($rangeBlocks !== null) {
            $first = $maxBufferLength * $rangeBlocks[0];
            $last = ($maxBufferLength * $rangeBlocks[1]) + $maxBufferLength - 1;

            if ($readable && ! $seekable) {
                $position = $first;
            }
        }

        $closureTrackMemoryUsage = static function () use (&$peakMemoryUsage): void {
            $peakMemoryUsage = max($peakMemoryUsage, memory_get_usage());
        };

        $contentsCallback = static function (int $position, ?int $length = null) use (&$sizeBytes): string {
            if ($length === null) {
                $length = $sizeBytes - $position;
            }

            return str_repeat('0', $length);
        };

        $trackPeakBufferLength = static function (int $bufferLength) use (&$peakBufferLength): void {
            if ($bufferLength > $peakBufferLength) {
                $peakBufferLength = $bufferLength;
            }
        };

        $streamMock = new StreamMock(
            $contentsCallback,
            $sizeBytes,
            $position,
            $trackPeakBufferLength
        );

        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->method('isSeekable')
            ->willReturn($seekable);
        $stream
            ->method('isReadable')
            ->willReturn($readable);
        $stream
            ->method('eof')
            ->willReturnCallback(static fn (): bool => $streamMock->handleEof());

        if ($seekable) {
            $stream
                ->method('seek')
                ->willReturnCallback(static fn (
                    $offset,
                    $whence
                ): bool => $streamMock->handleSeek($offset, $whence));
        }

        if ($readable) {
            $stream
                ->method('read')
                ->willReturnCallback(static fn (
                    $length
                ): string => $streamMock->handleRead($length));
        }

        if (! $readable) {
            $stream
                ->method('getContents')
                ->willReturnCallback(static fn (): string => $streamMock->handleGetContents());
        }

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);

        if ($rangeBlocks !== null) {
            $response = $response->withHeader('Content-Range', 'bytes ' . $first . '-' . $last . '/*');
        }

        ob_start(
            static function () use (&$closureTrackMemoryUsage): string {
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
        $contents = <<<'HTML'
            <!DOCTYPE html>'
            <html>
                <body>
                    <h1>Hello world</h1>
                </body>
            </html>
HTML;

        $response = (new HtmlResponse($contents))
            ->withStatus(200);

        ob_start();

        $this->emitter->emit($response);

        self::assertEquals('text/html; charset=utf-8', $response->getHeaderLine('content-type'));
        self::assertEquals($contents, ob_get_clean());
    }

    /**
     * @param null|array|string $contents Contents stored in stream
     *
     * @psalm-param null|array<array-key, string|bool>|string $contents
     *
     * @dataProvider provideEmitJsonResponseCases
     */
    public function testEmitJsonResponse(mixed $contents): void
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

    /**
     * @dataProvider provideContentRangeCases
     */
    public function testContentRange(string $header, string $body, string $expected): void
    {
        $response = (new Response())
            ->withHeader('Content-Range', $header);

        $responseBody = $response->getBody();
        $responseBody->write($body);

        ob_start();

        $this->emitter->emit($response);

        self::assertEquals($expected, ob_get_clean());
    }

    public function testContentRangeUnseekableBody(): void
    {
        $body = new CallbackStream(static fn (): string => 'Hello world');
        $response = (new Response())
            ->withBody($body)
            ->withHeader('Content-Range', 'bytes 3-6/*');

        ob_start();

        $this->emitter->emit($response);

        self::assertEquals('lo w', ob_get_clean());
    }

    /**
     * @psalm-return iterable<array{0: bool, 1: bool, 2: string, 3: int}>
     */
    public static function provideEmitStreamResponseCases(): iterable
    {
        yield [true,   true,    '01234567890987654321',   10];

        yield [true,   true,    '01234567890987654321',   20];

        yield [true,   true,    '01234567890987654321',  100];

        yield [true,   true, '01234567890987654321012',   10];

        yield [true,   true, '01234567890987654321012',   20];

        yield [true,   true, '01234567890987654321012',  100];

        yield [true,  false,    '01234567890987654321',   10];

        yield [true,  false,    '01234567890987654321',   20];

        yield [true,  false,    '01234567890987654321',  100];

        yield [true,  false, '01234567890987654321012',   10];

        yield [true,  false, '01234567890987654321012',   20];

        yield [true,  false, '01234567890987654321012',  100];

        yield [false,  true,    '01234567890987654321',   10];

        yield [false,  true,    '01234567890987654321',   20];

        yield [false,  true,    '01234567890987654321',  100];

        yield [false,  true, '01234567890987654321012',   10];

        yield [false,  true, '01234567890987654321012',   20];

        yield [false,  true, '01234567890987654321012',  100];

        yield [false, false,    '01234567890987654321',   10];

        yield [false, false,    '01234567890987654321',   20];

        yield [false, false,    '01234567890987654321',  100];

        yield [false, false, '01234567890987654321012',   10];

        yield [false, false, '01234567890987654321012',   20];

        yield [false, false, '01234567890987654321012',  100];
    }

    /**
     * @psalm-return iterable<array{
     *     0: bool,
     *     1: bool,
     *     2: array{0: string, 1: int, 2: int, 3: string},
     *     3: string,
     *     4: int
     * }>
     */
    public static function provideEmitRangeStreamResponseCases(): iterable
    {
        yield [true,   true, ['bytes', 10,  20, '*'],    '01234567890987654321',   5];

        yield [true,   true, ['bytes', 10,  20, '*'],    '01234567890987654321',  10];

        yield [true,   true, ['bytes', 10,  20, '*'],    '01234567890987654321', 100];

        yield [true,   true, ['bytes', 10,  20, '*'], '01234567890987654321012',   5];

        yield [true,   true, ['bytes', 10,  20, '*'], '01234567890987654321012',  10];

        yield [true,   true, ['bytes', 10,  20, '*'], '01234567890987654321012', 100];

        yield [true,   true, ['bytes', 10, 100, '*'],    '01234567890987654321',   5];

        yield [true,   true, ['bytes', 10, 100, '*'],    '01234567890987654321',  10];

        yield [true,   true, ['bytes', 10, 100, '*'],    '01234567890987654321', 100];

        yield [true,   true, ['bytes', 10, 100, '*'], '01234567890987654321012',   5];

        yield [true,   true, ['bytes', 10, 100, '*'], '01234567890987654321012',  10];

        yield [true,   true, ['bytes', 10, 100, '*'], '01234567890987654321012', 100];

        yield [true,  false, ['bytes', 10,  20, '*'],    '01234567890987654321',   5];

        yield [true,  false, ['bytes', 10,  20, '*'],    '01234567890987654321',  10];

        yield [true,  false, ['bytes', 10,  20, '*'],    '01234567890987654321', 100];

        yield [true,  false, ['bytes', 10,  20, '*'], '01234567890987654321012',   5];

        yield [true,  false, ['bytes', 10,  20, '*'], '01234567890987654321012',  10];

        yield [true,  false, ['bytes', 10,  20, '*'], '01234567890987654321012', 100];

        yield [true,  false, ['bytes', 10, 100, '*'],    '01234567890987654321',   5];

        yield [true,  false, ['bytes', 10, 100, '*'],    '01234567890987654321',  10];

        yield [true,  false, ['bytes', 10, 100, '*'],    '01234567890987654321', 100];

        yield [true,  false, ['bytes', 10, 100, '*'], '01234567890987654321012',   5];

        yield [true,  false, ['bytes', 10, 100, '*'], '01234567890987654321012',  10];

        yield [true,  false, ['bytes', 10, 100, '*'], '01234567890987654321012', 100];

        yield [false,  true, ['bytes', 10,  20, '*'],    '01234567890987654321',   5];

        yield [false,  true, ['bytes', 10,  20, '*'],    '01234567890987654321',  10];

        yield [false,  true, ['bytes', 10,  20, '*'],    '01234567890987654321', 100];

        yield [false,  true, ['bytes', 10,  20, '*'], '01234567890987654321012',   5];

        yield [false,  true, ['bytes', 10,  20, '*'], '01234567890987654321012',  10];

        yield [false,  true, ['bytes', 10,  20, '*'], '01234567890987654321012', 100];

        yield [false,  true, ['bytes', 10, 100, '*'],    '01234567890987654321',   5];

        yield [false,  true, ['bytes', 10, 100, '*'],    '01234567890987654321',  10];

        yield [false,  true, ['bytes', 10, 100, '*'],    '01234567890987654321', 100];

        yield [false,  true, ['bytes', 10, 100, '*'], '01234567890987654321012',   5];

        yield [false,  true, ['bytes', 10, 100, '*'], '01234567890987654321012',  10];

        yield [false,  true, ['bytes', 10, 100, '*'], '01234567890987654321012', 100];

        yield [false, false, ['bytes', 10,  20, '*'],    '01234567890987654321',   5];

        yield [false, false, ['bytes', 10,  20, '*'],    '01234567890987654321',  10];

        yield [false, false, ['bytes', 10,  20, '*'],    '01234567890987654321', 100];

        yield [false, false, ['bytes', 10,  20, '*'], '01234567890987654321012',   5];

        yield [false, false, ['bytes', 10,  20, '*'], '01234567890987654321012',  10];

        yield [false, false, ['bytes', 10,  20, '*'], '01234567890987654321012', 100];

        yield [false, false, ['bytes', 10, 100, '*'],    '01234567890987654321',   5];

        yield [false, false, ['bytes', 10, 100, '*'],    '01234567890987654321',  10];

        yield [false, false, ['bytes', 10, 100, '*'],    '01234567890987654321', 100];

        yield [false, false, ['bytes', 10, 100, '*'], '01234567890987654321012',   5];

        yield [false, false, ['bytes', 10, 100, '*'], '01234567890987654321012',  10];

        yield [false, false, ['bytes', 10, 100, '*'], '01234567890987654321012', 100];
    }

    /**
     * @psalm-return iterable<array{
     *     0: bool,
     *     1: bool,
     *     2: int,
     *     3: int,
     *     4: null|array{0: int, 1: int},
     *     5: int
     * }>
     */
    public static function provideEmitMemoryUsageCases(): iterable
    {
        yield [true,   true,  1000,   20,       null,  512];

        yield [true,   true,  1000,   20,       null, 4096];

        yield [true,   true,  1000,   20,       null, 8192];

        yield [true,  false,   100,  320,       null,  512];

        yield [true,  false,   100,  320,       null, 4096];

        yield [true,  false,   100,  320,       null, 8192];

        yield [false,  true,  1000,   20,       null,  512];

        yield [false,  true,  1000,   20,       null, 4096];

        yield [false,  true,  1000,   20,       null, 8192];

        yield [false, false,   100,  320,       null,  512];

        yield [false, false,   100,  320,       null, 4096];

        yield [false, false,   100,  320,       null, 8192];

        yield [true,   true,  1000,   20,   [25, 75],  512];

        yield [true,   true,  1000,   20,   [25, 75], 4096];

        yield [true,   true,  1000,   20,   [25, 75], 8192];

        yield [false,  true,  1000,   20,   [25, 75],  512];

        yield [false,  true,  1000,   20,   [25, 75], 4096];

        yield [false,  true,  1000,   20,   [25, 75], 8192];

        yield [true,   true,  1000,   20, [250, 750],  512];

        yield [true,   true,  1000,   20, [250, 750], 4096];

        yield [true,   true,  1000,   20, [250, 750], 8192];

        yield [false,  true,  1000,   20, [250, 750],  512];

        yield [false,  true,  1000,   20, [250, 750], 4096];

        yield [false,  true,  1000,   20, [250, 750], 8192];
    }

    /**
     * @psalm-return iterable<array<array-key, int|float|bool|string|array|null>>
     */
    public static function provideEmitJsonResponseCases(): iterable
    {
        yield [0.1];

        yield ['test'];

        yield [true];

        yield [1];

        yield [['key1' => 'value1']];

        yield [null];

        yield [[[0.1, 0.2], ['test', 'test2'], [true, false], ['key1' => 'value1'], [null]]];
    }

    /**
     * @psalm-return iterable<array<array-key, string>>
     */
    public static function provideContentRangeCases(): iterable
    {
        yield ['bytes 0-2/*', 'Hello world', 'Hel'];

        yield ['bytes 3-6/*', 'Hello world', 'lo w'];

        yield ['items 0-0/1', 'Hello world', 'Hello world'];
    }
}
