<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter\Tests\Helper;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * This Handler implementation is used to test if the Handler receives the expected
 * Request instance, and to fake an expected Response to be emitted by the Host.
 */
class TestRequestHandler implements RequestHandlerInterface
{
    /**
     * @var ServerRequestInterface
     */
    public $received_request;

    /**
     * @var ResponseInterface
     */
    private $expected_response;

    public function __construct(ResponseInterface $expected_response)
    {
        $this->expected_response = $expected_response;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->received_request = $request;

        return $this->expected_response;
    }
}
