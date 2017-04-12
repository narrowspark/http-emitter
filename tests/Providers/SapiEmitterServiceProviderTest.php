<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter\Tests\Providers;

use Narrowspark\HttpEmitter\EmitterInterface;
use Narrowspark\HttpEmitter\Providers\SapiEmitterServiceProvider;
use Narrowspark\HttpEmitter\SapiEmitter;
use PHPUnit\Framework\TestCase;
use Simplex\Container;

class SapiEmitterServiceProviderTest extends TestCase
{
    public function testGetServices()
    {
        $container = new Container();

        $container->register(new SapiEmitterServiceProvider());

        self::assertInstanceOf(EmitterInterface::class, $container->get(EmitterInterface::class));
        self::assertInstanceOf(EmitterInterface::class, $container->get(SapiEmitter::class));
    }
}
