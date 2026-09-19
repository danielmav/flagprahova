<?php
/**
 * Generează imaginile de fundal ale hero-urilor (WebP, două lățimi) din fotografiile
 * originale (Unsplash, `materiale/imagini/`, gitignored). Se rulează o singură dată,
 * rezultatul e versionat în `assets/img/hero/`.
 *
 *   php scripts/genereaza_hero.php
 */
declare(strict_types=1);

$root  = dirname(__DIR__);
$sursa = $root . '/materiale/imagini';
$dest  = $root . '/assets/img/hero';
// nume-țintă => fișier sursă. Decupaj 21:9 (landing) / 3:1 (secțiuni), centrat.
$harta = [
    'landing-1' => ['brandon-enPHTN3OPRw-unsplash.jpg',          21 / 9],
    'landing-2' => ['milada-vigerova-324UZUY4jdE-unsplash.jpg',  21 / 9],
    'landing-3' => ['sara-kurfess-E8AabnQlTlQ-unsplash.jpg',     21 / 9],
    '2021-2027' => ['david-trinks--B0PDKjZ63o-unsplash.jpg',     3 / 1],
    '2014-2020' => ['oleksandr-sushko-lb2zuNL0WXw-unsplash.jpg', 3 / 1],
];
$latimi = [1920 => 74, 960 => 72]; // lățime => calitate WebP

if (!is_dir($dest)) { mkdir($dest, 0775, true); }
foreach ($harta as $nume => [$fisier, $raport]) {
    $cale = "$sursa/$fisier";
    if (!is_file($cale)) { fwrite(STDERR, "lipsește: $cale\n"); exit(1); }
    $img = imagecreatefromjpeg($cale);
    $w = imagesx($img); $h = imagesy($img);
    // Decupaj centrat la raportul cerut.
    $cw = $w; $ch = (int) round($w / $raport);
    if ($ch > $h) { $ch = $h; $cw = (int) round($h * $raport); }
    $x = (int) (($w - $cw) / 2); $y = (int) (($h - $ch) / 2);
    foreach ($latimi as $lat => $q) {
        $out = imagecreatetruecolor($lat, (int) round($lat / $raport));
        imagecopyresampled($out, $img, 0, 0, $x, $y, $lat, (int) round($lat / $raport), $cw, $ch);
        $tinta = "$dest/$nume-$lat.webp";
        imagewebp($out, $tinta, $q);
        imagedestroy($out);
        printf("%-22s %4dx%-4d %6.0f KB\n", basename($tinta), $lat, (int) round($lat / $raport), filesize($tinta) / 1024);
    }
    imagedestroy($img);
}
