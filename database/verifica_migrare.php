<?php
declare(strict_types=1);

/**
 * Verificare post-migrare: numără intrările per secțiune/tip, semnalează
 * documentele fără fișier, paginile goale, galeriile fără imagini, slug-urile
 * duplicate (nu ar trebui să existe) și hosturile externe rămase în conținut.
 *
 * Nu modifică nimic — doar citește. Rulează după `migrate_wp.php`.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * @param string $hostPropriu host-ul sitului (ex. din settings.app.url), exclus din lista de hosturi externe
 * @return array{erori: string[], sumar: array<string, array<string, int>>, externe: string[], documente_fara_fisier: int}
 */
function verifica(PDO $pdo, string $dirFisiere, string $hostPropriu = ''): array
{
    $erori = [];
    $sumar = [];
    $documenteFaraFisier = 0;

    $rows = $pdo->query(
        'SELECT m.*, s.slug AS sectiune_slug FROM meniu m JOIN sectiuni s ON s.id = m.sectiune_id ORDER BY m.id'
    )->fetchAll();

    foreach ($rows as $r) {
        $sumar[$r['sectiune_slug']][$r['tip']] = ($sumar[$r['sectiune_slug']][$r['tip']] ?? 0) + 1;
    }

    // Documente fără `fisier_id` sau cu fișier lipsă pe disc (rândul din `fisiere`
    // poate lipsi și el, dacă baza a fost coruptă manual). `prepare()` o singură
    // dată, în afara buclei — doar parametrii se schimbă la fiecare `execute()`.
    $dirFisiere = rtrim($dirFisiere, '/\\');
    $stFisier = $pdo->prepare('SELECT cale FROM fisiere WHERE id = :id');
    foreach ($rows as $r) {
        if ($r['tip'] !== 'document') {
            continue;
        }
        $eticheta = "document #{$r['id']} „{$r['titlu']}” ({$r['sectiune_slug']})";
        if ($r['fisier_id'] === null) {
            $erori[] = "$eticheta: fara fisier_id";
            $documenteFaraFisier++;
            continue;
        }
        $stFisier->execute(['id' => (int) $r['fisier_id']]);
        $cale = $stFisier->fetchColumn();
        if ($cale === false) {
            $erori[] = "$eticheta: fisier_id {$r['fisier_id']} nu exista in tabela fisiere";
            $documenteFaraFisier++;
            continue;
        }
        if (!is_file($dirFisiere . '/' . $cale)) {
            $erori[] = "$eticheta: fisier lipsa pe disc ($cale)";
            $documenteFaraFisier++;
        }
    }

    // Pagini cu `continut_html` gol.
    foreach ($rows as $r) {
        if ($r['tip'] === 'pagina' && trim((string) $r['continut_html']) === '') {
            $erori[] = "pagina #{$r['id']} „{$r['titlu']}” ({$r['sectiune_slug']}): continut_html gol";
        }
    }

    // Galerii fără imagini.
    $stGalerie = $pdo->prepare('SELECT COUNT(*) FROM galerie_imagini WHERE meniu_id = :id');
    foreach ($rows as $r) {
        if ($r['tip'] !== 'galerie') {
            continue;
        }
        $stGalerie->execute(['id' => (int) $r['id']]);
        if ((int) $stGalerie->fetchColumn() === 0) {
            $erori[] = "galerie #{$r['id']} „{$r['titlu']}” ({$r['sectiune_slug']}): fara imagini";
        }
    }

    // Slug duplicat pe aceeași secțiune: NU ar trebui să apară niciodată — la scriere,
    // `Meniu\Repository::slugUnic()` verifică deja unicitatea (per secțiune) înainte de
    // orice INSERT/UPDATE. Verificarea de aici e doar o plasă de siguranță, pentru cazul
    // în care baza a fost modificată direct (SQL manual, import extern etc.).
    $dupl = $pdo->query(
        'SELECT sectiune_id, slug, COUNT(*) AS n FROM meniu GROUP BY sectiune_id, slug HAVING n > 1'
    )->fetchAll();
    foreach ($dupl as $d) {
        $erori[] = "slug duplicat „{$d['slug']}” in sectiunea {$d['sectiune_id']} ({$d['n']} intrari)";
    }

    // Hosturi externe rămase în `continut_html` (informativ, nu e eroare). Hostul
    // propriu al sitului (ex. flagprahova.ro) nu e „extern” — se exclude din listă.
    $hostPropriu = strtolower((string) parse_url($hostPropriu, PHP_URL_HOST));
    $externe = [];
    foreach ($rows as $r) {
        $html = (string) $r['continut_html'];
        if ($html === '' || !preg_match_all('#https?://([a-z0-9.-]+)#i', $html, $m)) {
            continue;
        }
        foreach ($m[1] as $h) {
            $h = strtolower($h);
            if ($h === $hostPropriu || in_array($h, $externe, true)) {
                continue;
            }
            $externe[] = $h;
        }
    }
    sort($externe);

    return ['erori' => $erori, 'sumar' => $sumar, 'externe' => $externe, 'documente_fara_fisier' => $documenteFaraFisier];
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $root = dirname(__DIR__);
    if (is_file($root . '/.env')) {
        Dotenv\Dotenv::createImmutable($root)->safeLoad();
    }
    $settings = require $root . '/config/settings.php';
    $pdo = (new App\Database($settings['db']))->pdo();
    $r = verifica($pdo, (string) $settings['upload']['dir'], (string) $settings['app']['url']);

    echo "Sumar intrari per sectiune/tip:\n";
    foreach ($r['sumar'] as $sectiune => $tipuri) {
        foreach ($tipuri as $tip => $n) {
            echo "  $sectiune / $tip: $n\n";
        }
    }
    echo "\nHosturi externe in continut_html: " . (implode(', ', $r['externe']) ?: '(niciunul)') . "\n";

    if ($r['erori'] === []) {
        echo "\nOK — nicio eroare gasita.\n";
    } else {
        // Problemele sunt un rezultat de diagnostic, nu output normal al comenzii:
        // pe STDERR, ca `2>/dev/null` sau un pipe pe stdout să nu le piardă/amestece.
        fwrite(STDERR, "\n" . count($r['erori']) . " problema(e):\n");
        foreach ($r['erori'] as $e) {
            fwrite(STDERR, "  ! $e\n");
        }
    }
    echo "\ndocumente fara fisier: {$r['documente_fara_fisier']}\n";

    // Coduri de ieșire: 0 = curat; 1 = cea mai gravă problemă (documente fără fișier,
    // conținut nepublicabil); 2 = alte probleme (pagini goale, galerii fără imagini,
    // slug duplicat) fără documente lipsă — de investigat, dar nu blocante.
    if ($r['documente_fara_fisier'] > 0) {
        exit(1);
    }
    exit($r['erori'] === [] ? 0 : 2);
}
