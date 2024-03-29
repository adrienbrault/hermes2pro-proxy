<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/demo',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])

    // add a single rule
    ->withRules([
        NoUnusedImportsFixer::class,
    ])

    ->withPreparedSets(
        psr12: true,
        common: true,
        cleanCode: true,
    )
;
