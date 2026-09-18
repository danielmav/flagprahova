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

/**
 * @return array{galerie_imagini:int, meniu:int, fisiere:int, sectiuni_curatate:int, setari:int, seed_iesire:string, seed_cod:int}
 */
function reseteaza(PDO $pdo, string $root): array
{
    $rap = [];
    $rap['galerie_imagini'] = $pdo->exec('DELETE FROM galerie_imagini');
    $rap['meniu']           = $pdo->exec('DELETE FROM meniu');
    $rap['fisiere']         = $pdo->exec('DELETE FROM fisiere');
    $rap['sectiuni_curatate'] = $pdo->exec('UPDATE sectiuni SET acasa_html = NULL');

    $placeholders = implode(',', array_fill(0, count(Setari::CHEI), '?'));
    $st = $pdo->prepare("DELETE FROM setari WHERE cheie IN ($placeholders)");
    $st->execute(Setari::CHEI);
    $rap['setari'] = $st->rowCount();

    // Sursa unică de adevăr pentru valorile implicite e `seed.php`: îl rulăm ca
    // proces separat (nu `require`, ca să nu redeclarăm clasele din vendor/autoload).
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
