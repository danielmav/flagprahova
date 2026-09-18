<?php
declare(strict_types=1);

/**
 * Test end-to-end pentru `migreaza()`, pe baza WordPress REALĂ.
 *
 * Curățenia din `finally` e „chirurgicală”: se face un instantaneu al id-urilor
 * din `meniu` ÎNAINTE de migrare și se șterg doar id-urile apărute între timp,
 * în ordine descrescătoare (copiii sunt mereu creați după părinți). Intrările
 * manuale, ca și rândurile unei migrări reale anterioare, rămân neatinse.
 *
 * Fișierele reale sunt deja importate în tabela `fisiere`; testul înregistrează
 * rânduri FALSE doar pentru căile care LIPSESC și șterge doar ce a creat el.
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
$meniuVechi = array_flip(array_map('intval', $pdo->query('SELECT id FROM meniu')->fetchAll(PDO::FETCH_COLUMN)));
$fisiereTest = [];
$legacyUrlVechi = []; // id => legacy_url, pentru rândurile reale pe care testul le strică temporar
$slugVechi = [];      // id => slug, idem (testul rescrie slug-ul ca să verifice că migrarea nu-l resetează)
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
    // `creat` nu e neapărat egal cu totalul: dacă `meniu` are deja conținut migrat
    // (rulare pe bază reală, deja migrată), migreaza() actualizează în loc să creeze.
    // Suma creat+actualizat trebuie totuși să acopere tot, iar `creat` trebuie să
    // corespundă EXACT id-urilor noi apărute față de instantaneul de dinaintea acestui apel.
    $idsNoi = [];
    foreach (array_map('intval', $pdo->query('SELECT id FROM meniu WHERE legacy_id IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN)) as $id) {
        if (!isset($meniuVechi[$id])) { $idsNoi[] = $id; }
    }
    ok('raport: creat + actualizat = total (inclusiv galeriile)', $r['creat'] + $r['actualizat'] === $n20 + $n21);
    ok('raport: creat = intrările nou apărute față de instantaneu', $r['creat'] === count($idsNoi));
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
    // Numerele de telefon rămân apelabile după `Html::curata()` (schema `tel:` e permisă).
    ok('  175 Contact păstrează href="tel:+40762609685"', str_contains($contact['continut_html'], 'href="tel:+40762609685"'));
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
    // Lista intrărilor sărite e fixată: orice document pierdut la migrare (fișier
    // negăsit după `legacy_url`) ar adăuga un id nou aici și ar pica testul.
    // 176 („Acasă”) NU apare: pagina 75 e sărită tăcut prin `Harta::PAGINI_SARITE`
    // (`continue` fără raport), textul ei ajungând în `sectiuni.acasa_html`.
    $saritIds = array_values(array_unique(array_map(static fn(string $s): int => (int) $s, $r['sarit'])));
    sort($saritIds);
    ok('sarite: exact [333] — ' . implode(' | ', $r['sarit']), $saritIds === [333]);
    // Meniul vechi nu are nicio intrare clasificată `link` (toate URL-urile externe
    // erau spam, deja eliminat), deci lista de hosturi externe rămâne goală.
    ok('niciun link extern în meniul vechi => externe gol', (int) $pdo->query("SELECT COUNT(*) FROM meniu WHERE tip='link' AND legacy_id IS NOT NULL")->fetchColumn() === 0 && $r['externe'] === []);
    ok('acasa_html 2014-2020 setat', str_contains((string) $pdo->query("SELECT acasa_html FROM sectiuni WHERE id=$sid20")->fetchColumn(), 'contractului de finanțare'));
    ok('acasa_html 2021-2027 setat', strlen((string) $pdo->query("SELECT acasa_html FROM sectiuni WHERE id=$sid21")->fetchColumn()) > 200);

    // idempotență
    $r2 = migreaza($ctx, false);
    ok('re-rulare: 0 creat, nimic duplicat', $r2['creat'] === 0 && (int) $pdo->query("SELECT COUNT(*) FROM meniu WHERE legacy_id IS NOT NULL")->fetchColumn() === $n20 + $n21);
    ok('re-rulare: slug-ul nu se schimbă', $m->gasesteDupaLegacy(579)['slug'] === $org['slug']);

    // Slug editat manual din admin: migrarea nu îl resetează. Intrarea 579 poate fi
    // una REALĂ (bază deja migrată), deci salvăm slug-ul și îl punem la loc în `finally`.
    $slugVechi[(int) $org['id']] = (string) $org['slug'];
    $pdo->prepare('UPDATE meniu SET slug = :sl WHERE id = :id')->execute(['sl' => 'organigrama-editata-manual', 'id' => (int) $org['id']]);
    migreaza($ctx, false);
    ok('re-rulare: slug-ul editat manual rămâne', $m->gasesteDupaLegacy(579)['slug'] === 'organigrama-editata-manual');

    // Intrare mutată înapoi (manual sau prin schimbarea `SET_2021`): migrarea o readuce.
    $pdo->prepare('UPDATE meniu SET sectiune_id = :s, parent_id = NULL WHERE legacy_id = 3622')->execute(['s' => $sid20]);
    $r4 = migreaza($ctx, false);
    $nou4 = $m->gasesteDupaLegacy(3622);
    ok('intrare mutată înapoi => revine în 2021-2027, fără excepție', (int) $nou4['sectiune_id'] === $sid21 && (int) $nou4['parent_id'] === (int) $m->gasesteDupaLegacy(9001)['id'] && $r4['mutat'] >= 1);

    // Atașament de galerie fără fișier => `imagini_lipsa`. Instantaneul galeriei
    // se ia ÎNAINTE de corupere: `migreaza()` reface setul de imagini al galeriei
    // (DELETE + re-INSERT) pe baza legacy_url-ului corupt, deci restaurarea doar a
    // `fisiere.legacy_url` NU aduce înapoi rândul din `galerie_imagini` — pe o
    // galerie REALĂ asta ar lăsa o imagine permanent lipsă după test.
    $gid = (int) $gal[0]['id'];
    $galSnapshot = $ctx['galerie']->imagini($gid);
    $primaImagine = $galSnapshot[0];
    $fid = (int) $primaImagine['fisier_id'];
    $legacyUrlVechi[$fid] = (string) $ctx['fisiere']->gaseste($fid)['legacy_url'];
    $pdo->prepare('UPDATE fisiere SET legacy_url = :l WHERE id = :id')->execute(['l' => $legacyUrlVechi[$fid] . '.lipsa-test', 'id' => $fid]);
    $r5 = migreaza($ctx, false);
    ok('atașament fără fișier => imagini_lipsa = 1', $r5['imagini_lipsa'] === 1 && count($ctx['galerie']->imagini($gid)) === 39);
} finally {
    foreach ($slugVechi as $id => $sl) {
        $pdo->prepare('UPDATE meniu SET slug = :sl WHERE id = :id')->execute(['sl' => $sl, 'id' => $id]);
    }
    foreach ($legacyUrlVechi as $id => $l) {
        $pdo->prepare('UPDATE fisiere SET legacy_url = :l WHERE id = :id')->execute(['l' => $l, 'id' => $id]);
    }
    // Dacă galeria testată e o intrare REALĂ (era deja în instantaneul de dinaintea
    // testului) și numărul de imagini nu mai corespunde, refacem exact setul original
    // ÎNAINTE de a șterge intrările noi de mai jos (galeria însăși poate fi reală).
    if (isset($gid, $galSnapshot) && isset($meniuVechi[$gid]) && count($ctx['galerie']->imagini($gid)) !== count($galSnapshot)) {
        $ctx['galerie']->seteaza($gid, array_map(
            static fn(array $r): array => ['fisier_id' => (int) $r['fisier_id'], 'legenda' => (string) $r['legenda']],
            $galSnapshot
        ));
    }
    // Doar id-urile apărute după instantaneu, de la cel mai nou spre cel mai vechi
    // (copiii sunt creați după părinți, deci nu rămân orfani).
    $st = $pdo->prepare('DELETE FROM meniu WHERE id = :id');
    foreach (array_map('intval', $pdo->query('SELECT id FROM meniu ORDER BY id DESC')->fetchAll(PDO::FETCH_COLUMN)) as $id) {
        if (!isset($meniuVechi[$id])) { $st->execute(['id' => $id]); }
    }
    foreach ($fisiereTest as $id) { $pdo->exec("DELETE FROM fisiere WHERE id = $id"); }
    $st = $pdo->prepare('UPDATE sectiuni SET acasa_html = :h WHERE slug = :s');
    foreach ($acasaVechi as $slug => $h) { $st->execute(['h' => $h, 's' => $slug]); }
}
final_test();
