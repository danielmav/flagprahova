<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$s = $pdo->query("SELECT * FROM sectiuni WHERE slug='2021-2027'")->fetch();
$m = strtolower('Seo' . bin2hex(random_bytes(3)));
$ids = []; $fid = null;
try {
    $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES ("d.pdf", :c, "application/pdf", 1)')->execute(['c' => "2026/09/d-$m.pdf"]);
    $fid = (int) $pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, fisier_id, vizibil) VALUES (:s, :p, 999, :t, :sl, :tip, :f, :v)');
    $ins->execute(['s' => $s['id'], 'p' => null, 't' => "Dosar $m", 'sl' => "dosar-$m", 'tip' => 'dosar', 'f' => null, 'v' => 1]); $d = (int) $pdo->lastInsertId(); $ids[] = $d;
    $ins->execute(['s' => $s['id'], 'p' => $d, 't' => "Pag $m", 'sl' => "pag-$m", 'tip' => 'pagina', 'f' => null, 'v' => 1]); $ids[] = (int) $pdo->lastInsertId();
    $ins->execute(['s' => $s['id'], 'p' => $d, 't' => "Doc $m", 'sl' => "doc-$m", 'tip' => 'document', 'f' => $fid, 'v' => 1]); $ids[] = (int) $pdo->lastInsertId();
    $ins->execute(['s' => $s['id'], 'p' => $d, 't' => "Asc $m", 'sl' => "asc-$m", 'tip' => 'pagina', 'f' => null, 'v' => 0]); $ids[] = (int) $pdo->lastInsertId();

    $r = cerere('GET', '/sitemap.xml'); $c = corp($r);
    ok('sitemap => 200 xml', $r->getStatusCode() === 200 && str_starts_with($r->getHeaderLine('Content-Type'), 'application/xml') && str_starts_with(trim($c), '<?xml'));
    ok('  landing + secțiuni', str_contains($c, '<loc>http://flagprahova.test/</loc>') && str_contains($c, '<loc>http://flagprahova.test/2021-2027/</loc>') && str_contains($c, '<loc>http://flagprahova.test/2014-2020/</loc>'));
    ok('  dosar + pagină, cu lastmod', str_contains($c, "<loc>http://flagprahova.test/2021-2027/dosar-$m</loc>") && str_contains($c, "<loc>http://flagprahova.test/2021-2027/pag-$m</loc>") && preg_match('#<lastmod>\d{4}-\d{2}-\d{2}</lastmod>#', $c) === 1);
    ok('  fără document / invizibil', !str_contains($c, "doc-$m") && !str_contains($c, "asc-$m"));
    ok('  XML valid', simplexml_load_string($c) !== false);

    $r = cerere('GET', '/robots.txt'); $c = corp($r);
    ok('robots', $r->getStatusCode() === 200 && str_contains($c, 'Disallow: /admin') && str_contains($c, 'Sitemap: http://flagprahova.test/sitemap.xml'));

    $r = cerere('GET', '/admin/login');
    ok('adminul e noindex', str_contains(corp($r), 'noindex'));
} finally {
    if ($ids) { $pdo->exec('DELETE FROM meniu WHERE id IN (' . implode(',', array_reverse($ids)) . ')'); }
    if ($fid) { $pdo->exec("DELETE FROM fisiere WHERE id = $fid"); }
}
final_test();
