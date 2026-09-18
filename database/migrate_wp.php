<?php
declare(strict_types=1);

/**
 * Migrarea conținutului din WordPress: arborele de meniu 2014-2020, meniul fix
 * 2021-2027, paginile curățate, galeriile, contactul și textele Acasă.
 * Idempotent: upsert pe `meniu.legacy_id`.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Migrare\Curata;
use App\Migrare\Harta;
use App\Migrare\Legacy;
use App\Support\Html;

/**
 * @param array{legacy:Legacy,meniu:App\Meniu\Repository,galerie:App\Meniu\GalerieRepository,fisiere:App\Fisiere\Repository,pdo:PDO,root:string} $ctx
 * @return array{creat:int,actualizat:int,sarit:string[],galerii:int,imagini_lipsa:int,linkuri_rupte:string[],externe:string[],spam:int}
 */
function migreaza(array $ctx, bool $verbose = true): array
{
    $legacy = $ctx['legacy'];
    $meniu = $ctx['meniu'];
    $galerie = $ctx['galerie'];
    $fisiere = $ctx['fisiere'];
    $pdo = $ctx['pdo'];
    $rap = ['creat' => 0, 'actualizat' => 0, 'sarit' => [], 'galerii' => 0, 'imagini_lipsa' => 0, 'linkuri_rupte' => [], 'externe' => [], 'spam' => 0];
    $sect = [];
    foreach ($meniu->sectiuni() as $s) {
        $sect[$s['slug']] = (int) $s['id'];
    }
    $curata = new Curata(fn(string $c): ?string => $fisiere->gasesteDupaLegacy($c)['cale'] ?? null);
    $existaFisier = fn(string $c): bool => $fisiere->gasesteDupaLegacy($c) !== null;

    $items = $legacy->meniu();
    $copii = [];
    foreach ($items as $it) {
        $copii[$it['parent']][] = $it;
    }

    $upsert = function (int $legacyId, array $date) use ($meniu, &$rap): int {
        $ex = $meniu->gasesteDupaLegacy($legacyId);
        if ($ex !== null) {
            // Slug-ul nu se trimite la actualizare: `actualizeaza()` îl re-derivă
            // din titlu, deterministic, deci URL-urile publice rămân stabile.
            unset($date['slug']);
            $meniu->actualizeaza((int) $ex['id'], $date);
            $rap['actualizat']++;
            return (int) $ex['id'];
        }
        $rap['creat']++;
        return $meniu->creeaza($date + ['legacy_id' => $legacyId, 'slug' => '']);
    };

    $paginaHtml = function (int $paginaId) use ($legacy, $curata, &$rap): array {
        $p = $legacy->pagina($paginaId, in_array($paginaId, Harta::PAGINI_BUILDER, true));
        $r = $curata->proceseaza((string) ($p['continut'] ?? ''));
        $rap['linkuri_rupte'] = array_merge($rap['linkuri_rupte'], $r['linkuri_rupte']);
        $rap['externe'] = array_values(array_unique(array_merge($rap['externe'], $r['externe'])));
        $rap['spam'] += $r['spam_eliminat'];
        return $r;
    };

    $adaugaGalerii = function (int $parintePaginaId, int $paginaId, array $galerii, int $sid) use ($upsert, $galerie, $legacy, $fisiere, &$rap): void {
        foreach (array_values($galerii) as $i => $g) {
            $gid = $upsert(800000 + $paginaId * 100 + $i, ['sectiune_id' => $sid, 'parent_id' => $parintePaginaId, 'titlu' => $g['titlu'], 'tip' => 'galerie', 'vizibil' => 1]);
            $cai = $legacy->atasamenteCai($g['ids']);
            $set = [];
            foreach ($g['ids'] as $aid) {
                $f = isset($cai[$aid]) ? $fisiere->gasesteDupaLegacy($cai[$aid]) : null;
                if ($f === null) {
                    $rap['imagini_lipsa']++;
                    continue;
                }
                $set[] = ['fisier_id' => (int) $f['id'], 'legenda' => ''];
            }
            $galerie->seteaza($gid, $set);
            $rap['galerii']++;
        }
    };

    // --- 2014-2020 ---
    $sid20 = $sect['2014-2020'];
    $arbore20 = [];
    $root = (string) $ctx['root'];
    $parcurge = function (array $lista, ?int $parintNou, array &$arboreNod) use (&$parcurge, $copii, $legacy, $fisiere, $meniu, $root, $existaFisier, $upsert, $paginaHtml, $adaugaGalerii, $sid20, &$rap): void {
        foreach ($lista as $it) {
            if (in_array($it['id'], Harta::SET_2021, true)) {
                continue;
            }
            if ($it['tip'] === 'post_type' && in_array($it['obiect_id'], Harta::PAGINI_SARITE, true)) {
                continue;
            }
            $pag = $it['tip'] === 'post_type' ? $legacy->pagina($it['obiect_id'], in_array($it['obiect_id'], Harta::PAGINI_BUILDER, true)) : null;
            $areCopii = !empty($copii[$it['id']]);
            $cls = Harta::clasifica($it, $pag, $areCopii, $existaFisier);
            if ($cls['tip'] === 'sari') {
                $rap['sarit'][] = "{$it['id']} „{$it['titlu']}”: {$cls['motiv']}";
                continue;
            }
            $date = ['sectiune_id' => $sid20, 'parent_id' => $parintNou, 'titlu' => $it['titlu'], 'tip' => $cls['tip'], 'url' => $cls['url'] ?? '', 'vizibil' => 1, 'sablon' => 'standard'];
            $galerii = [];
            if ($cls['tip'] === 'document') {
                $date['fisier_id'] = (int) $fisiere->gasesteDupaLegacy($cls['cale'])['id'];
            }
            if ($cls['tip'] === 'pagina') {
                if ($it['id'] === Harta::LEGACY_CONTACT_VECHI) {
                    // Formularul CF7 și tabelul vechi nu se pot refolosi.
                    $date['continut_html'] = Html::curata((string) file_get_contents($root . '/database/data/contact-2014-2020.html'));
                    $date['sablon'] = 'contact';
                } else {
                    $r = $paginaHtml($it['obiect_id']);
                    if ($r['html'] === '' && $r['galerii'] === [] && !$areCopii) {
                        $rap['sarit'][] = "{$it['id']} „{$it['titlu']}”: continut gol dupa curatare";
                        continue;
                    }
                    $date['continut_html'] = $r['html'];
                    $galerii = $r['galerii'];
                }
            }
            $idNou = $upsert($it['id'], $date);
            $nod = ['id' => $idNou, 'copii' => []];
            if ($galerii !== []) {
                $adaugaGalerii($idNou, $it['obiect_id'], $galerii, $sid20);
                foreach ($meniu->copii($idNou) as $c) {
                    if ($c['tip'] === 'galerie') {
                        $nod['copii'][] = ['id' => (int) $c['id'], 'copii' => []];
                    }
                }
            }
            if ($areCopii) {
                $parcurge($copii[$it['id']], $idNou, $nod['copii']);
            }
            $arboreNod[] = $nod;
        }
    };
    $parcurge($copii[0] ?? [], null, $arbore20);
    $meniu->reordoneaza($sid20, $arbore20);

    // --- 2021-2027 ---
    $sid21 = $sect['2021-2027'];
    $arbore21 = [];
    $utileHtml = $paginaHtml(252)['html'];
    foreach (Harta::MENIU_2021 as $def) {
        $date = ['sectiune_id' => $sid21, 'parent_id' => null, 'titlu' => $def['titlu'], 'tip' => $def['tip'], 'vizibil' => 1, 'sablon' => $def['sablon'] ?? 'standard'];
        if ($def['legacy'] === Harta::UTILE_2021) {
            $date['continut_html'] = $utileHtml;
        }
        if ($def['legacy'] === Harta::CONTACT_2021) {
            $date['continut_html'] = Html::curata((string) file_get_contents($ctx['root'] . '/database/data/contact-2021-2027.html'));
        }
        $id = $upsert($def['legacy'], $date);
        $nod = ['id' => $id, 'copii' => []];
        foreach ($def['copii'] ?? [] as $c) {
            $nod['copii'][] = ['id' => $upsert($c['legacy'], ['sectiune_id' => $sid21, 'parent_id' => $id, 'titlu' => $c['titlu'], 'tip' => $c['tip'], 'vizibil' => 1]), 'copii' => []];
        }
        if ($def['legacy'] === Harta::NOUTATI_2021 || $def['legacy'] === Harta::STRATEGIE_2021) {
            $tinta = $def['legacy'] === Harta::NOUTATI_2021 ? 250 : 204;
            foreach ($copii[$tinta] ?? [] as $it) {
                if (!in_array($it['id'], Harta::SET_2021, true)) {
                    continue;
                }
                $cls = Harta::clasifica($it, null, false, $existaFisier);
                if ($cls['tip'] === 'sari') {
                    $rap['sarit'][] = "{$it['id']} „{$it['titlu']}”: {$cls['motiv']}";
                    continue;
                }
                $fid = $cls['tip'] === 'document' ? (int) $fisiere->gasesteDupaLegacy($cls['cale'])['id'] : null;
                $nod['copii'][] = ['id' => $upsert($it['id'], ['sectiune_id' => $sid21, 'parent_id' => $id, 'titlu' => $it['titlu'], 'tip' => $cls['tip'], 'fisier_id' => $fid, 'url' => $cls['url'] ?? '', 'vizibil' => 1]), 'copii' => []];
            }
        }
        $arbore21[] = $nod;
    }
    $meniu->reordoneaza($sid21, $arbore21);

    // --- Acasă ---
    $st = $pdo->prepare('UPDATE sectiuni SET acasa_html = :h WHERE slug = :s');
    $st->execute(['h' => $paginaHtml(75)['html'], 's' => '2014-2020']);
    $st->execute(['h' => Html::curata((string) file_get_contents($ctx['root'] . '/database/data/acasa-2021-2027.html')), 's' => '2021-2027']);

    $rap['linkuri_rupte'] = array_values(array_unique($rap['linkuri_rupte']));

    if ($verbose) {
        printf(
            "creat %d, actualizat %d, galerii %d, imagini lipsa %d, spam eliminat %d, sarite %d, linkuri rupte %d, externe: %s\n",
            $rap['creat'], $rap['actualizat'], $rap['galerii'], $rap['imagini_lipsa'], $rap['spam'], count($rap['sarit']), count($rap['linkuri_rupte']), implode(', ', $rap['externe'])
        );
        foreach ($rap['sarit'] as $s) {
            echo "  - sarit: $s\n";
        }
        foreach ($rap['linkuri_rupte'] as $l) {
            echo "  - link rupt: $l\n";
        }
    }
    return $rap;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $root = dirname(__DIR__);
    if (is_file($root . '/.env')) {
        Dotenv\Dotenv::createImmutable($root)->safeLoad();
    }
    $settings = require $root . '/config/settings.php';
    if (($settings['db_wp']['name'] ?? '') === '') {
        fwrite(STDERR, "DB_WP_NAME lipseste din .env\n");
        exit(1);
    }
    $c = $settings['db_wp'];
    $wp = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']), $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $db = new App\Database($settings['db']);
    migreaza(['legacy' => new Legacy($wp, (string) $c['prefix']), 'meniu' => new App\Meniu\Repository($db), 'galerie' => new App\Meniu\GalerieRepository($db), 'fisiere' => new App\Fisiere\Repository($db), 'pdo' => $db->pdo(), 'root' => $root]);
}
