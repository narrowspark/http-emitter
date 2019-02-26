<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter\Tests\Helper;

use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * This test-double replaces the ServerRequestCreator under test, so we can manually
 * inject a Request instance - we use this to assert that the Request that gets
 * processed by the Handler in the Host is the same Request instance that would be
 * produced by the ServerRequestCreator in a SAPI-environment.
 */
class TestServerRequestCreator implements ServerRequestCreatorInterface
{
    /**
     * @var ServerRequestInterface
     */
    private $request;

    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    public function fromGlobals(): ServerRequestInterface
    {
        return $this->request;
    }

    public function fromArrays(
        array $server,
        array $headers = [],
        array $cookie = [],
        array $get = [],
        array $post = [],
        array $files = [],
        $body = null
    ): ServerRequestInterface {
        throw new RuntimeException("NOT IMPLEMENTED: will never be called");
    }

    public static function getHeadersFromServer(array $server): array
    {
        throw new RuntimeException("NOT IMPLEMENTED: will never be called");
    }
}
