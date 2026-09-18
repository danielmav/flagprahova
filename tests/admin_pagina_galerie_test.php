<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Support\Html;

ok('Html: scoate script', !str_contains(Html::curata('<p>a</p><script>alert(1)</script>'), 'script'));
ok('Html: scoate onclick', !str_contains(Html::curata('<p onclick="x()">a</p>'), 'onclick'));
ok('Html: scoate javascript:', !str_contains(Html::curata('<a href="javascript:alert(1)">x</a>'), 'javascript'));
ok('Html: păstrează p/strong/a', Html::curata('<p><strong>b</strong> <a href="https://x.ro">l</a></p>') === '<p><strong>b</strong> <a href="https://x.ro">l</a></p>');
ok('Html: target=_blank primește rel=noopener noreferrer', str_contains(Html::curata('<a href="https://x.ro" target="_blank">l</a>'), 'rel="noopener noreferrer"'));
ok('Html: iframe youtube păstrat', str_contains(Html::curata('<iframe src="https://www.youtube.com/embed/abc" allowfullscreen></iframe>'), '<iframe'));
ok('Html: iframe alt domeniu scos', !str_contains(Html::curata('<iframe src="https://evil.com/x"></iframe>'), '<iframe'));
ok('Html: diacritice intacte', Html::curata('<p>Șirna și Păulești</p>') === '<p>Șirna și Păulești</p>');
// Doar text și elemente: o instrucțiune de procesare nu trebuie emisă verbatim,
// nici să scurgă `</div>`-ul wrapperului intern.
$pi = Html::curata('<?x onerror=alert(1) ');
ok('Html: processing instruction eliminată', !str_contains($pi, 'onerror') && !str_contains($pi, '<?') && !str_contains($pi, '</div>'));
ok('Html: comentariu eliminat', !str_contains(Html::curata('<p>a<!-- x --></p>'), 'x --'));
// href: listă albă de scheme, nu listă neagră.
ok('Html: href protocol-relative scos', !str_contains(Html::curata('<a href="//evil.tld/x">l</a>'), 'evil.tld'));
ok('Html: href absolut păstrat', str_contains(Html::curata('<a href="/fisiere/x.pdf">l</a>'), 'href="/fisiere/x.pdf"'));
ok('Html: ancoră păstrată', str_contains(Html::curata('<a href="#sus">l</a>'), 'href="#sus"'));
ok('Html: mailto păstrat', str_contains(Html::curata('<a href="mailto:a@b.ro">l</a>'), 'href="mailto:a@b.ro"'));
ok('Html: https păstrat', str_contains(Html::curata('<a href="https://x.ro/a?b=1">l</a>'), 'href="https://x.ro/a?b=1"'));
ok('Html: img cu src protocol-relative pierde src', !str_contains(Html::curata('<img src="//evil.tld/a.png" alt="a">'), 'evil.tld'));
ok('Html: rel existent păstrat, completat', Html::curata('<a href="https://x.ro" target="_blank" rel="nofollow">l</a>')
    === '<a href="https://x.ro" target="_blank" rel="nofollow noopener noreferrer">l</a>');

$pdo = pdo();
$uid = logheaza_test();
$sid = (int) $pdo->query("SELECT id FROM sectiuni WHERE slug='2014-2020'")->fetchColumn();
$marca = bin2hex(random_bytes(3));
$fids = []; $mids = [];
try {
    foreach (['a.png' => 'image/png', 'b.png' => 'image/png', 'c.pdf' => 'application/pdf'] as $n => $m) {
        $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES (:n, :c, :m, 1)')->execute(['n' => $n, 'c' => "2026/01/$marca-$n", 'm' => $m]);
        $fids[$n] = (int) $pdo->lastInsertId();
    }
    // pagina: HTML curățat la salvare
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => '', 'titlu' => "Pagina $marca", 'tip' => 'pagina', 'sablon' => 'contact', 'vizibil' => '1',
        'continut_html' => '<p>Text</p><script>x()</script><img src="/fisiere/2026/01/a.png" onerror="x()">']);
    $p = $pdo->query('SELECT * FROM meniu WHERE titlu = ' . $pdo->quote("Pagina $marca"))->fetch();
    $mids[] = (int) $p['id'];
    ok('pagina salvată cu sablon contact', $p['sablon'] === 'contact');
    ok('  HTML curățat (fără script/onerror, cu img)', !str_contains($p['continut_html'], 'script') && !str_contains($p['continut_html'], 'onerror') && str_contains($p['continut_html'], '<img'));

    // galerie: 2 imagini + 1 pdf (ignorat), ordine + legende
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => '', 'titlu' => "Galerie $marca", 'tip' => 'galerie', 'vizibil' => '1',
        'galerie_fisier_id' => [$fids['b.png'], $fids['c.pdf'], $fids['a.png']], 'galerie_legenda' => ['Doi', 'x', 'Unu']]);
    $g = $pdo->query('SELECT * FROM meniu WHERE titlu = ' . $pdo->quote("Galerie $marca"))->fetch();
    $mids[] = (int) $g['id'];
    $gal = (new App\Meniu\GalerieRepository(new App\Database(settings()['db'])))->imagini((int) $g['id']);
    ok('galerie: 2 imagini (pdf ignorat)', count($gal) === 2);
    ok('  ordinea b, a', $gal[0]['fisier_id'] == $fids['b.png'] && $gal[1]['fisier_id'] == $fids['a.png']);
    ok('  legendele', $gal[0]['legenda'] === 'Doi' && $gal[1]['legenda'] === 'Unu');
    ok('  cale din fisiere', str_ends_with($gal[0]['cale'], 'b.png'));

    $r = cerere('GET', '/admin/meniu/' . $g['id']);
    // Numărăm doar rândurile reale din <ol>, nu și cel din <template>.
    $lista = explode('<template', corp($r))[0];
    ok('formularul galeriei listează imaginile', substr_count($lista, 'data-fisier-id="') === 2);

    // re-salvare cu o singură imagine înlocuiește setul
    cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'id' => $g['id'], 'sectiune_id' => $sid, 'parent_id' => '', 'titlu' => "Galerie $marca", 'tip' => 'galerie', 'vizibil' => '1',
        'galerie_fisier_id' => [$fids['a.png']], 'galerie_legenda' => ['']]);
    ok('re-salvarea înlocuiește setul', (int) $pdo->query('SELECT COUNT(*) FROM galerie_imagini WHERE meniu_id = ' . (int) $g['id'])->fetchColumn() === 1);

    // schimbarea tipului golește galeria (imaginile n-ar mai fi accesibile din formular)
    cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'id' => $g['id'], 'sectiune_id' => $sid, 'parent_id' => '', 'titlu' => "Galerie $marca", 'tip' => 'dosar', 'vizibil' => '1']);
    ok('schimbarea tipului golește galeria', (int) $pdo->query('SELECT COUNT(*) FROM galerie_imagini WHERE meniu_id = ' . (int) $g['id'])->fetchColumn() === 0);
} finally {
    foreach ($mids as $id) { $pdo->exec("DELETE FROM meniu WHERE id = $id"); }
    foreach ($fids as $id) { $pdo->exec("DELETE FROM fisiere WHERE id = $id"); }
    $pdo->exec("DELETE FROM utilizatori WHERE id = $uid");
}
final_test();
