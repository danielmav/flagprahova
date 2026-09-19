<?php
// Dump SQL pentru server (import prin phpMyAdmin, fără SSH): tot conținutul,
// fără contul local de dezvoltare, fără tokenuri/încercări/mesaje.
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
if (is_file($root . '/.env')) { Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
$s  = (require $root . '/config/settings.php')['db'];
$opt = getopt('', ['out::', 'mysqldump::']);
$out = $opt['out'] ?? $root . '/storage/migrare/flagprahova-server.sql';
$dump = $opt['mysqldump'] ?? 'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe';
if (!is_file($dump)) { fwrite(STDERR, "mysqldump lipsește: $dump\n"); exit(1); }

$pdo = (new App\Database($s))->pdo();
$clienti = (int) $pdo->query("SELECT COUNT(*) FROM utilizatori WHERE email <> 'admin@flagprahova.ro'")->fetchColumn();
if ($clienti === 0) { fwrite(STDERR, "Nu exista contul clientului in `utilizatori` (doar admin@flagprahova.ro). Creeaza-l cu database/create_admin.php inainte de export.\n"); exit(1); }

$baza = ['--host=' . $s['host'], '--port=' . $s['port'], '--user=' . $s['user'], '--default-character-set=utf8mb4', '--skip-comments', '--single-transaction', '--add-drop-table'];
if ($s['pass'] !== '') { $baza[] = '--password=' . $s['pass']; }
$ruleaza = function (array $args) use ($dump, $baza, $s): string {
    // mysqldump cere: [opțiuni] baza_de_date [tabel ...] — opțiunile din $args
    // (încep cu `--`) trebuie separate de numele tabelelor, ca baza să rămână
    // imediat după opțiuni și tabelele să vină după ea, nu invers.
    $optiuni = array_values(array_filter($args, static fn ($a) => str_starts_with($a, '--')));
    $tabele  = array_values(array_filter($args, static fn ($a) => !str_starts_with($a, '--')));
    $cmd = escapeshellarg($dump) . ' ' . implode(' ', array_map('escapeshellarg', [...$baza, ...$optiuni, $s['name'], ...$tabele]));
    exec($cmd . ' 2>&1', $o, $c);
    if ($c !== 0) { fwrite(STDERR, "mysqldump a esuat: " . implode("\n", $o) . "\n"); exit(1); }
    return implode("\n", $o) . "\n";
};

$sql  = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
$sql .= $ruleaza(['sectiuni', 'fisiere', 'meniu', 'galerie_imagini', 'setari']);
$sql .= $ruleaza(['--where=email <> \'admin@flagprahova.ro\'', 'utilizatori']);
$sql .= $ruleaza(['--no-data', 'parola_tokens', 'login_incercari', 'mesaje_contact']);
$sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
@mkdir(dirname($out), 0775, true);
file_put_contents($out, $sql);

foreach (['sectiuni', 'meniu', 'fisiere', 'galerie_imagini', 'setari', 'utilizatori'] as $t) {
    $n = $t === 'utilizatori' ? $clienti : (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    echo str_pad($t, 16) . $n . "\n";
}
echo "scris $out (" . round(filesize($out) / 1024) . " KB)\n";
