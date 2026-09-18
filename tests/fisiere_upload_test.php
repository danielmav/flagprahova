<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UploadedFileFactory;

$dir = sys_get_temp_dir() . '/fp-upload-' . bin2hex(random_bytes(3));
mkdir($dir);
$up = new App\Fisiere\Upload($dir, 1024 * 1024);
// Notă: Slim\Psr7\UploadedFile::moveTo() face rename() pe fișierul sursă când nu rulează
// sub SAPI (cazul testelor CLI), deci „consumă" fișierul original. Copiem fixture-ul
// într-un temporar de unică folosință înainte de fiecare upload simulat, ca fixture-ele
// comise (mic.pdf ș.a.) să rămână intacte pentru rulările următoare.
$uf = function (string $cale, string $nume, int $err = UPLOAD_ERR_OK) {
    $tmp = sys_get_temp_dir() . '/fp-upload-src-' . bin2hex(random_bytes(4)) . '-' . basename($cale);
    copy($cale, $tmp);
    $s = (new StreamFactory())->createStreamFromFile($tmp);
    return (new UploadedFileFactory())->createUploadedFile($s, filesize($tmp), $err, $nume);
};

ok('numeSigur normalizează', App\Fisiere\Upload::numeSigur('Anunț angajare – Manager.PDF') === 'anunt-angajare-manager.pdf');
ok('numeSigur taie path traversal', !str_contains(App\Fisiere\Upload::numeSigur('../../x.pdf'), '/'));

$r = $up->salveaza($uf(__DIR__ . '/fixtures/mic.pdf', 'Comunicat SDL.pdf'));
ok('pdf acceptat', $r['motiv'] === null && $r['cale'] !== null);
ok('  cale AAAA/LL/nume', preg_match('#^\d{4}/\d{2}/comunicat-sdl\.pdf$#', (string) $r['cale']) === 1);
ok('  mime application/pdf', $r['mime'] === 'application/pdf');
ok('  nume_afisat păstrat', $r['nume_afisat'] === 'Comunicat SDL.pdf');
ok('  fișierul există pe disc', is_file($dir . '/' . $r['cale']));
$r2 = $up->salveaza($uf(__DIR__ . '/fixtures/mic.pdf', 'Comunicat SDL.pdf'));
ok('al doilea cu același nume primește -2', str_ends_with((string) $r2['cale'], 'comunicat-sdl-2.pdf'));

$r = $up->salveaza($uf(__DIR__ . '/fixtures/rau.php.pdf', 'rau.php.pdf'));
ok('php deghizat în pdf => tip_nepermis (MIME din conținut)', $r['motiv'] === 'tip_nepermis');
$r = $up->salveaza($uf(__DIR__ . '/fixtures/mic.png', 'poza.exe'));
ok('png cu extensie exe => salvat ca .png', $r['motiv'] === null && str_ends_with((string) $r['cale'], '.png'));
ok('esteImagine', App\Fisiere\Upload::esteImagine('image/png') && !App\Fisiere\Upload::esteImagine('application/pdf'));
$r = $up->salveaza($uf(__DIR__ . '/fixtures/mic.pdf', 'x.pdf', UPLOAD_ERR_NO_FILE));
ok('fără fișier => gol', $r['motiv'] === 'gol');
$mic = new App\Fisiere\Upload($dir, 10);
ok('peste limită => prea_mare', $mic->salveaza($uf(__DIR__ . '/fixtures/mic.pdf', 'x.pdf'))['motiv'] === 'prea_mare');

// Repository
$repo = new App\Fisiere\Repository(new App\Database(settings()['db']));
$cale = date('Y/m') . '/test-' . bin2hex(random_bytes(3)) . '.pdf';
try {
    $id = $repo->inregistreaza(['nume_afisat' => 'Test.pdf', 'cale' => $cale, 'mime' => 'application/pdf', 'marime' => 12, 'incarcat_de' => null, 'legacy_url' => null]);
    ok('inregistreaza => id', $id > 0);
    ok('inregistreaza pe aceeași cale întoarce același id', $repo->inregistreaza(['nume_afisat' => 'Test2.pdf', 'cale' => $cale, 'mime' => 'application/pdf', 'marime' => 12]) === $id);
    ok('  și actualizează numele', $repo->gaseste($id)['nume_afisat'] === 'Test2.pdf');
    ok('gasesteDupaCale', ($repo->gasesteDupaCale($cale)['id'] ?? 0) == $id);
    $l = $repo->lista(date('Y'), date('m'), 'test-');
    ok('lista filtrează pe an/lună/căutare', count(array_filter($l, fn($x) => (int) $x['id'] === $id)) === 1);
    ok('lista doarImagini exclude pdf', array_filter($repo->lista(null, null, '', true), fn($x) => (int) $x['id'] === $id) === []);
    $aniLuni = array_values(array_filter($repo->aniLuni(), fn($a) => $a['an'] === date('Y')));
    ok('aniLuni conține luna curentă', $aniLuni !== [] && in_array(date('m'), $aniLuni[0]['luni'], true));
    $repo->redenumeste($id, 'Nou.pdf');
    ok('redenumeste', $repo->gaseste($id)['nume_afisat'] === 'Nou.pdf');
    ok('sterge (fără fișier pe disc) => true', $repo->sterge($id, $dir) === true && $repo->gaseste($id) === null);
} finally {
    pdo()->exec('DELETE FROM fisiere WHERE cale = ' . pdo()->quote($cale));
    array_map('unlink', glob($dir . '/*/*/*') ?: []);
}
final_test();
