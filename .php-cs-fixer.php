<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS' => true,
        '@PHP8x2Migration' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,

        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
        ],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'single_line_empty_body' => true,
        'no_superfluous_phpdoc_tags' => true,
        'phpdoc_summary' => false,
        'phpdoc_align' => false,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'no_singleline_whitespace_before_semicolons' => false,
        'no_multiline_whitespace_around_double_arrow' => false,
        'trailing_comma_in_multiline' => [
            'elements' => ['arguments', 'arrays'],
        ],
    ])
    ->setFinder($finder);
