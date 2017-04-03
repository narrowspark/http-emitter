<?php
use Narrowspark\CS\Config\Config;

$config = new Config();
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
