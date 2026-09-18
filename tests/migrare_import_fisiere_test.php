<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require dirname(__DIR__) . '/database/import_fisiere.php';

$tmp = sys_get_temp_dir() . '/fp-imp-' . bin2hex(random_bytes(3));
mkdir($tmp);
$zipCale = "$tmp/test.zip";
$z = new ZipArchive();
$z->open($zipCale, ZipArchive::CREATE);
$pdf = file_get_contents(__DIR__ . '/fixtures/mic.pdf');
$png = file_get_contents(__DIR__ . '/fixtures/mic.png');
$z->addFromString('wp-content/uploads/1999/01/Anunț angajare – Manager.pdf', $pdf);
$z->addFromString('wp-content/uploads/1999/01/poza.png', $png);
$z->addFromString('wp-content/uploads/1999/01/poza-300x200.png', $png);          // miniatură (bază prezentă)
$z->addFromString('wp-content/uploads/1999/02/altul.pdf', $pdf . 'x');
$z->addFromString('wp-content/uploads/1999/02/altul.PDF', $pdf . 'y');           // coliziune de nume, conținut diferit
$z->addFromString('wp-content/uploads/js_composer/x.css', 'a{}');                // plugin
$z->addFromString('wp-content/uploads/1999/02/script.php', '<?php echo 1;');      // extensie nepermisă
$z->addFromString('wp-content/plugins/x/y.php', '<?php');                          // în afara uploads
$z->addFromString('wp-content/uploads/1999/03/poza.png', $png);
$z->addFromString('wp-content/uploads/1999/03/poza-300x200.png', $png);          // miniatură reală (bază prezentă)
$z->addFromString('wp-content/uploads/1999/03/cropped-646x404.jpg', $png);       // arată ca miniatură, dar e atașament original (bază absentă)
$z->addFromString('wp-content/uploads/1999/05/Copie.pdf', $pdf);
$z->addFromString('wp-content/uploads/1999/05/copie.pdf', $pdf);                 // conținut identic, nume WP diferit => NU reuse, fișier separat
$z->close();

