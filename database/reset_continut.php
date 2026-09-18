<?php
declare(strict_types=1);

/**
 * Reset DOAR pentru dev: șterge tot conținutul migrat (galerii, meniu, fișiere)
 * și readuce `setari` la valorile implicite din `seed.php`, ca să poți rula
 * migrarea de la zero fără să reinstalezi baza. Refuză fără `--da` sau dacă
 * `APP_ENV` nu e `dev`.
 *
 * Fișierele de pe disc (`fisiere/AAAA/LL/...`) NU se șterg — rulează apoi
 * `import_fisiere.php` ca să repopulezi tabela `fisiere` din arhivă (fișierele
 * deja prezente pe disc sunt doar re-înregistrate, nu rescrise).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Setari\Repository as Setari;

/** Șterge recursiv un director (miniaturile din `fisiere/mini/`). */
function stergeDirector(string $dir): void
{
    foreach (scandir($dir) ?: [] as $nume) {
        if ($nume === '.' || $nume === '..') { continue; }
        $cale = $dir . '/' . $nume;
        if (is_dir($cale)) {
            stergeDirector($cale);
        } else {
            @unlink($cale);
        }
    }
    @rmdir($dir);
}

/**
 * @return array{galerie_imagini:int, meniu:int, fisiere:int, sectiuni_curatate:int, setari:int, seed_iesire:string, seed_cod:int}
 */
function reseteaza(PDO $pdo, string $root): array
{
    $rap = [];
    // Numărătorile se iau ÎNAINTE de fiecare DELETE, nu din valoarea întoarsă de
    // `PDO::exec()`: `meniu.parent_id` are FK auto-referențiat cu `ON DELETE CASCADE`
    // (`fk_meniu_parent`) — rândurile șterse prin cascadă, când scanarea DELETE-ului
    // ajunge la ele, sunt deja dispărute, deci NU intră în numărul de rânduri afectate
    // raportat de `exec()`. Fără fix-ul ăsta, „meniu sterse” arăta mult sub realitate
    // (ex. 17 în loc de 253), deși ștergerea propriu-zisă era completă și corectă.
    $rap['galerie_imagini'] = (int) $pdo->query('SELECT COUNT(*) FROM galerie_imagini')->fetchColumn();
    $pdo->exec('DELETE FROM galerie_imagini');
    $rap['meniu'] = (int) $pdo->query('SELECT COUNT(*) FROM meniu')->fetchColumn();
    $pdo->exec('DELETE FROM meniu');
    $rap['fisiere'] = (int) $pdo->query('SELECT COUNT(*) FROM fisiere')->fetchColumn();
    $pdo->exec('DELETE FROM fisiere');
    $rap['sectiuni_curatate'] = $pdo->exec('UPDATE sectiuni SET acasa_html = NULL');

    // Miniaturile se regenerează la cerere (App\Fisiere\Miniatura) — nu au sens
    // fără intrările din `fisiere` pe care tocmai le-am șters.
    $dirMini = $root . '/fisiere/mini';
    if (is_dir($dirMini)) {
        stergeDirector($dirMini);
    }

    $placeholders = implode(',', array_fill(0, count(Setari::CHEI), '?'));
    $st = $pdo->prepare("DELETE FROM setari WHERE cheie IN ($placeholders)");
    $st->execute(Setari::CHEI);
    $rap['setari'] = $st->rowCount();

    // Sursa unică de adevăr pentru valorile implicite e `seed.php`: îl rulăm ca
    // proces separat (nu `require`, ca să nu redeclarăm clasele din vendor/autoload).
    // `exec()` poate fi dezactivată din `disable_functions` (frecvent pe hosting
    // partajat) — fără verificare, eroarea ar fi un fatal opac „Call to undefined
    // function exec()”, exact în momentul în care setările tocmai au fost șterse.
    if (!function_exists('exec')) {
        $rap['seed_iesire'] = "exec() e dezactivata (disable_functions) — ruleaza manual: php database/seed.php";
        $rap['seed_cod'] = 1;
        return $rap;
    }
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/database/seed.php') . ' 2>&1';
    exec($cmd, $out, $cod);
    $rap['seed_iesire'] = implode("\n", $out);
    $rap['seed_cod'] = $cod;

    return $rap;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $root = dirname(__DIR__);
    if (is_file($root . '/.env')) {
        Dotenv\Dotenv::createImmutable($root)->safeLoad();
    }
    $settings = require $root . '/config/settings.php';
    if (($settings['app']['env'] ?? 'prod') !== 'dev') {
        fwrite(STDERR, "Refuzat: reset_continut.php ruleaza doar cu APP_ENV=dev.\n");
        exit(1);
    }
    $opt = getopt('', ['da']);
    if (!array_key_exists('da', $opt)) {
        fwrite(STDERR, "Sterge TOT continutul migrat (galerii, meniu, fisiere) si setarile editabile, apoi reface setarile din seed.php.\nFisierele de pe disc NU se sterg. Ruleaza cu --da ca sa confirmi.\n");
        exit(1);
    }
    $pdo = (new App\Database($settings['db']))->pdo();
    $r = reseteaza($pdo, $root);
    printf(
        "galerie_imagini sterse %d, meniu sterse %d, fisiere sterse %d, sectiuni curatate %d, setari sterse %d\n",
        $r['galerie_imagini'], $r['meniu'], $r['fisiere'], $r['sectiuni_curatate'], $r['setari']
    );
    echo "seed.php:\n" . $r['seed_iesire'] . "\n";
    exit($r['seed_cod'] === 0 ? 0 : 1);
}
