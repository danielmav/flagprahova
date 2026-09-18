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
$z->addFromString('wp-content/uploads/1999/01/poza-300x200.png', $png);          // miniatură
$z->addFromString('wp-content/uploads/1999/02/altul.pdf', $pdf . 'x');
$z->addFromString('wp-content/uploads/1999/02/altul.PDF', $pdf . 'y');           // coliziune de nume, conținut diferit
$z->addFromString('wp-content/uploads/js_composer/x.css', 'a{}');                // plugin
$z->addFromString('wp-content/uploads/1999/02/script.php', '<?php echo 1;');      // extensie nepermisă
$z->addFromString('wp-content/plugins/x/y.php', '<?php');                          // în afara uploads
$z->close();

$repo = new App\Fisiere\Repository(new App\Database(settings()['db']));
$dest = "$tmp/fisiere";
try {
    $z = new ZipArchive(); $z->open($zipCale);
    $r = importa_fisiere($z, $dest, $repo);
    $z->close();
    ok('importate 4', $r['importate'] === 4);
    ok('miniaturi sărite 1', $r['sarite_miniaturi'] === 1);
    ok('extensie sărită 1', $r['sarite_extensie'] === 1);
    ok('fișier normalizat pe disc', is_file("$dest/1999/01/anunt-angajare-manager.pdf"));
    $f = $repo->gasesteDupaCale('1999/01/anunt-angajare-manager.pdf');
    ok('  înregistrat cu legacy_url original', $f && $f['legacy_url'] === '1999/01/Anunț angajare – Manager.pdf' && $f['nume_afisat'] === 'Anunț angajare – Manager.pdf' && $f['mime'] === 'application/pdf');
    ok('coliziune: al doilea primește -2', $repo->gasesteDupaCale('1999/02/altul-2.pdf') !== null && $repo->gasesteDupaCale('1999/02/altul.pdf') !== null);
    // idempotent
    $z = new ZipArchive(); $z->open($zipCale);
    $r2 = importa_fisiere($z, $dest, $repo);
    $z->close();
    ok('re-rulare: 0 importate, 4 existente', $r2['importate'] === 0 && $r2['existente'] === 4);
    ok('re-rulare: nu apar rânduri noi', count(pdo()->query("SELECT id FROM fisiere WHERE cale LIKE '1999/0%'")->fetchAll()) === 4);
    // filtru --doar
    $repo3 = $repo; $dest3 = "$tmp/f3";
    $z = new ZipArchive(); $z->open($zipCale);
    $r3 = importa_fisiere($z, $dest3, $repo3, '1999/02');
    $z->close();
    ok('--doar limitează la 1999/02 (existente, nu importate)', $r3['importate'] + $r3['existente'] === 2);
} finally {
    pdo()->exec("DELETE FROM fisiere WHERE cale LIKE '1999/0%'");
    foreach (glob("$tmp/*/*/*/*") ?: [] as $f) { @unlink($f); }
}
final_test();
