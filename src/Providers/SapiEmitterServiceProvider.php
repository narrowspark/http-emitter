<?php
declare(strict_types=1);
namespace Narrowspark\HttpEmitter\Providers;

use Interop\Container\ContainerInterface;
use Interop\Container\ServiceProviderInterface;
use Narrowspark\HttpEmitter\EmitterInterface;
use Narrowspark\HttpEmitter\SapiEmitter;

class SapiEmitterServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getFactories()
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

    /**
     * {@inheritdoc}
     */
    public function getExtensions(): array
    {
        return [];
    }
}
