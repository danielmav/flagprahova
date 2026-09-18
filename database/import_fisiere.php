<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Fisiere\Repository as Fisiere;
use App\Fisiere\Upload;

function importa_fisiere(ZipArchive $zip, string $dest, Fisiere $repo, ?string $doar = null, int $limita = 0): array
{
    $r = ['importate' => 0, 'existente' => 0, 'sarite_miniaturi' => 0, 'sarite_extensie' => 0, 'erori' => []];
    $ext = array_merge(Upload::EXTENSII, ['gif']);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nume = $zip->getNameIndex($i);
        if (!preg_match('#^wp-content/uploads/(\d{4}/\d{2})/([^/]+)$#', $nume, $m)) { continue; }
        [$_, $luna, $fisier] = $m;
        if ($doar !== null && !str_starts_with($luna, $doar)) { continue; }
        if (preg_match('/-\d+x\d+\.(jpe?g|png|webp|gif)$/i', $fisier)) { $r['sarite_miniaturi']++; continue; }
        $e = strtolower(pathinfo($fisier, PATHINFO_EXTENSION));
        if (!in_array($e, $ext, true)) { $r['sarite_extensie']++; continue; }
        $legacy = "$luna/$fisier";
        $existent = gaseste_dupa_legacy($repo, $legacy);
        if ($existent !== null && is_file(rtrim($dest, '/\\') . '/' . $existent['cale'])) { $r['existente']++; continue; }
        if ($limita > 0 && $r['importate'] >= $limita) { break; }
        $continut = $zip->getFromIndex($i);
        if ($continut === false) { $r['erori'][] = "$legacy: nu pot citi din arhivă"; continue; }
        $dir = rtrim($dest, '/\\') . "/$luna";
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) { $r['erori'][] = "$legacy: nu pot crea $dir"; continue; }
        $sigur = Upload::numeSigur($fisier);
        $baza = pathinfo($sigur, PATHINFO_FILENAME);
        $cand = $sigur;
        for ($n = 2; is_file("$dir/$cand") && md5_file("$dir/$cand") !== md5($continut); $n++) {
            $cand = "$baza-$n.$e";
        }
        if (!is_file("$dir/$cand") && file_put_contents("$dir/$cand", $continut) === false) { $r['erori'][] = "$legacy: nu pot scrie"; continue; }
        $mime = $finfo->buffer(substr($continut, 0, 8192)) ?: 'application/octet-stream';
        $repo->inregistreaza([
            'nume_afisat' => $fisier, 'cale' => "$luna/$cand", 'mime' => $mime,
            'marime' => strlen($continut), 'incarcat_de' => null, 'legacy_url' => $legacy,
        ]);
        $r['importate']++;
    }
    return $r;
}

function gaseste_dupa_legacy(Fisiere $repo, string $legacy): ?array
{
    return $repo->gasesteDupaLegacy($legacy);
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    ini_set('memory_limit', '512M');
    $root = dirname(__DIR__);
    if (is_file($root . '/.env')) { Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
    $settings = require $root . '/config/settings.php';
    $opt = getopt('', ['zip:', 'dest::', 'doar::', 'limita::']);
    $zipCale = (string) ($opt['zip'] ?? '');
    if ($zipCale === '' || !is_file($zipCale)) { fwrite(STDERR, "Folosire: import_fisiere.php --zip=cale.zip [--dest=dir] [--doar=AAAA/LL] [--limita=N]\n"); exit(1); }
    $zip = new ZipArchive();
    if ($zip->open($zipCale) !== true) { fwrite(STDERR, "Nu pot deschide arhiva.\n"); exit(1); }
    $repo = new Fisiere(new App\Database($settings['db']));
    $t = microtime(true);
    $r = importa_fisiere($zip, (string) ($opt['dest'] ?? $settings['upload']['dir']), $repo, $opt['doar'] ?? null, (int) ($opt['limita'] ?? 0));
    $zip->close();
    printf("importate %d, existente %d, sarite miniaturi %d, sarite extensie %d, erori %d, %.1f s\n", $r['importate'], $r['existente'], $r['sarite_miniaturi'], $r['sarite_extensie'], count($r['erori']), microtime(true) - $t);
    foreach ($r['erori'] as $e) { echo "  ! $e\n"; }
}
