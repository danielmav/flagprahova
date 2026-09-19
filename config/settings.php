<?php
declare(strict_types=1);

return [
    'app' => [
        'env'       => $_ENV['APP_ENV'] ?? 'prod',
        'debug'     => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
        'url'       => rtrim($_ENV['APP_URL'] ?? 'http://flagprahova.test', '/'),
        'base_path' => rtrim($_ENV['BASE_PATH'] ?? '', '/'),
        'name'      => 'FLAG Prahova',
        'indexable' => filter_var($_ENV['APP_INDEXABLE'] ?? true, FILTER_VALIDATE_BOOL),
    ],
    'admin' => [
        'path' => '/' . trim($_ENV['ADMIN_PATH'] ?? 'admin', '/'),
    ],
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'name' => $_ENV['DB_NAME'] ?? 'flagprahova',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'pass' => $_ENV['DB_PASS'] ?? '',
    ],
    // Baza WordPress veche, DOAR pentru scripturile de migrare (read-only).
    'db_wp' => [
        'host' => $_ENV['DB_WP_HOST'] ?? ($_ENV['DB_HOST'] ?? '127.0.0.1'),
        'port' => $_ENV['DB_WP_PORT'] ?? ($_ENV['DB_PORT'] ?? '3306'),
        'name' => $_ENV['DB_WP_NAME'] ?? '',
        'user' => $_ENV['DB_WP_USER'] ?? ($_ENV['DB_USER'] ?? 'root'),
        'pass' => $_ENV['DB_WP_PASS'] ?? ($_ENV['DB_PASS'] ?? ''),
        'prefix' => $_ENV['DB_WP_PREFIX'] ?? 'wpt9_',
    ],
    'mail' => [
        'from'        => $_ENV['MAIL_FROM'] ?? 'noreply@flagprahova.ro',
        'from_name'   => $_ENV['MAIL_FROM_NAME'] ?? 'FLAG Prahova',
        'admin'       => $_ENV['MAIL_ADMIN'] ?? 'flagprahova@gmail.com',
        'smtp_host'   => $_ENV['SMTP_HOST'] ?? '',
        'smtp_port'   => (int) ($_ENV['SMTP_PORT'] ?? 587),
        'smtp_user'   => $_ENV['SMTP_USER'] ?? '',
        'smtp_pass'   => $_ENV['SMTP_PASS'] ?? '',
        'smtp_secure' => $_ENV['SMTP_SECURE'] ?? 'tls',
    ],
    'upload' => [
        'dir'       => dirname(__DIR__) . '/fisiere',
        'url'       => '/fisiere',
        'max_bytes' => 50 * 1024 * 1024,
    ],
    'twig' => [
        'templates' => dirname(__DIR__) . '/templates',
        'cache'     => filter_var($_ENV['TWIG_CACHE'] ?? false, FILTER_VALIDATE_BOOL)
            ? dirname(__DIR__) . '/storage/cache/twig' : false,
    ],
];
