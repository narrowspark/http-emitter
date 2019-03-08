<?php
use Narrowspark\CS\Config\Config;

$config = new Config(null, [
    'native_function_invocation' => [
        'exclude' => [
            'headers_sent',
            'header',
        ],
    ],
    'static_lambda' => false,
]);
$config->getFinder()
    ->files()
    ->in(__DIR__)
    ->exclude('build')
    ->exclude('vendor')
    ->notPath('src/Viserio/Component/Validation/Sanitizer.php')
    ->notPath('src/Viserio/Component/WebProfiler/Resources/views/webprofiler.html.php')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$cacheDir = getenv('TRAVIS') ? getenv('HOME') . '/.php-cs-fixer' : __DIR__;

$config->setCacheFile($cacheDir . '/.php_cs.cache');

return $config;
