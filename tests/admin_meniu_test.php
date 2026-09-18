<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$uid = logheaza_test();
$sid = (int) $pdo->query("SELECT id FROM sectiuni WHERE slug='2021-2027'")->fetchColumn();
$marca = 'Test ' . bin2hex(random_bytes(3));
$ids = [];
try {
    $r = cerere('GET', '/admin/meniu?sectiune=2021-2027');
    ok('GET /admin/meniu => 200', $r->getStatusCode() === 200);
    ok('  are taburile secțiunilor', str_contains(corp($r), '2021-2027') && str_contains(corp($r), '2014-2020'));
    ok('  are linkul Adaugă intrare', str_contains(corp($r), '/admin/meniu/nou?sectiune=2021-2027'));

    // creare dosar la nivel 1
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => '', 'titlu' => "Noutăți $marca", 'tip' => 'dosar', 'vizibil' => '1']);
    ok('salveaza dosar => 302', $r->getStatusCode() === 302);
    $dosar = $pdo->query("SELECT * FROM meniu WHERE titlu = " . $pdo->quote("Noutăți $marca"))->fetch();
    ok('  dosar creat, slug corect', $dosar && str_starts_with($dosar['slug'], 'noutati-test-'));
    $ids[] = (int) $dosar['id'];

    // titlu gol => formular re-randat cu eroare, nimic creat
    $n0 = (int) $pdo->query('SELECT COUNT(*) FROM meniu')->fetchColumn();
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => $dosar['id'], 'titlu' => '', 'tip' => 'dosar']);
    ok('titlu gol => 200 cu eroare', $r->getStatusCode() === 200 && str_contains(corp($r), 'Titlul este obligatoriu'));
    ok('  nimic creat', (int) $pdo->query('SELECT COUNT(*) FROM meniu')->fetchColumn() === $n0);

    // link fără http => eroare
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => $dosar['id'], 'titlu' => 'L', 'tip' => 'link', 'url' => 'ftp://x']);
    ok('link invalid => eroare', $r->getStatusCode() === 200 && str_contains(corp($r), 'Linkul trebuie să înceapă cu'));

    // document fără fișier => eroare; cu fișier => ok
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => $dosar['id'], 'titlu' => 'D', 'tip' => 'document', 'fisier_id' => '']);
    ok('document fără fișier => eroare', $r->getStatusCode() === 200 && str_contains(corp($r), 'Alege un fișier'));
    $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES ("t.pdf", :c, "application/pdf", 1)')->execute(['c' => '2026/01/test-' . bin2hex(random_bytes(3)) . '.pdf']);
    $fid = (int) $pdo->lastInsertId();
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => $dosar['id'], 'titlu' => "Comunicat $marca", 'tip' => 'document', 'fisier_id' => $fid]);
    $doc = $pdo->query("SELECT * FROM meniu WHERE titlu = " . $pdo->quote("Comunicat $marca"))->fetch();
    ok('document creat sub dosar', $doc && (int) $doc['parent_id'] === (int) $dosar['id'] && (int) $doc['fisier_id'] === $fid);
    $ids[] = (int) $doc['id'];

    // editare
    $r = cerere('GET', '/admin/meniu/' . $doc['id']);
    ok('GET formular editare => 200 cu titlul', $r->getStatusCode() === 200 && str_contains(corp($r), "Comunicat $marca"));
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'id' => $doc['id'], 'sectiune_id' => $sid, 'parent_id' => $dosar['id'], 'titlu' => "Comunicat $marca v2", 'tip' => 'document', 'fisier_id' => $fid, 'vizibil' => '0']);
    $doc2 = $pdo->query('SELECT * FROM meniu WHERE id = ' . (int) $doc['id'])->fetch();
    ok('editare salvează titlu + vizibil', $doc2['titlu'] === "Comunicat $marca v2" && (int) $doc2['vizibil'] === 0);

    // la editare, sectiune_id din POST se ignoră (nu mutăm intrarea în altă secțiune printr-un POST fabricat)
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'id' => $doc['id'], 'sectiune_id' => 999999, 'parent_id' => $dosar['id'], 'titlu' => "Comunicat $marca v2", 'tip' => 'document', 'fisier_id' => $fid, 'vizibil' => '0']);
    ok('editare ignoră sectiune_id din POST', $r->getStatusCode() === 302
        && (int) $pdo->query('SELECT sectiune_id FROM meniu WHERE id = ' . (int) $doc['id'])->fetchColumn() === $sid);

    // părinte dintr-o altă secțiune => ignorat (altfel ar deveni rădăcină „fantomă”)
    $sid2 = (int) $pdo->query("SELECT id FROM sectiuni WHERE slug='2014-2020'")->fetchColumn();
    cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid2, 'parent_id' => $dosar['id'], 'titlu' => "Strain $marca", 'tip' => 'dosar', 'vizibil' => '1']);
    $strain = $pdo->query("SELECT * FROM meniu WHERE titlu = " . $pdo->quote("Strain $marca"))->fetch();
    ok('parent din altă secțiune => ignorat', $strain && (int) $strain['sectiune_id'] === $sid2 && $strain['parent_id'] === null);
    if ($strain) { $pdo->exec('DELETE FROM meniu WHERE id = ' . (int) $strain['id']); }

    // CSRF greșit la ștergere => nu se șterge nimic, flash de eroare
    $r = cerere('POST', '/admin/meniu/' . $doc['id'] . '/sterge', ['_csrf' => 'gresit']);
    ok('sterge cu CSRF greșit => intrarea rămâne', $r->getStatusCode() === 302
        && str_contains($_SESSION['flash']['mesaj'] ?? '', 'Sesiunea a expirat')
        && (int) $pdo->query('SELECT COUNT(*) FROM meniu WHERE id = ' . (int) $doc['id'])->fetchColumn() === 1);

    // arborele afișează ambele, cu data-id
    $r = cerere('GET', '/admin/meniu?sectiune=2021-2027');
    ok('arbore: nodurile au data-id', str_contains(corp($r), 'data-id="' . $dosar['id'] . '"') && str_contains(corp($r), 'data-id="' . $doc['id'] . '"'));

    // reordonare JSON: doc devine rădăcină, dosar copil al lui
    $req = (new Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('POST', '/admin/meniu/reordoneaza')
        ->withHeader('Content-Type', 'application/json')->withHeader('X-CSRF', 'abc');
    $req->getBody()->write(json_encode(['sectiune_id' => $sid, 'arbore' => [['id' => (int) $doc['id'], 'copii' => [['id' => (int) $dosar['id'], 'copii' => []]]]]]));
    $req->getBody()->rewind();
    $r = app()->handle($req);
    ok('reordoneaza => 200 ok', $r->getStatusCode() === 200 && (json_decode(corp($r), true)['ok'] ?? false) === true);
    ok('  dosar acum copil al documentului', (int) $pdo->query('SELECT parent_id FROM meniu WHERE id = ' . (int) $dosar['id'])->fetchColumn() === (int) $doc['id']);
    // dar arborele trimis doar cu rădăcina noastră NU atinge alte intrări ale secțiunii
    ok('  reordonarea parțială nu șterge alte intrări', (int) $pdo->query("SELECT COUNT(*) FROM meniu WHERE sectiune_id = $sid")->fetchColumn() >= 2);

    // ștergere cu descendenți
    $r = cerere('POST', '/admin/meniu/' . $doc['id'] . '/sterge', ['_csrf' => 'abc']);
    ok('sterge => 302 + flash 2 intrări', $r->getStatusCode() === 302 && str_contains($_SESSION['flash']['mesaj'] ?? '', '2'));
    ok('  ambele șterse', (int) $pdo->query('SELECT COUNT(*) FROM meniu WHERE id IN (' . (int) $doc['id'] . ',' . (int) $dosar['id'] . ')')->fetchColumn() === 0);
    $ids = [];
    $pdo->exec("DELETE FROM fisiere WHERE id = $fid");
} finally {
    foreach ($ids as $id) { $pdo->exec("DELETE FROM meniu WHERE id = $id"); }
    $pdo->exec("DELETE FROM utilizatori WHERE id = $uid");
}
final_test();
