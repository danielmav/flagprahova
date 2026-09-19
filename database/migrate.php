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

// Coloane adăugate după prima lansare: `CREATE TABLE IF NOT EXISTS` nu le mai
// pune pe bazele existente, deci le adăugăm aici dacă lipsesc (idempotent).
$adauga = [
    ['meniu', 'publicat_la', 'ALTER TABLE meniu ADD COLUMN publicat_la DATE NULL AFTER vizibil'],
];
$st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c');
foreach ($adauga as [$tabel, $coloana, $alter]) {
    $st->execute(['t' => $tabel, 'c' => $coloana]);
    if ((int) $st->fetchColumn() === 0) {
        $pdo->exec($alter);
        echo "migrate: adăugat $tabel.$coloana\n";
    }
}
