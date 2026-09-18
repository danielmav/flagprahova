<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Fisiere\Miniatura;

$dir = settings()['upload']['dir'];
$m = bin2hex(random_bytes(3));
$rel = "2026/09/mini-test-$m.png";
@mkdir("$dir/2026/09", 0775, true);
// sursă 800x600 generată cu GD (nu depinde de fixtures)
$im = imagecreatetruecolor(800, 600); imagefill($im, 0, 0, imagecolorallocate($im, 30, 111, 197)); imagepng($im, "$dir/$rel"); imagedestroy($im);
try {
    ok('caleMini ok', Miniatura::caleMini($rel, 480) === "mini/480/2026/09/mini-test-$m.webp");
    ok('caleMini lățime nepermisă => null', Miniatura::caleMini($rel, 999) === null);
    ok('caleMini cale cu ../ => null', Miniatura::caleMini('2026/09/../../x.png', 480) === null);
    ok('caleMini pdf => null', Miniatura::caleMini('2026/09/x.pdf', 480) === null);

    $r = cerere('GET', "/fisiere/mini/480/2026/09/mini-test-$m.webp");
    ok('GET miniatură => 200 image/webp', $r->getStatusCode() === 200 && $r->getHeaderLine('Content-Type') === 'image/webp');
    ok('  cache-control lung', str_contains($r->getHeaderLine('Cache-Control'), 'max-age=31536000'));
    $abs = "$dir/mini/480/2026/09/mini-test-$m.webp";
    ok('  fișierul e scris pe disc', is_file($abs));
    [$w, $h] = getimagesize($abs);
    ok('  redimensionat la 480x360', $w === 480 && $h === 360);
    $mt = filemtime($abs); sleep(1);
    $r = cerere('GET', "/fisiere/mini/480/2026/09/mini-test-$m.webp");
    ok('  a doua cerere nu regenerează', $r->getStatusCode() === 200 && filemtime($abs) === $mt);
    $r = cerere('GET', "/fisiere/mini/1600/2026/09/mini-test-$m.webp");
    [$w] = getimagesize("$dir/mini/1600/2026/09/mini-test-$m.webp");
    ok('1600 nu mărește peste original (800)', $r->getStatusCode() === 200 && $w === 800);
    $r = cerere('GET', "/fisiere/mini/480/2026/09/nu-exista-$m.webp");
    ok('sursă lipsă => 404', $r->getStatusCode() === 404);
    $r = cerere('GET', "/fisiere/mini/300/2026/09/mini-test-$m.webp");
    ok('lățime nepermisă => 404', $r->getStatusCode() === 404);
} finally {
    @unlink("$dir/$rel");
    @unlink("$dir/mini/480/2026/09/mini-test-$m.webp");
    @unlink("$dir/mini/1600/2026/09/mini-test-$m.webp");
    // Directoarele create de test: `@rmdir` reușește DOAR dacă au rămas goale,
    // deci nu atinge nimic dintr-un `fisiere/` real.
    foreach ([
        "$dir/2026/09", "$dir/2026",
        "$dir/mini/480/2026/09", "$dir/mini/480/2026", "$dir/mini/480",
        "$dir/mini/1600/2026/09", "$dir/mini/1600/2026", "$dir/mini/1600",
        "$dir/mini",
    ] as $d) {
        @rmdir($d);
    }
}
final_test();