$repo = new App\Fisiere\Repository(new App\Database(settings()['db']));
$dest = "$tmp/fisiere";
try {
    $z = new ZipArchive(); $z->open($zipCale);
    $r = importa_fisiere($z, $dest, $repo);
    $z->close();
    ok('importate 8', $r['importate'] === 8);
    ok('miniaturi sărite 2', $r['sarite_miniaturi'] === 2);
    ok('extensie sărită 1', $r['sarite_extensie'] === 1);
    ok('fișier normalizat pe disc', is_file("$dest/1999/01/anunt-angajare-manager.pdf"));
    $f = $repo->gasesteDupaCale('1999/01/anunt-angajare-manager.pdf');
    ok('  înregistrat cu legacy_url original', $f && $f['legacy_url'] === '1999/01/Anunț angajare – Manager.pdf' && $f['nume_afisat'] === 'Anunț angajare – Manager.pdf' && $f['mime'] === 'application/pdf');
    ok('coliziune: al doilea primește -2', $repo->gasesteDupaCale('1999/02/altul-2.pdf') !== null && $repo->gasesteDupaCale('1999/02/altul.pdf') !== null);

    // IMPORTANT 1: cropped-646x404.jpg arată ca o miniatură (sufix -NNNxNNN), dar nu are un
    // original "cropped.jpg" alături în arhivă => e chiar atașamentul original, se importă.
    ok('cropped-646x404.jpg importat ca original (bază absentă)', is_file("$dest/1999/03/cropped-646x404.jpg"));
    $cr = $repo->gasesteDupaCale('1999/03/cropped-646x404.jpg');
    ok('  înregistrat, legacy_url corect', $cr !== null && $cr['legacy_url'] === '1999/03/cropped-646x404.jpg');
    // poza-300x200.png din 1999/03 are bază reală (poza.png) => rămâne miniatură (sărită)
    ok('poza-300x200.png (1999/03) tot miniatură (bază prezentă)', $repo->gasesteDupaCale('1999/03/poza-300x200.png') === null);

    // IMPORTANT 2: fără reuse pe md5 — două atașamente WP cu nume diferite dar conținut identic
    // primesc DOUĂ rânduri, DOUĂ fișiere separate pe disc, legacy_url-uri distincte și intacte.
    $c1 = $repo->gasesteDupaCale('1999/05/copie.pdf');
    $c2 = $repo->gasesteDupaCale('1999/05/copie-2.pdf');
    ok('fără reuse md5: două rânduri distincte', $c1 !== null && $c2 !== null);
    ok('  fișiere separate pe disc', is_file("$dest/1999/05/copie.pdf") && is_file("$dest/1999/05/copie-2.pdf"));
    ok('  legacy_url distincte (Copie.pdf vs copie.pdf)', $c1['legacy_url'] !== $c2['legacy_url']
        && in_array($c1['legacy_url'], ['1999/05/Copie.pdf', '1999/05/copie.pdf'], true)
        && in_array($c2['legacy_url'], ['1999/05/Copie.pdf', '1999/05/copie.pdf'], true));

    // idempotent
    $z = new ZipArchive(); $z->open($zipCale);
    $r2 = importa_fisiere($z, $dest, $repo);
    $z->close();
    ok('re-rulare: 0 importate, 8 existente', $r2['importate'] === 0 && $r2['existente'] === 8);
    ok('re-rulare: nu apar rânduri noi', count(pdo()->query("SELECT id FROM fisiere WHERE cale LIKE '1999/0%'")->fetchAll()) === 8);
    $c1b = $repo->gasesteDupaCale('1999/05/copie.pdf');
    $c2b = $repo->gasesteDupaCale('1999/05/copie-2.pdf');
    ok('re-rulare: legacy_url neschimbate (fără suprascriere md5)', $c1b['legacy_url'] === $c1['legacy_url'] && $c2b['legacy_url'] === $c2['legacy_url']);

    // Reset + reimport: rândul din `fisiere` lipsește (ca după `reset_continut.php
    // --da`), dar fișierele sunt deja pe disc, byte-identice cu arhiva => doar
    // re-înregistrare, FĂRĂ rescriere și FĂRĂ fișier „-3” nou.
    $mtimeTrecut = time() - 3600;
    touch("$dest/1999/05/copie.pdf", $mtimeTrecut);
    touch("$dest/1999/05/copie-2.pdf", $mtimeTrecut);
    clearstatcache();
    pdo()->exec("DELETE FROM fisiere WHERE id IN ({$c1['id']}, {$c2['id']})");
    $z = new ZipArchive(); $z->open($zipCale);
    $r5 = importa_fisiere($z, $dest, $repo, '1999/05');
    $z->close();
    ok('reset+reimport: 2 re-inregistrate (nu 0, nu noi scrise)', $r5['importate'] === 2);
    ok('  fara fisier -3 nou creat', !is_file("$dest/1999/05/copie-3.pdf"));
    clearstatcache();
    ok('  mtime neschimbat pe ambele (fara rescriere)', filemtime("$dest/1999/05/copie.pdf") === $mtimeTrecut && filemtime("$dest/1999/05/copie-2.pdf") === $mtimeTrecut);
    $c1r = $repo->gasesteDupaCale('1999/05/copie.pdf');
    $c2r = $repo->gasesteDupaCale('1999/05/copie-2.pdf');
    ok('  aceleași căi refăcute, legacy_url pe fiecare', $c1r !== null && $c2r !== null
        && in_array($c1r['legacy_url'], ['1999/05/Copie.pdf', '1999/05/copie.pdf'], true)
        && in_array($c2r['legacy_url'], ['1999/05/Copie.pdf', '1999/05/copie.pdf'], true));

    // Conținut DIFERIT, rând absent: numele e „ocupat” pe disc de un fișier care NU
    // mai e byte-identic (nu e propriul fișier de dinainte de reset) => tot „-2”,
    // niciodată reuse/suprascriere silențioasă a unui fișier străin.
    $zCiocnire = "$tmp/ciocnire.zip";
    $zc = new ZipArchive(); $zc->open($zCiocnire, ZipArchive::CREATE);
    $zc->addFromString('wp-content/uploads/1999/09/unic.pdf', $pdf);
    $zc->close();
    $destC = "$tmp/fc";
    $zc = new ZipArchive(); $zc->open($zCiocnire);
    $rc1 = importa_fisiere($zc, $destC, $repo);
    $zc->close();
    ok('ciocnire: prima rulare inregistreaza unic.pdf', $rc1['importate'] === 1 && is_file("$destC/1999/09/unic.pdf"));
    $rândUnic = $repo->gasesteDupaCale('1999/09/unic.pdf');
    pdo()->exec('DELETE FROM fisiere WHERE id = ' . (int) $rândUnic['id']);
    file_put_contents("$destC/1999/09/unic.pdf", 'continut cu totul altul, nu din arhiva'); // simulează un fișier STRĂIN cu același nume
    $zc = new ZipArchive(); $zc->open($zCiocnire);
    $rc2 = importa_fisiere($zc, $destC, $repo);
    $zc->close();
    ok('ciocnire: conținut diferit => tot -2 (nu suprascrie fișierul străin)', $rc2['importate'] === 1
        && is_file("$destC/1999/09/unic-2.pdf") && file_get_contents("$destC/1999/09/unic-2.pdf") === $pdf
        && file_get_contents("$destC/1999/09/unic.pdf") === 'continut cu totul altul, nu din arhiva');
    $rândUnic2 = $repo->gasesteDupaCale('1999/09/unic-2.pdf');
    ok('  rândul nou se leagă de fișierul -2, nu de cel străin', $rândUnic2 !== null && $rândUnic2['legacy_url'] === '1999/09/unic.pdf');

    // filtru --doar
    $repo3 = $repo; $dest3 = "$tmp/f3";
    $z = new ZipArchive(); $z->open($zipCale);
    $r3 = importa_fisiere($z, $dest3, $repo3, '1999/02');
    $z->close();
    ok('--doar limitează la 1999/02 (existente, nu importate)', $r3['importate'] + $r3['existente'] === 2);

    // --verbose: numele miniaturilor sărite apar în raport
    $z = new ZipArchive(); $z->open($zipCale);
    $r4 = importa_fisiere($z, "$tmp/f4", $repo, null, 0, true);
    $z->close();
    ok('--verbose: listă miniaturi sărite', isset($r4['miniaturi_sarite']) && count($r4['miniaturi_sarite']) === 2
        && in_array('1999/01/poza-300x200.png', $r4['miniaturi_sarite'], true)
        && in_array('1999/03/poza-300x200.png', $r4['miniaturi_sarite'], true));
} finally {
    pdo()->exec("DELETE FROM fisiere WHERE cale LIKE '1999/0%'");
    foreach (glob("$tmp/*/*/*/*") ?: [] as $f) { @unlink($f); }
}
final_test();
