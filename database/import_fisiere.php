<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Fisiere\Repository as Fisiere;
use App\Fisiere\Upload;

function importa_fisiere(ZipArchive $zip, string $dest, Fisiere $repo, ?string $doar = null, int $limita = 0, bool $verbose = false): array
{
    $r = ['importate' => 0, 'existente' => 0, 'sarite_miniaturi' => 0, 'sarite_extensie' => 0, 'erori' => []];
    if ($verbose) { $r['miniaturi_sarite'] = []; }
    $ext = array_merge(Upload::EXTENSII, ['gif']);
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    // Set cu toate intrările din wp-content/uploads/AAAA/LL/nume — necesar ca să distingem o
    // miniatură WP reală (are originalul fără sufix `-LxÎ` alături) de un atașament original
    // al cărui nume conține întâmplător un sufix `-NNNxNNN` (ex. `cropped-646x404.jpg`).
    $toate = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = $zip->getNameIndex($i);
        if (preg_match('#^wp-content/uploads/(\d{4}/\d{2})/([^/]+)$#', $n, $mm)) {
            $toate["$mm[1]/$mm[2]"] = true;
        }
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nume = $zip->getNameIndex($i);
        if (!preg_match('#^wp-content/uploads/(\d{4}/\d{2})/([^/]+)$#', $nume, $m)) { continue; }
        [$_, $luna, $fisier] = $m;
        if ($doar !== null && !str_starts_with($luna, $doar)) { continue; }
        $legacy = "$luna/$fisier";
        if (preg_match('/^(.+)-\d+x\d+\.(jpe?g|png|webp|gif)$/i', $fisier, $mt)) {
            $original = "$luna/{$mt[1]}.{$mt[2]}";
            if (isset($toate[$original])) {
                $r['sarite_miniaturi']++;
                if ($verbose) { $r['miniaturi_sarite'][] = $legacy; }
                continue;
            }
            // altfel: arată ca o miniatură dar originalul nu e în arhivă — e chiar atașamentul,
            // se importă normal mai jos.
        }
        $e = strtolower(pathinfo($fisier, PATHINFO_EXTENSION));
        if (!in_array($e, $ext, true)) { $r['sarite_extensie']++; continue; }
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
        // Fără reuse pe md5 în cazul GENERAL: reuse-ul vine DOAR din gasesteDupaLegacy()
        // de mai sus. Două atașamente WP diferite cu conținut identic primesc fișiere
        // separate pe disc, altfel a doua rulare ar suprascrie legacy_url-ul rândului
        // existent (vezi revizie).
        //
        // EXCEPȚIE: după `reset_continut.php` (DELETE FROM fisiere, fișierele rămân pe
        // disc), tabela e complet goală, deci exact fișierele deja importate anterior
        // par acum „nerevendicate”. Fără verificarea de mai jos, bucla de coliziune le-ar
        // considera pe toate „ocupate” și ar scrie dubluri „-2”, „-3”... pentru fiecare
        // fișier real. Detectăm acest caz per candidat: dacă fișierul de pe disc nu e
        // revendicat încă de niciun rând ÎN ACEASTĂ RULARE (gasesteDupaCale) și conținutul
        // lui e byte-identic cu cel din arhivă, e propriul fișier de dinainte de reset —
        // îl (re)înregistrăm fără să-l rescriem. Ordinea de parcurgere a arhivei e
        // deterministă (aceeași arhivă), deci coliziunile se reconstituie identic.
        $reuseFaraScriere = false;
        for ($n = 1; is_file("$dir/$cand"); $n++) {
            if ($repo->gasesteDupaCale("$luna/$cand") === null
                && filesize("$dir/$cand") === strlen($continut)
                && hash_equals((string) md5_file("$dir/$cand"), md5($continut))
            ) {
                $reuseFaraScriere = true;
                break;
            }
            $cand = "$baza-" . ($n + 1) . ".$e";
        }
        if ($reuseFaraScriere) {
            $mime = $finfo->file("$dir/$cand") ?: 'application/octet-stream';
        } else {
            if (file_put_contents("$dir/$cand", $continut) === false) { $r['erori'][] = "$legacy: nu pot scrie"; continue; }
            $mime = $finfo->buffer(substr($continut, 0, 8192)) ?: 'application/octet-stream';
        }
        if ($mime === 'application/zip' && in_array($e, ['docx', 'xlsx', 'pptx', 'odt'], true)) {
            $mimeOffice = Upload::mimeOffice("$dir/$cand", $e);
            if ($mimeOffice !== null) { $mime = $mimeOffice; }
        }
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
    $opt = getopt('', ['zip:', 'dest::', 'doar::', 'limita::', 'verbose']);
    $zipCale = (string) ($opt['zip'] ?? '');
    if ($zipCale === '' || !is_file($zipCale)) { fwrite(STDERR, "Folosire: import_fisiere.php --zip=cale.zip [--dest=dir] [--doar=AAAA/LL] [--limita=N] [--verbose]\n"); exit(1); }
    $zip = new ZipArchive();
    if ($zip->open($zipCale) !== true) { fwrite(STDERR, "Nu pot deschide arhiva.\n"); exit(1); }
    $repo = new Fisiere(new App\Database($settings['db']));
    $verbose = array_key_exists('verbose', $opt);
    $t = microtime(true);
    $r = importa_fisiere($zip, (string) ($opt['dest'] ?? $settings['upload']['dir']), $repo, $opt['doar'] ?? null, (int) ($opt['limita'] ?? 0), $verbose);
    $zip->close();
    printf("importate %d, existente %d, sarite miniaturi %d, sarite extensie %d, erori %d, %.1f s\n", $r['importate'], $r['existente'], $r['sarite_miniaturi'], $r['sarite_extensie'], count($r['erori']), microtime(true) - $t);
    foreach ($r['erori'] as $e) { echo "  ! $e\n"; }
    if ($verbose) { foreach ($r['miniaturi_sarite'] as $mn) { echo "  ~ miniatură: $mn\n"; } }
}
