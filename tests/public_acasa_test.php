<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$s = $pdo->query("SELECT * FROM sectiuni WHERE slug='2021-2027'")->fetch();
$marca = 'Aca' . bin2hex(random_bytes(3));
$ids = []; $fid = null; $noutatiTemp = false;
try {
    $nout = $pdo->query("SELECT id FROM meniu WHERE sectiune_id={$s['id']} AND slug='noutati' AND parent_id IS NULL")->fetch();
    if (!$nout) {
        $pdo->exec("INSERT INTO meniu (sectiune_id, ordine, titlu, slug, tip) VALUES ({$s['id']}, 0, 'Noutăți', 'noutati', 'dosar')");
        $nout = ['id' => (int) $pdo->lastInsertId()]; $ids[] = (int) $nout['id']; $noutatiTemp = true;
    }
    $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES ("c.pdf", :c, "application/pdf", 2048)')->execute(['c' => "2026/09/comunicat-$marca.pdf"]);
    $fid = (int) $pdo->lastInsertId();
    // ordine -1 => primul din listă indiferent de ce mai există
    $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, fisier_id) VALUES (:s, :p, 0, :t, :sl, "document", :f)')
        ->execute(['s' => $s['id'], 'p' => $nout['id'], 't' => "Comunicat $marca", 'sl' => "comunicat-" . strtolower($marca), 'f' => $fid]);
    $ids[] = (int) $pdo->lastInsertId();
    $pdo->exec("UPDATE meniu SET ordine = 0 WHERE id = " . end($ids));
    $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip) VALUES (:s, NULL, 998, :t, :sl, "dosar")')
        ->execute(['s' => $s['id'], 't' => "Ramura $marca", 'sl' => "ramura-" . strtolower($marca)]);
    $ids[] = (int) $pdo->lastInsertId();

    $r = cerere('GET', '/2021-2027/');
    $c = corp($r);
    ok('GET /2021-2027/ => 200', $r->getStatusCode() === 200);
    ok('  h1 unic = titlul secțiunii', substr_count($c, '<h1') === 1 && str_contains($c, $s['titlu']));
    ok('  title/description proprii', str_contains($c, '<title>' . $s['titlu']) && str_contains($c, 'name="description" content="' . $s['subtitlu']));
    ok('  canonical', str_contains($c, '<link rel="canonical" href="http://flagprahova.test/2021-2027/">'));
    ok('  textul acasă', str_contains($c, 'id="despre"'));
    ok('  blocul Noutăți cu documentul nostru + mărime', str_contains($c, "Comunicat $marca") && str_contains($c, '2 KB') && str_contains($c, "href=\"/fisiere/2026/09/comunicat-$marca.pdf\""));
    ok('  cardul pentru intrarea de nivel 1', preg_match('#class="fp-card[^"]*"[^>]*href="/2021-2027/ramura-' . strtolower($marca) . '"#', $c) === 1);
    ok('  butonul Contact din hero', str_contains($c, 'href="/2021-2027/contact"') || !str_contains($c, 'fp-hero__btn-contact'));
    ok('  ilustrația', str_contains($c, 'assets/img/ilustratie.svg'));
} finally {
    if ($ids) { $pdo->exec('DELETE FROM meniu WHERE id IN (' . implode(',', array_reverse($ids)) . ')'); }
    if ($fid) { $pdo->exec("DELETE FROM fisiere WHERE id = $fid"); }
}
final_test();
