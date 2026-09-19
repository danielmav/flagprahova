<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Fisiere\Miniatura;

$dir = settings()['upload']['dir'];
$m = bin2hex(random_bytes(3));
$rel = "2026/09/garda-$m.png";
@mkdir("$dir/2026/09", 0775, true);
// PNG „bombă": 8000x6000 = 48 MP, dar fișierul e mic (imagine uniformă, compresie maximă).
$im = imagecreatetruecolor(8000, 6000); imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255)); imagepng($im, "$dir/$rel", 9); imagedestroy($im);
try {
    ok('MAX_PIXELI = 40 MP', Miniatura::MAX_PIXELI === 40_000_000);
    $t0 = microtime(true);
    $rez = (new Miniatura($dir))->asigura($rel, 480);
    ok('sursa de 48 MP => null, fără decodare', $rez === null);
    ok('  a răspuns repede (< 1 s, deci n-a decodat)', microtime(true) - $t0 < 1.0);
    ok('  nu a lăsat miniatură pe disc', !is_file("$dir/mini/480/2026/09/garda-$m.webp"));
    $r = cerere('GET', "/fisiere/mini/480/2026/09/garda-$m.webp");
    ok('  HTTP => 404', $r->getStatusCode() === 404);
} finally {
    @unlink("$dir/$rel");
    @unlink("$dir/mini/480/2026/09/garda-$m.webp");
}
final_test();
