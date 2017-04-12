<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter\Providers;

use Interop\Container\ContainerInterface;
use Interop\Container\ServiceProvider;
use Narrowspark\HttpEmitter\EmitterInterface;
use Narrowspark\HttpEmitter\SapiEmitter;

class SapiEmitterServiceProvider implements ServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function getServices()
    {
        return [
            EmitterInterface::class => function () {
                return new SapiEmitter();
            },
            SapiEmitter::class => function (ContainerInterface $container) {
                return $container->get(EmitterInterface::class);
            },
        ];
    }
}
