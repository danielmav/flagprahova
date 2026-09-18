<?php
declare(strict_types=1);

/**
 * Test end-to-end pentru `migreaza()`, pe baza WordPress REALĂ.
 *
 * ATENȚIE: în `finally` face `DELETE FROM meniu WHERE legacy_id IS NOT NULL`,
 * deci șterge și rândurile unei migrări reale rulate anterior. Rulează-l
 * ÎNAINTE de migrarea reală (sau după `reset_continut.php`). Intrările de meniu
 * create manual (fără `legacy_id`) nu sunt atinse.
 *
 * Fișierele reale sunt deja importate în tabela `fisiere`; testul înregistrează
 * rânduri FALSE doar pentru căile care LIPSESC și șterge în `finally` doar ce a
 * creat el.
 */

require __DIR__ . '/_bootstrap.php';
require dirname(__DIR__) . '/database/migrate_wp.php';

use App\Migrare\Legacy;

$s = settings();
$pdo = pdo();
$wp = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $s['db_wp']['host'], $s['db_wp']['port'], $s['db_wp']['name']), $s['db_wp']['user'], $s['db_wp']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$db = new App\Database($s['db']);
$ctx = ['legacy' => new Legacy($wp), 'meniu' => new App\Meniu\Repository($db), 'galerie' => new App\Meniu\GalerieRepository($db), 'fisiere' => new App\Fisiere\Repository($db), 'pdo' => $pdo, 'root' => dirname(__DIR__)];

