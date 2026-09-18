<?php

declare(strict_types=1);

/**
 * Front controller pentru FLAG Prahova.
 * Laragon document root = project root; .htaccess rutează totul aici.
 */

require __DIR__ . '/vendor/autoload.php';

App\Bootstrap::create()->run();
