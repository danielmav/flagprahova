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

// Parola NU pleacă pe linia de comandă (ar apărea în lista de procese și ar
// declanșa avertismentul mysqldump „Using a password on the command line
// interface can be insecure”, care ar ajunge altfel amestecat cu stdout-ul).
// Se trece prin variabila de mediu MYSQL_PWD, setată doar cât durează exec-ul.
$baza = ['--host=' . $s['host'], '--port=' . $s['port'], '--user=' . $s['user'], '--default-character-set=utf8mb4', '--skip-comments', '--single-transaction', '--add-drop-table'];
$ruleaza = function (array $args) use ($dump, $baza, $s): string {
    // mysqldump cere: [opțiuni] baza_de_date [tabel ...] — opțiunile din $args
    // (încep cu `--`) trebuie separate de numele tabelelor, ca baza să rămână
    // imediat după opțiuni și tabelele să vină după ea, nu invers.
    $optiuni = array_values(array_filter($args, static fn ($a) => str_starts_with($a, '--')));
    $tabele  = array_values(array_filter($args, static fn ($a) => !str_starts_with($a, '--')));
    $cmd = escapeshellarg($dump) . ' ' . implode(' ', array_map('escapeshellarg', [...$baza, ...$optiuni, $s['name'], ...$tabele]));

    // stdout și stderr separate prin proc_open: stdout devine EXACT conținutul
    // dumpului, stderr (avertismente/erori mysqldump) nu ajunge niciodată în
    // fișierul SQL — se folosește doar pentru mesajul de eroare la exit != 0.
    if ($s['pass'] !== '') { putenv('MYSQL_PWD=' . $s['pass']); }
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) { fwrite(STDERR, "nu am putut porni mysqldump\n"); exit(1); }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $c = proc_close($proc);
    if ($s['pass'] !== '') { putenv('MYSQL_PWD'); }
    if ($c !== 0) { fwrite(STDERR, "mysqldump a esuat: $stderr\n"); exit(1); }
    return $stdout;
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
