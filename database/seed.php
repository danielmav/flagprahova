<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
if (is_file($root . '/.env')) { Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
$settings = require $root . '/config/settings.php';
$pdo = (new App\Database($settings['db']))->pdo();

$sectiuni = [
    ['slug' => '2021-2027', 'titlu' => 'FLAG Prahova 2021-2027', 'subtitlu' => 'Programul pentru Acvacultură și Pescuit 2021-2027', 'ordine' => 1],
    ['slug' => '2014-2020', 'titlu' => 'FLAG Prahova 2014-2020', 'subtitlu' => 'Programul Operațional pentru Pescuit și Afaceri Maritime 2014-2020', 'ordine' => 2],
];
$st = $pdo->prepare('INSERT INTO sectiuni (slug, titlu, subtitlu, ordine) VALUES (:s, :t, :st, :o)
    ON DUPLICATE KEY UPDATE titlu = VALUES(titlu), subtitlu = VALUES(subtitlu), ordine = VALUES(ordine)');
foreach ($sectiuni as $s) {
    $st->execute(['s' => $s['slug'], 't' => $s['titlu'], 'st' => $s['subtitlu'], 'o' => $s['ordine']]);
}

$setari = [
    'contact_email_destinatar' => 'flagprahova@gmail.com',
    'landing_titlu'            => 'Asociația FLAG Prahova',
    'landing_text'             => 'Grup de acțiune locală pentru pescuit și acvacultură în județul Prahova.',
    'footer_text'              => 'Conținutul acestui material nu reprezintă în mod obligatoriu poziția oficială a Uniunii Europene sau a Guvernului României.',
    'contact_adresa'           => 'Comuna Păulești, Sat Găgeni, nr. 41, județul Prahova',
    'contact_telefon'          => '0762 609 685',
    'contact_email_public'     => 'flagprahova@gmail.com',
];
$st = $pdo->prepare('INSERT IGNORE INTO setari (cheie, valoare) VALUES (:c, :v)');
foreach ($setari as $c => $v) {
    $st->execute(['c' => $c, 'v' => $v]);
}
echo "seed: " . count($sectiuni) . " secțiuni, " . count($setari) . " setări\n";
