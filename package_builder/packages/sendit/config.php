<?php
// Сборка через глобальный shevartv/modx-builder (флаг --no-dev стрипает dev-vendor и восстанавливает после билда):
//   cd <project> && modxapp build sendit --no-dev
// Локальная копия package_builder/ (src, cli.php) — устаревший форк без --no-dev, в репозитории не отслеживается; не использовать.
return [
    'name' => 'SendIt',
    'name_lower' => 'sendit',
    'name_short' => 'si',
    'version' => '3.1.5',
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
