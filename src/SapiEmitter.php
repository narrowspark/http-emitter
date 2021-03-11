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

namespace Narrowspark\HttpEmitter;

use Psr\Http\Message\ResponseInterface;

/**
 * @see \Narrowspark\HttpEmitter\Tests\SapiEmitterTest
 */
final class SapiEmitter extends AbstractSapiEmitter
{
    /**
     * {@inheritdoc}
     */
    public function emit(ResponseInterface $response): void
    {
        $this->assertNoPreviousOutput();

        $this->emitHeaders($response);

        // Set the status _after_ the headers, because of PHP's "helpful" behavior with location headers.
        $this->emitStatusLine($response);

        $this->emitBody($response);

        $this->closeConnection();
    }

    /**
     * Sends the message body of the response.
     */
    private function emitBody(ResponseInterface $response): void
    {
        echo $response->getBody();
    }
}
