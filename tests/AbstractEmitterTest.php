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
use Narrowspark\HttpEmitter\AbstractSapiEmitter;
use Narrowspark\HttpEmitter\Contract\RuntimeException;
use Narrowspark\HttpEmitter\Tests\Helper\HeaderStack;
use PHPUnit\Framework\TestCase;
use function Safe\sprintf;

/**
 * @internal
 */
abstract class AbstractEmitterTest extends TestCase
{
    protected AbstractSapiEmitter $emitter;

    final public function testEmitThrowsSentHeadersException(): void
    {
        HeaderStack::$headersSent = true;
        HeaderStack::$headersFile = 'src/AbstractSapiEmitter.php';
        HeaderStack::$headersLine = 20;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'Unable to emit response: Headers already sent in file %s on line %s. This happens if echo, print, printf, print_r, var_dump, var_export or similar statement that writes to the output buffer are used.',
            HeaderStack::$headersFile,
            (string) HeaderStack::$headersLine
        ));

        $this->emitter->emit($this->arrangeStatus200AndTypeTextResponse());
    }

    final public function testEmitsMessageBody(): void
    {
        $response = $this->arrangeStatus200AndTypeTextResponse();

        $responseBody = $response->getBody();
        $responseBody->write('Content!');

        $this->expectOutputString('Content!');

        $this->emitter->emit($response);

        self::assertTrue(HeaderStack::has('HTTP/1.1 200 OK'));
        self::assertTrue(HeaderStack::has('Content-Type: text/plain'));
    }

    final public function testMultipleSetCookieHeadersAreNotReplaced(): void
    {
        $response = new Response();
        $response = $response
            ->withStatus(200)
            ->withAddedHeader('Set-Cookie', 'foo=bar')
            ->withAddedHeader('Set-Cookie', 'bar=baz');

        $this->emitter->emit($response);

        $expectedStack = [
            ['header' => 'Set-Cookie: foo=bar', 'replace' => false, 'status_code' => 200],
            ['header' => 'Set-Cookie: bar=baz', 'replace' => false, 'status_code' => 200],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'status_code' => 200],
        ];

        self::assertSame($expectedStack, HeaderStack::stack());
    }

    final public function testDoesNotLetResponseCodeBeOverriddenByPHP(): void
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

        self::assertSame($expectedStack, HeaderStack::stack());
    }

    final public function testEmitterRespectLocationHeader(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Location', 'http://api.my-service.com/12345678');

        $this->emitter->emit($response);

        $expectedStack = [
            ['header' => 'Location: http://api.my-service.com/12345678', 'replace' => true, 'status_code' => 200],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'status_code' => 200],
        ];

        self::assertSame($expectedStack, HeaderStack::stack());
    }

    private function arrangeStatus200AndTypeTextResponse(): Response
    {
        $response = new Response();
        $response = $response
            ->withStatus(200);

        return $response
            ->withAddedHeader('Content-Type', 'text/plain');
    }
}
