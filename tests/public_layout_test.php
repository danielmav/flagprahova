<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$s1 = $pdo->query("SELECT * FROM sectiuni WHERE slug='2021-2027'")->fetch();
$marca = 'Lay' . bin2hex(random_bytes(3));
$ids = []; $fid = null;
try {
    // arbore temporar pe 3 niveluri + un nod invizibil cu copil vizibil
    $ins = $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, fisier_id, url, vizibil) VALUES (:s, :p, :o, :t, :sl, :tip, :f, :u, :v)');
    $adauga = function (?int $p, string $t, string $tip, int $viz = 1, ?int $f = null, ?string $u = null) use ($ins, $pdo, $s1, &$ids): int {
        $ins->execute(['s' => $s1['id'], 'p' => $p, 'o' => 999, 't' => $t, 'sl' => slugify($t), 'tip' => $tip, 'f' => $f, 'u' => $u, 'v' => $viz]);
        $id = (int) $pdo->lastInsertId(); $ids[] = $id; return $id;
    };
    $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES ("Raport.pdf", :c, "application/pdf", 123456)')->execute(['c' => "2026/09/raport-$marca.pdf"]);
    $fid = (int) $pdo->lastInsertId();
    $n1 = $adauga(null, "Dosar $marca", 'dosar');
    $n2 = $adauga($n1, "Subdosar $marca", 'dosar');
    $n3 = $adauga($n2, "Raport $marca", 'document', 1, $fid);
    $n4 = $adauga($n1, "Extern $marca", 'link', 1, null, 'https://example.com/x');
    $ascuns = $adauga(null, "Ascuns $marca", 'dosar', 0);
    $orfan  = $adauga($ascuns, "Orfan $marca", 'dosar', 1);

    $r = cerere('GET', '/');
    $c = corp($r);
    ok('GET / => 200', $r->getStatusCode() === 200);
    ok('  ambele perioade', str_contains($c, 'FLAG Prahova 2021-2027') && str_contains($c, 'FLAG Prahova 2014-2020'));
    ok('  linkuri către secțiuni', str_contains($c, 'href="/2021-2027/"') && str_contains($c, 'href="/2014-2020/"'));
    ok('  canonical + OG + twitter', str_contains($c, '<link rel="canonical" href="http://flagprahova.test/">') && str_contains($c, 'property="og:title"') && str_contains($c, 'property="og:image" content="http://flagprahova.test/assets/img/og-default.png"') && str_contains($c, 'name="twitter:card"'));
    ok('  un singur h1', substr_count($c, '<h1') === 1);
    ok('  logo-uri + text cofinanțare', str_contains($c, 'assets/img/logo/eu-flag.png') && str_contains($c, 'Cofinanțat de Uniunea Europeană') && str_contains($c, 'assets/img/logo/flag-prahova.png'));
    // JSON-LD-ul trebuie să fie JSON VALID, nu doar să conțină șirul potrivit:
    // o valoare interpolată prin autoescape-ul HTML ar strica parsarea.
    $ld = preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $c, $m) === 1
        ? json_decode(trim($m[1]), true) : null;
    ok('  JSON-LD Organization parsează', is_array($ld) && ($ld['@type'] ?? null) === 'Organization'
        && ($ld['@context'] ?? null) === 'https://schema.org' && ($ld['url'] ?? null) === 'http://flagprahova.test/');
    ok('  CSS/JS locale, nimic extern', str_contains($c, '/assets/css/site.css') && str_contains($c, '/assets/js/site.js') && !str_contains($c, 'googleapis') && !str_contains($c, 'cdn.'));
    ok('  fără link către admin', !str_contains($c, '/admin'));

    $r = cerere('GET', '/2021-2027');
    ok('GET /2021-2027 (fără slash) => 301 la /2021-2027/', $r->getStatusCode() === 301 && $r->getHeaderLine('Location') === '/2021-2027/');

    $r = cerere('GET', '/2021-2027/');
    $c = corp($r);
    ok('GET /2021-2027/ => 200', $r->getStatusCode() === 200);
    ok('  meniul pe 3 niveluri (desktop)', str_contains($c, "Dosar $marca") && str_contains($c, "Subdosar $marca") && str_contains($c, "Raport $marca"));
    ok('  documentul linkează direct la fișier', str_contains($c, "href=\"/fisiere/2026/09/raport-$marca.pdf\""));
    ok('  linkul extern are noopener', preg_match('#<a[^>]+href="https://example\.com/x"[^>]+rel="noopener[^"]*"#', $c) === 1);
    ok('  invizibilul și orfanul lipsesc', !str_contains($c, "Ascuns $marca") && !str_contains($c, "Orfan $marca"));
    ok('  comutatorul duce la cealaltă perioadă', str_contains($c, 'href="/2014-2020/"'));
    ok('  sigla 2021-2027 în bandă', str_contains($c, 'assets/img/logo/2021-2027.png'));
    ok('  meniul mobil (offcanvas) există', str_contains($c, 'class="offcanvas') && str_contains($c, "<details"));

    $r = cerere('GET', '/2014-2020/');
    ok('GET /2014-2020/ nu arată sigla 2021-2027', $r->getStatusCode() === 200 && !str_contains(corp($r), 'assets/img/logo/2021-2027.png'));

    $r = cerere('GET', '/1999-2000/');
    ok('secțiune inexistentă => 404 cu layout + noindex', $r->getStatusCode() === 404 && str_contains(corp($r), 'fp-header') && str_contains(corp($r), 'noindex'));
    $r = cerere('GET', '/2021-2027/nu-exista-' . strtolower($marca));
    ok('slug inexistent => 404 cu meniul secțiunii', $r->getStatusCode() === 404 && str_contains(corp($r), "Dosar $marca"));
    $r = cerere('GET', '/wp-content/uploads/x.pdf');
    ok('cale WP => 404', $r->getStatusCode() === 404);
} finally {
    if ($ids) { $pdo->exec('DELETE FROM meniu WHERE id IN (' . implode(',', $ids) . ')'); }
    if ($fid) { $pdo->exec("DELETE FROM fisiere WHERE id = $fid"); }
}
final_test();