$acasaVechi = $pdo->query('SELECT slug, acasa_html FROM sectiuni')->fetchAll(PDO::FETCH_KEY_PAIR);
$fisiereTest = [];
// Fișiere false pentru căile referite de meniu și de galerii care nu au corespondent real;
// cu arhiva importată, lista e aproape goală.
$cai = [];
foreach ($ctx['legacy']->meniu() as $it) { $c = Legacy::caleDinUrl($it['url']); if ($c) { $cai[] = $c; } }
$cai = array_merge($cai, array_values($ctx['legacy']->atasamenteCai([2903, 2904, 2905])), ['2017/08/peste-la-cuptor.jpg', '2017/08/marinata-de-peste.jpg']);
foreach (array_unique($cai) as $c) {
    if ($ctx['fisiere']->gasesteDupaLegacy($c) !== null) { continue; } // fișier real deja importat
    $mime = preg_match('/\.(jpe?g|png)$/i', $c) ? 'image/jpeg' : 'application/pdf';
    $id = $ctx['fisiere']->inregistreaza(['nume_afisat' => basename($c), 'cale' => 'test-migrare/' . slugify($c) . '.' . pathinfo($c, PATHINFO_EXTENSION), 'mime' => $mime, 'marime' => 1, 'incarcat_de' => null, 'legacy_url' => $c]);
    $fisiereTest[] = $id;
}
try {
    $r = migreaza($ctx, false);
    $sid20 = (int) $pdo->query("SELECT id FROM sectiuni WHERE slug='2014-2020'")->fetchColumn();
    $sid21 = (int) $pdo->query("SELECT id FROM sectiuni WHERE slug='2021-2027'")->fetchColumn();
    $n20 = (int) $pdo->query("SELECT COUNT(*) FROM meniu WHERE sectiune_id=$sid20 AND legacy_id IS NOT NULL")->fetchColumn();
    $n21 = (int) $pdo->query("SELECT COUNT(*) FROM meniu WHERE sectiune_id=$sid21 AND legacy_id IS NOT NULL")->fetchColumn();
    ok('2014-2020: peste 190 de intrări migrate', $n20 >= 190);
    ok('2021-2027: 12 sintetice + 9 mutate = 21', $n21 === 21);
    ok('raport: creat = total (inclusiv galeriile)', $r['creat'] === $n20 + $n21);
    $m = $ctx['meniu'];
    $org = $m->gasesteDupaLegacy(579);
    ok('579 Organigrama => document cu fisier_id', $org && $org['tip'] === 'document' && (int) $org['fisier_id'] > 0);
    $proc = $m->gasesteDupaLegacy(237);
    ok('237 Proceduri => dosar, părinte Strategie (204)', $proc && $proc['tip'] === 'dosar' && (int) $proc['parent_id'] === (int) $m->gasesteDupaLegacy(204)['id']);
    ok('  Organigrama sub Proceduri', (int) $org['parent_id'] === (int) $proc['id']);
    $coop = $m->gasesteDupaLegacy(2984);
    ok('2984 Cooperare => pagina cu conținut curat (fără [vc_)', $coop && $coop['tip'] === 'pagina' && str_contains($coop['continut_html'], 'INTERACȚIUNE') && !str_contains($coop['continut_html'], '[vc_'));
    $gal = $m->copii((int) $coop['id']);
    ok('  14 galerii copii ale paginii Cooperare', count($gal) === 14 && $gal[0]['tip'] === 'galerie' && $gal[0]['titlu'] === 'Instruire vanzari');
    // Toate cele 455 de atașamente ale galeriilor există în `fisiere` (arhiva reală e importată).
    ok('  galeria 1 are 40 de imagini', count($ctx['galerie']->imagini((int) $gal[0]['id'])) === 40);
    ok('  nicio imagine lipsă', $r['imagini_lipsa'] === 0);
    $contact = $m->gasesteDupaLegacy(175);
    ok('175 Contact => pagina sablon contact cu ambele persoane', $contact && $contact['sablon'] === 'contact' && str_contains($contact['continut_html'], 'Olteanu'));
    $c21 = $m->gasesteDupaLegacy(9009);
    ok('9009 Contact 2021 => doar Laura', $c21 && $c21['sablon'] === 'contact' && str_contains($c21['continut_html'], 'Manolache') && !str_contains($c21['continut_html'], 'Olteanu'));
    $u21 = $m->gasesteDupaLegacy(9008);
    ok('9008 Utile 2021 => rețete cu imagine rescrisă', $u21 && str_contains($u21['continut_html'], 'Marinată') && str_contains($u21['continut_html'], '/fisiere/2017/08/marinata-de-peste.jpg'));
    $nou = $m->gasesteDupaLegacy(3622);
    ok('3622 mutat în 2021-2027 sub Noutăți (9001)', $nou && (int) $nou['sectiune_id'] === $sid21 && (int) $nou['parent_id'] === (int) $m->gasesteDupaLegacy(9001)['id']);
    ok('3601 DIGICO rămâne în 2014-2020 sub Noutăți (250)', (int) $m->gasesteDupaLegacy(3601)['sectiune_id'] === $sid20);
    $cons = $m->gasesteDupaLegacy(280);
    ok('280 Consultare publică => pagina din builder html (nu din post_content-ul spam)', $cons && $cons['tip'] === 'pagina' && str_contains($cons['continut_html'], 'Consultare publică') && !str_contains($cons['continut_html'], 'podcasts'));
    ok('spam eliminat > 0', $r['spam'] > 0);
    ok('acasa_html 2014-2020 setat', str_contains((string) $pdo->query("SELECT acasa_html FROM sectiuni WHERE id=$sid20")->fetchColumn(), 'contractului de finanțare'));
    ok('acasa_html 2021-2027 setat', strlen((string) $pdo->query("SELECT acasa_html FROM sectiuni WHERE id=$sid21")->fetchColumn()) > 200);
    // idempotență
    $r2 = migreaza($ctx, false);
    ok('re-rulare: 0 creat, nimic duplicat', $r2['creat'] === 0 && (int) $pdo->query("SELECT COUNT(*) FROM meniu WHERE legacy_id IS NOT NULL")->fetchColumn() === $n20 + $n21);
    ok('re-rulare: slug-ul nu se schimbă', $m->gasesteDupaLegacy(579)['slug'] === $org['slug']);
} finally {
    $pdo->exec('DELETE FROM meniu WHERE legacy_id IS NOT NULL');
    foreach ($fisiereTest as $id) { $pdo->exec("DELETE FROM fisiere WHERE id = $id"); }
    $st = $pdo->prepare('UPDATE sectiuni SET acasa_html = :h WHERE slug = :s');
    foreach ($acasaVechi as $slug => $h) { $st->execute(['h' => $h, 's' => $slug]); }
}
final_test();
