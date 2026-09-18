<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// Convenție: `APP_URL` include deja `BASE_PATH` (staging în subfolder:
// APP_URL=https://flagprahova.ro/nou, BASE_PATH=/nou). `href`-urile din Context
// conțin baza, deci URL-ul absolut = app.url + (cale fără bază).
$db = new \App\Database(settings()['db']);
$s = settings();
$s['app']['base_path'] = '/sub';
$s['app']['url'] = 'http://flagprahova.test/sub';
$ctx = new \App\Public\Context(
    new \App\Meniu\Repository($db),
    new \App\Fisiere\Repository($db),
    new \App\Setari\Repository($db),
    $s
);

$sectiune = $ctx->sectiune('2021-2027');
ok('secțiunea 2021-2027 există', $sectiune !== null);
$arbore = $ctx->arbore($sectiune);
ok('arborele nu e gol', $arbore !== []);
$pagini = array_values(array_filter($arbore, fn($n) => $n['tip'] !== 'link'));
ok('href-urile de pagină încep cu /sub/', $pagini !== [] && array_reduce(
    $pagini,
    fn($acc, $n) => $acc && str_starts_with($n['href'], '/sub/'),
    true
));

ok('urlPublic cu bază în cale nu dublează prefixul', $ctx->urlPublic('/sub/2021-2027/') === 'http://flagprahova.test/sub/2021-2027/');
ok('urlPublic fără bază în cale adaugă app.url', $ctx->urlPublic('/2021-2027/') === 'http://flagprahova.test/sub/2021-2027/');
ok('urlPublic("/") => rădăcina publică', $ctx->urlPublic('/') === 'http://flagprahova.test/sub/');
ok('urlPublic("/sub") => rădăcina publică', $ctx->urlPublic('/sub') === 'http://flagprahova.test/sub/');
// `/subsol` NU e sub bază: prefixul nu se scoate pe potriveală de șir.
ok('urlPublic nu taie un segment care doar începe ca baza', $ctx->urlPublic('/subsol/x') === 'http://flagprahova.test/sub/subsol/x');

// Fără bază (aplicația reală): breadcrumb-ul JSON-LD are URL-uri absolute curate.
$pdo = pdo();
$s1 = $pdo->query("SELECT * FROM sectiuni WHERE slug='2021-2027'")->fetch();
$m = strtolower('Bp' . bin2hex(random_bytes(3)));
$ids = [];
try {
    $ins = $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, continut_html, vizibil) VALUES (:s, :p, 999, :t, :sl, :tip, :c, 1)');
    $ins->execute(['s' => $s1['id'], 'p' => null, 't' => "Dosar $m", 'sl' => "dosar-$m", 'tip' => 'dosar', 'c' => '']);
    $d = (int) $pdo->lastInsertId(); $ids[] = $d;
    $ins->execute(['s' => $s1['id'], 'p' => $d, 't' => "Pag $m", 'sl' => "pag-$m", 'tip' => 'pagina', 'c' => '<p>Text de test.</p>']);
    $ids[] = (int) $pdo->lastInsertId();

    $c = corp(cerere('GET', "/2021-2027/pag-$m"));
    $ld = preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $c, $mm) === 1
        ? json_decode(trim($mm[1]), true) : null;
    ok('BreadcrumbList parsează', is_array($ld) && ($ld['@type'] ?? null) === 'BreadcrumbList' && count($ld['itemListElement']) === 3);
    $urls = array_column($ld['itemListElement'] ?? [], 'item');
    ok('  3 item-uri cu URL', count($urls) === 3);
    ok('  toate absolute pe app.url', array_reduce($urls, fn($a, $u) => $a && str_starts_with($u, 'http://flagprahova.test/'), true));
    ok('  fără // după schemă', array_reduce($urls, fn($a, $u) => $a && !str_contains(substr($u, 7), '//'), true));
    ok('  ultimul e pagina curentă', ($urls[2] ?? '') === "http://flagprahova.test/2021-2027/pag-$m");
} finally {
    if ($ids) { $pdo->exec('DELETE FROM meniu WHERE id IN (' . implode(',', array_reverse($ids)) . ')'); }
}
final_test();
