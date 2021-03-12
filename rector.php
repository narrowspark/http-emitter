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

use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use Rector\Core\Configuration\Option;
use Rector\Core\ValueObject\PhpVersion;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    // paths to refactor; solid alternative to CLI arguments
    $parameters->set(Option::PATHS, [__DIR__ . '/src', __DIR__ . '/tests']);

    // is your PHP version different from the one your refactor to? [default: your PHP version], uses PHP_VERSION_ID format
    $parameters->set(Option::PHP_VERSION_FEATURES, PhpVersion::PHP_80);

    // auto import fully qualified class names? [default: false]
    $parameters->set(Option::AUTO_IMPORT_NAMES, true);

    // skip root namespace classes, like \DateTime or \Exception [default: true]
    $parameters->set(Option::IMPORT_SHORT_CLASSES, false);

    // skip classes used in PHP DocBlocks, like in /** @var \Some\Class */ [default: true]
    $parameters->set(Option::IMPORT_DOC_BLOCKS, false);

    // Run Rector only on changed files
    $parameters->set(Option::ENABLE_CACHE, true);

    $phpstanPath = getcwd() . '/phpstan.neon';
    $phpstanNeonContent = FileSystem::read($phpstanPath);
    $bleedingEdgePattern = '#\n\s+-(.*?)bleedingEdge\.neon[\'|"]?#';

    // bleeding edge clean out, see https://github.com/rectorphp/rector/issues/2431
    if (Strings::match($phpstanNeonContent, $bleedingEdgePattern)) {
        $temporaryPhpstanNeon = getcwd() . '/rector-temp-phpstan.neon';
        $clearedPhpstanNeonContent = Strings::replace($phpstanNeonContent, $bleedingEdgePattern);

        FileSystem::write($temporaryPhpstanNeon, $clearedPhpstanNeonContent);

        $phpstanPath = $temporaryPhpstanNeon;
    }

    // Path to phpstan with extensions, that PHPStan in Rector uses to determine types
    $parameters->set(Option::PHPSTAN_FOR_RECTOR_PATH, $phpstanPath);

    $parameters->set(Option::SETS, [
        'action-injection-to-constructor-injection', 'array-str-functions-to-static-call', 'early-return', 'doctrine-code-quality', 'dead-code', 'code-quality', 'type-declaration', 'order', 'psr4', 'type-declaration', 'type-declaration-strict', 'php71', 'php72', 'php73', 'php74', 'php80', 'phpunit91', 'phpunit-code-quality', 'phpunit-exception', 'phpunit-yield-data-provider',
    ]);

    $parameters->set(Option::SKIP, [
    ]);
};
