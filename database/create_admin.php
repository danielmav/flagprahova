<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
if (is_file($root . '/.env')) { Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
$settings = require $root . '/config/settings.php';
[$_, $email, $nume, $parola] = $argv + [null, null, null, null];
if (!$email || !$parola) { fwrite(STDERR, "Folosire: create_admin.php email nume parola\n"); exit(1); }
$pdo = (new App\Database($settings['db']))->pdo();
$pdo->prepare('INSERT INTO utilizatori (email, nume, parola_hash) VALUES (:e, :n, :h)
    ON DUPLICATE KEY UPDATE nume = VALUES(nume), parola_hash = VALUES(parola_hash)')
    ->execute(['e' => $email, 'n' => (string) $nume, 'h' => password_hash($parola, PASSWORD_DEFAULT)]);
echo "OK: $email\n";
