<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
if (is_file($root . '/.env')) { Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
$settings = require $root . '/config/settings.php';
$pdo = (new App\Database($settings['db']))->pdo();

$sql = file_get_contents(__DIR__ . '/schema.sql');
$instructiuni = array_filter(array_map('trim', explode(';', $sql)));
$n = 0;
foreach ($instructiuni as $i) {
    $pdo->exec($i);
    $n++;
}
echo "migrate: $n instrucțiuni rulate pe {$settings['db']['name']}\n";
