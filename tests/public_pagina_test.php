<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$s = $pdo->query("SELECT * FROM sectiuni WHERE slug='2014-2020'")->fetch();
$m = strtolower('Pag' . bin2hex(random_bytes(3)));
$ids = []; $fids = [];
try {
    $insF = $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES (:n, :c, :m, :s)');
    $insF->execute(['n' => 'Ghid.pdf', 'c' => "2026/09/ghid-$m.pdf", 'm' => 'application/pdf', 's' => 1536000]); $fids['pdf'] = (int) $pdo->lastInsertId();
    $insF->execute(['n' => 'Poza.jpg', 'c' => "2026/09/poza-$m.jpg", 'm' => 'image/jpeg', 's' => 90000]); $fids['jpg'] = (int) $pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, continut_html, fisier_id, url, sablon, vizibil) VALUES (:s, :p, :o, :t, :sl, :tip, :h, :f, :u, :sab, :v)');
    $add = function (?int $p, string $t, string $tip, array $x = []) use ($ins, $pdo, $s, &$ids): int {
        $ins->execute(['s' => $s['id'], 'p' => $p, 'o' => $x['o'] ?? 999, 't' => $t, 'sl' => slugify($t), 'tip' => $tip, 'h' => $x['h'] ?? null, 'f' => $x['f'] ?? null, 'u' => $x['u'] ?? null, 'sab' => $x['sab'] ?? 'standard', 'v' => $x['v'] ?? 1]);
        $id = (int) $pdo->lastInsertId(); $ids[] = $id; return $id;
    };
    $dosar  = $add(null, "Arhiva $m", 'dosar');
    $sub    = $add($dosar, "Sesiunea $m", 'dosar', ['o' => 1]);
    $doc    = $add($sub, "Ghid $m", 'document', ['f' => $fids['pdf']]);
    $docGol = $add($sub, "Fara fisier $m", 'document');
    $link   = $add($dosar, "Extern $m", 'link', ['u' => 'https://example.org/p', 'o' => 2]);
    $pag    = $add($dosar, "Pagina $m", 'pagina', ['h' => "<h2>Sub $m</h2><p>Text <strong>bold</strong></p>", 'o' => 0]);
    $gal    = $add($pag, "Galerie $m", 'galerie');
    $pdo->exec("INSERT INTO galerie_imagini (meniu_id, fisier_id, ordine, legenda) VALUES ($gal, {$fids['jpg']}, 0, 'Legenda $m')");
    $ascuns = $add($dosar, "Ascuns $m", 'pagina', ['v' => 0, 'h' => '<p>secret</p>']);
    $subAsc = $add($ascuns, "Copil ascuns $m", 'pagina', ['h' => '<p>secret2</p>']);
    $contact = $add(null, "Contact $m", 'pagina', ['sab' => 'contact', 'h' => "<p>Adresa $m</p>"]);

    $r = cerere('GET', "/2014-2020/pagina-$m"); $c = corp($r);
    ok('pagina => 200', $r->getStatusCode() === 200);
    ok('  h1 unic + conținut raw', substr_count($c, '<h1') === 1 && str_contains($c, "<h2>Sub $m</h2>") && str_contains($c, '<strong>bold</strong>'));
    ok('  title/canonical', str_contains($c, "<title>Pagina $m") && str_contains($c, "href=\"http://flagprahova.test/2014-2020/pagina-$m\""));
    // JSON-LD: se parsează, nu se caută pe substring (json_encode nu pune spațiu după `:`).
    $ld = null;
    if (preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $c, $mm)) { $ld = json_decode(trim($mm[1]), true); }
    ok('  breadcrumb cu părintele', str_contains($c, 'fp-breadcrumb') && str_contains($c, "Arhiva $m"));
    ok('  JSON-LD BreadcrumbList parsează, cu părintele în listă', is_array($ld)
        && ($ld['@type'] ?? null) === 'BreadcrumbList'
        && str_contains(json_encode($ld['itemListElement'] ?? [], JSON_UNESCAPED_UNICODE), "Arhiva $m")
        && str_contains(json_encode($ld['itemListElement'] ?? [], JSON_UNESCAPED_UNICODE), "Pagina $m"));
    ok('  galeria-copil sub conținut, cu miniatură + lightbox', str_contains($c, "Galerie $m") && str_contains($c, "/fisiere/mini/480/2026/09/poza-$m.webp") && str_contains($c, "data-full=\"/fisiere/mini/1600/2026/09/poza-$m.webp\"") && str_contains($c, 'loading="lazy"'));
    ok('  sidebar cu ramura + contact rapid', str_contains($c, 'fp-sidebar') && str_contains($c, "Sesiunea $m") && str_contains($c, 'fp-contact-rapid'));
    ok('  meniul marchează nodul curent', preg_match('#<a class="[^"]*is-activ[^"]*" href="/2014-2020/pagina-' . $m . '"#', $c) === 1);

    $r = cerere('GET', "/2014-2020/arhiva-$m"); $c = corp($r);
    ok('dosar => 200 cu lista copiilor grupată', $r->getStatusCode() === 200 && str_contains($c, "Sesiunea $m") && str_contains($c, "Ghid $m") && str_contains($c, '1,5 MB') && str_contains($c, 'data-ext="pdf"'));
    ok('  documentul fără fișier apare fără link', str_contains($c, "Fara fisier $m") && !str_contains($c, "href=\"\""));
    ok('  linkul extern', str_contains($c, 'href="https://example.org/p"'));
    ok('  invizibilul lipsește', !str_contains($c, "Ascuns $m"));

    $r = cerere('GET', "/2014-2020/galerie-$m"); $c = corp($r);
    ok('galerie => 200, grilă + og:image', $r->getStatusCode() === 200 && str_contains($c, 'class="fp-galerie"') && str_contains($c, "Legenda $m") && str_contains($c, "og:image\" content=\"http://flagprahova.test/fisiere/mini/1600/2026/09/poza-$m.webp"));

    $r = cerere('GET', "/2014-2020/ghid-$m");
    ok('document => 302 la fișier', $r->getStatusCode() === 302 && $r->getHeaderLine('Location') === "/fisiere/2026/09/ghid-$m.pdf");
    $r = cerere('GET', "/2014-2020/fara-fisier-$m");
    ok('document fără fișier => 404', $r->getStatusCode() === 404);
    $r = cerere('GET', "/2014-2020/extern-$m");
    ok('link => 302 extern', $r->getStatusCode() === 302 && $r->getHeaderLine('Location') === 'https://example.org/p');
    $r = cerere('GET', "/2014-2020/ascuns-$m");
    ok('pagina invizibilă => 404', $r->getStatusCode() === 404 && !str_contains(corp($r), 'secret'));
    $r = cerere('GET', "/2014-2020/copil-ascuns-$m");
    ok('copil vizibil al unui invizibil => 404', $r->getStatusCode() === 404 && !str_contains(corp($r), 'secret2'));
    $r = cerere('GET', "/2021-2027/pagina-$m");
    ok('slug din altă secțiune => 404', $r->getStatusCode() === 404);
    $r = cerere('GET', "/2014-2020/contact-$m"); $c = corp($r);
    ok('șablon contact => 200 cu conținut + formular', $r->getStatusCode() === 200 && str_contains($c, "Adresa $m") && str_contains($c, 'class="fp-form'));
} finally {
    if ($ids) { $pdo->exec('DELETE FROM meniu WHERE id IN (' . implode(',', array_reverse($ids)) . ')'); }
    if ($fids) { $pdo->exec('DELETE FROM fisiere WHERE id IN (' . implode(',', $fids) . ')'); }
}
final_test();
