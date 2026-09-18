<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo  = pdo();
$repo = new App\Meniu\Repository(new App\Database(settings()['db']));
$sec  = $repo->sectiuneDupaSlug('2014-2020');
ok('sectiuneDupaSlug', $sec !== null && $sec['slug'] === '2014-2020');
$sid  = (int) $sec['id'];
$marca = 'test-' . bin2hex(random_bytes(3));
$creati = [];
try {
    $a = $repo->creeaza(['sectiune_id' => $sid, 'parent_id' => null, 'titlu' => "Strategie $marca", 'slug' => '', 'tip' => 'dosar']);
    $b = $repo->creeaza(['sectiune_id' => $sid, 'parent_id' => $a, 'titlu' => "Ghidul solicitantului $marca", 'slug' => '', 'tip' => 'dosar']);
    // Titlul include marcajul aleator: un titlu fix ca „Măsura 1 – Rev. 5” poate
    // coincide cu slug-ul unei intrări REALE deja migrate din WordPress, caz în
    // care `slugUnic()` ar da testului sufixul „-2” în loc de sufixul curat,
    // rupând aserțiunile de mai jos fără nicio legătură cu codul testat.
    $titluBaza = "Măsura 1 – Rev. 5 $marca";
    $slugBaza = slugify($titluBaza);
    $c = $repo->creeaza(['sectiune_id' => $sid, 'parent_id' => $b, 'titlu' => $titluBaza, 'slug' => '', 'tip' => 'link', 'url' => 'https://example.com/m1.pdf']);
    $d = $repo->creeaza(['sectiune_id' => $sid, 'parent_id' => $b, 'titlu' => $titluBaza, 'slug' => '', 'tip' => 'link', 'url' => 'https://example.com/m1b.pdf']);
    $creati = [$a, $b, $c, $d];

    ok('slug generat din titlu', $repo->gaseste($c)['slug'] === $slugBaza);
    ok('slug duplicat primește sufix -2', $repo->gaseste($d)['slug'] === $slugBaza . '-2');
    ok('ordine crește între frați', (int) $repo->gaseste($d)['ordine'] === (int) $repo->gaseste($c)['ordine'] + 1);

    $arb = $repo->arbore($sid);
    $nodA = null;
    foreach ($arb as $n) { if ((int) $n['id'] === $a) { $nodA = $n; } }
    ok('arbore: nodul A la nivel 1', $nodA !== null);
    ok('arbore: A → B → [C, D] (3 niveluri)', $nodA !== null && count($nodA['copii']) === 1
        && count($nodA['copii'][0]['copii']) === 2);
    ok('arbore: A are descendenti = 3 (tot subarborele)', $nodA !== null && $nodA['descendenti'] === 3);
    ok('arbore: B are descendenti = 2, C are 0', $nodA !== null && $nodA['copii'][0]['descendenti'] === 2
        && $nodA['copii'][0]['copii'][0]['descendenti'] === 0);

    $repo->actualizeaza($c, ['titlu' => 'Măsura 1 – Rev. 6', 'slug' => 'masura-1-rev-6', 'tip' => 'link', 'url' => 'https://example.com/m1c.pdf', 'vizibil' => 0]);
    ok('actualizeaza schimbă titlul', $repo->gaseste($c)['titlu'] === 'Măsura 1 – Rev. 6');
    $viz = $repo->arbore($sid, true);
    $nodAv = null;
    foreach ($viz as $n) { if ((int) $n['id'] === $a) { $nodAv = $n; } }
    ok('arbore doarVizibile exclude C', $nodAv !== null && count($nodAv['copii'][0]['copii']) === 1);

    // actualizeaza: mutarea lui A sub propriul nepot (C, la acest moment A → B → C) trebuie refuzată silențios
    ok('esteDescendent(A, C) = true înainte de reordonare', $repo->esteDescendent($a, $c) === true);
    $repo->actualizeaza($a, ['parent_id' => $c]);
    ok('actualizeaza: mutarea lui A sub nepotul C păstrează parent_id', $repo->gaseste($a)['parent_id'] === null);

    // reordoneaza: D înaintea lui C, ambele mutate direct sub A
    $repo->reordoneaza($sid, [['id' => $a, 'copii' => [['id' => $d, 'copii' => []], ['id' => $c, 'copii' => []], ['id' => $b, 'copii' => []]]]]);
    ok('reordoneaza: D are parent A și ordine 0', (int) $repo->gaseste($d)['parent_id'] === $a && (int) $repo->gaseste($d)['ordine'] === 0);
    ok('reordoneaza: B e al treilea', (int) $repo->gaseste($b)['ordine'] === 2);
    $arunca = false;
    try { $repo->reordoneaza($sid, [['id' => 999999999, 'copii' => []]]); } catch (InvalidArgumentException) { $arunca = true; }
    ok('reordoneaza refuză id străin', $arunca);
    $aruncaDuplicat = false;
    try {
        $repo->reordoneaza($sid, [['id' => $a, 'copii' => [
            ['id' => $b, 'copii' => []],
            ['id' => $c, 'copii' => [['id' => $b, 'copii' => []]]],
        ]]]);
    } catch (InvalidArgumentException) {
        $aruncaDuplicat = true;
    }
    ok('reordoneaza refuză id duplicat în payload', $aruncaDuplicat);

    ok('numaraDescendenti(A) = 3', $repo->numaraDescendenti($a) === 3);

    // fisierFolosit: o intrare cu fisier_id, una cu calea în continut_html, și niciuna pentru un fișier neutilizat
    $caleFisier = 'uploads/test-' . $marca . '.pdf';
    $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES (:n, :c, :m, 0)')
        ->execute(['n' => "Fisier $marca", 'c' => $caleFisier, 'm' => 'application/pdf']);
    $fisierId = (int) $pdo->lastInsertId();
    $caleFisierNefolosit = 'uploads/test-nefolosit-' . $marca . '.pdf';
    $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES (:n, :c, :m, 0)')
        ->execute(['n' => "Fisier nefolosit $marca", 'c' => $caleFisierNefolosit, 'm' => 'application/pdf']);
    $fisierNefolositId = (int) $pdo->lastInsertId();
    try {
        $eViaFisierId = $repo->creeaza(['sectiune_id' => $sid, 'parent_id' => null, 'titlu' => "Doc fisier $marca", 'slug' => '', 'tip' => 'document', 'fisier_id' => $fisierId]);
        $fViaContinut = $repo->creeaza(['sectiune_id' => $sid, 'parent_id' => null, 'titlu' => "Pagina cu link $marca", 'slug' => '', 'tip' => 'pagina', 'continut_html' => '<a href="/' . $caleFisier . '">descarcă</a>']);
        $creati = array_merge($creati, [$eViaFisierId, $fViaContinut]);

        $folosit = $repo->fisierFolosit($fisierId);
        $idsFolosit = array_map(static fn (array $r): int => (int) $r['id'], $folosit);
        ok('fisierFolosit găsește intrarea cu fisier_id', in_array($eViaFisierId, $idsFolosit, true));
        ok('fisierFolosit găsește intrarea cu calea în continut_html', in_array($fViaContinut, $idsFolosit, true));
        ok('fisierFolosit întoarce [] pentru un fișier neutilizat', $repo->fisierFolosit($fisierNefolositId) === []);
    } finally {
        $pdo->exec("DELETE FROM fisiere WHERE id IN ($fisierId, $fisierNefolositId)");
    }

    ok('sterge(A) șterge 4', $repo->sterge($a) === 4);
    ok('după ștergere B lipsește', $repo->gaseste($b) === null);
    // Doar A/B/C/D au fost șterse de sterge($a) (subarborele lui A); eViaFisierId
    // și fViaContinut sunt rădăcini separate și rămân în $creati pentru finally.
    $creati = array_values(array_diff($creati, [$a, $b, $c, $d]));
} finally {
    foreach ($creati as $id) { $pdo->exec("DELETE FROM meniu WHERE id = $id"); }
}
final_test();
