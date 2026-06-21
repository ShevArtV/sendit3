<?php
// /usr/local/php/php-8.2/bin/php -d display_errors -d error_reporting=E_ALL package_builder/cli.php build sendit
return [
    'name' => 'SendIt',
    'name_lower' => 'sendit',
    'name_short' => 'si',
    'version' => '3.1.2',
    'release' => 'pl',
    'php_version' => '8.1',

    'resolvers_path' => 'package_builder/packages/sendit/resolvers/',

    'paths' => [
        'core' => 'core/components/sendit/',
        'assets' => 'assets/components/sendit/',
    ],

    'elements' => [
        'category' => 'SendIt',
        'snippets' => 'elements/snippets.php',
        'chunks' => 'elements/chunks.php',
        'plugins' => 'elements/plugins.php',
        'settings' => 'elements/settings.php',
    ],

    'static' => [
        'chunks' => false,
        'snippets' => true,
        'plugins' => false,
    ],

    'build' => [
        'download' => false,
        'install' => false,
        'update' => [
            'chunks' => true,
            'snippets' => true,
            'plugins' => true,
            'settings' => false,
        ],
    ],
];
