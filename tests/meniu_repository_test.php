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
    $c = $repo->creeaza(['sectiune_id' => $sid, 'parent_id' => $b, 'titlu' => "Măsura 1 – Rev. 5", 'slug' => '', 'tip' => 'link', 'url' => 'https://example.com/m1.pdf']);
    $d = $repo->creeaza(['sectiune_id' => $sid, 'parent_id' => $b, 'titlu' => "Măsura 1 – Rev. 5", 'slug' => '', 'tip' => 'link', 'url' => 'https://example.com/m1b.pdf']);
    $creati = [$a, $b, $c, $d];

    ok('slug generat din titlu', $repo->gaseste($c)['slug'] === 'masura-1-rev-5');
    ok('slug duplicat primește sufix -2', $repo->gaseste($d)['slug'] === 'masura-1-rev-5-2');
    ok('ordine crește între frați', (int) $repo->gaseste($d)['ordine'] === (int) $repo->gaseste($c)['ordine'] + 1);

    $arb = $repo->arbore($sid);
    $nodA = null;
    foreach ($arb as $n) { if ((int) $n['id'] === $a) { $nodA = $n; } }
    ok('arbore: nodul A la nivel 1', $nodA !== null);
    ok('arbore: A → B → [C, D] (3 niveluri)', $nodA !== null && count($nodA['copii']) === 1
        && count($nodA['copii'][0]['copii']) === 2);

    $repo->actualizeaza($c, ['titlu' => 'Măsura 1 – Rev. 6', 'slug' => 'masura-1-rev-6', 'tip' => 'link', 'url' => 'https://example.com/m1c.pdf', 'vizibil' => 0]);
    ok('actualizeaza schimbă titlul', $repo->gaseste($c)['titlu'] === 'Măsura 1 – Rev. 6');
    $viz = $repo->arbore($sid, true);
    $nodAv = null;
    foreach ($viz as $n) { if ((int) $n['id'] === $a) { $nodAv = $n; } }
    ok('arbore doarVizibile exclude C', $nodAv !== null && count($nodAv['copii'][0]['copii']) === 1);

    // reordoneaza: D înaintea lui C, ambele mutate direct sub A
    $repo->reordoneaza($sid, [['id' => $a, 'copii' => [['id' => $d, 'copii' => []], ['id' => $c, 'copii' => []], ['id' => $b, 'copii' => []]]]]);
    ok('reordoneaza: D are parent A și ordine 0', (int) $repo->gaseste($d)['parent_id'] === $a && (int) $repo->gaseste($d)['ordine'] === 0);
    ok('reordoneaza: B e al treilea', (int) $repo->gaseste($b)['ordine'] === 2);
    $arunca = false;
    try { $repo->reordoneaza($sid, [['id' => 999999999, 'copii' => []]]); } catch (InvalidArgumentException) { $arunca = true; }
    ok('reordoneaza refuză id străin', $arunca);

    ok('numaraDescendenti(A) = 3', $repo->numaraDescendenti($a) === 3);
    ok('sterge(A) șterge 4', $repo->sterge($a) === 4);
    ok('după ștergere B lipsește', $repo->gaseste($b) === null);
    $creati = [];
} finally {
    foreach ($creati as $id) { $pdo->exec("DELETE FROM meniu WHERE id = $id"); }
}
final_test();
