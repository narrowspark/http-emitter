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

use Laminas\Diactoros\Response;
use Narrowspark\HttpEmitter\SapiEmitter;
use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;
use Psr\Http\Message\StreamInterface;
use function Safe\ob_end_clean;

/**
 * @internal
 *
 * @medium
 * @covers \Narrowspark\HttpEmitter\SapiEmitter
 */
final class SapiEmitterTest extends AbstractEmitterTest
{
    protected function setUp(): void
    {
        HeaderStack::reset();

        HeaderStack::$headersSent = false;
        HeaderStack::$headersFile = null;
        HeaderStack::$headersLine = null;

        $this->emitter = new SapiEmitter();
    }

    public function testDoesNotInjectContentLengthHeaderIfStreamSizeIsUnknown(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->method('__toString')
            ->willReturn('Content!');
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
}
