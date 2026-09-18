<?php
// Descarcă DM Sans (400/500/700) + DM Serif Display (400), subseturile latin și latin-ext,
// în assets/fonts/ și scrie assets/css/fonts.css cu @font-face + unicode-range.
// Rulat o singură dată în dev; rezultatul (fonturile + fonts.css) se commit-uie.
declare(strict_types=1);
$root = dirname(__DIR__);
$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
$url = 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=DM+Serif+Display&display=swap';
$ctx = stream_context_create(['http' => ['header' => "User-Agent: $ua\r\n"]]);
$css = file_get_contents($url, false, $ctx);
if ($css === false) { fwrite(STDERR, "Nu pot descărca CSS-ul\n"); exit(1); }
@mkdir("$root/assets/fonts", 0775, true);
$out = "/* Fonturi self-hosted (descărcate cu scripts/descarca_fonturi.php). Licență: SIL OFL 1.1 */\n";
// Google servește pentru DM Sans același woff2 variabil la toate greutățile: scriem fișierul
// o singură dată (indexat pe conținut) și îl referim din mai multe @font-face.
$dupaContinut = [];
preg_match_all('#/\*\s*(latin|latin-ext)\s*\*/\s*@font-face\s*\{(.*?)\}#s', $css, $m, PREG_SET_ORDER);
foreach ($m as [$tot, $subset, $corp]) {
    preg_match("/font-family:\s*'([^']+)'/", $corp, $fam);
    preg_match('/font-weight:\s*(\d+)/', $corp, $w);
    preg_match('/url\(([^)]+)\)/', $corp, $u);
    preg_match('/unicode-range:\s*([^;]+);/', $corp, $ur);
    $bin = file_get_contents($u[1], false, $ctx);
    if ($bin === false) { fwrite(STDERR, "Nu pot descărca {$u[1]}\n"); exit(1); }
    $cheie = md5($bin);
    $nume = $dupaContinut[$cheie] ?? null;
    if ($nume === null) {
        $nume = strtolower(str_replace(' ', '-', $fam[1])) . "-{$w[1]}-$subset.woff2";
        file_put_contents("$root/assets/fonts/$nume", $bin);
        $dupaContinut[$cheie] = $nume;
        echo "ok $nume\n";
    } else {
        echo "refolosit $nume pentru {$fam[1]} {$w[1]} $subset\n";
    }
    $out .= "@font-face{font-family:'{$fam[1]}';font-style:normal;font-weight:{$w[1]};font-display:swap;src:url(../fonts/$nume) format('woff2');unicode-range:{$ur[1]};}\n";
}
file_put_contents("$root/assets/css/fonts.css", $out);
echo "scris assets/css/fonts.css\n";
