<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// Ajustările de layout din 2026-09-19: hero cu fotografii, sigla → prima pagină,
// butonul spre cealaltă perioadă în footer, data publicării, `cu_baza` pe conținut.
$root = dirname(__DIR__);
foreach (['landing-1', 'landing-2', 'landing-3', '2021-2027', '2014-2020'] as $n) {
    ok("hero/$n există în ambele lățimi", is_file("$root/assets/img/hero/$n-1920.webp") && is_file("$root/assets/img/hero/$n-960.webp"));
}

$c = corp(cerere('GET', '/'));
ok('landing: casetele perioadelor sunt ÎN hero (o singură secțiune)', preg_match('#<section class="fp-hero[^"]*fp-hero--landing.*?Intră în secțiune.*?</section>#s', $c) === 1 && !str_contains($c, 'fp-section--soft'));
ok('  slider cu 3 imagini, prima cu prioritate, restul lazy', substr_count($c, 'class="fp-hero__slide"') === 3 && str_contains($c, 'landing-1-1920.webp') && str_contains($c, 'fetchpriority="high"') && substr_count($c, 'loading="lazy"') >= 2);
ok('  sigla din header duce la prima pagină', preg_match('#<a class="fp-header__sigla" href="/">#', $c) === 1);
ok('  fără ilustrația SVG', !str_contains($c, 'ilustratie.svg'));

$c = corp(cerere('GET', '/2014-2020/'));
ok('acasă 2014-2020: fotografia proprie', str_contains($c, 'assets/img/hero/2014-2020-1920.webp') && !str_contains($c, 'hero/2021-2027'));
ok('  sigla din header duce la prima pagină și de aici', preg_match('#<a class="fp-header__sigla" href="/">#', $c) === 1);
ok('  footer: cealaltă perioadă e buton accent', preg_match('#<a class="fp-btn fp-btn--accent fp-footer__cealalta" href="/2021-2027/">FLAG Prahova 2021-2027 →</a>#', $c) === 1);

// Data publicării: `publicat_la` explicit → zi lună an; fără → luna din calea fișierului.
$pdo = pdo();
$s = $pdo->query("SELECT * FROM sectiuni WHERE slug='2014-2020'")->fetch();
$m = strtolower('Dt' . bin2hex(random_bytes(3)));
$ids = []; $fid = null;
try {
    $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES (:n, :c, :m, :s)')
        ->execute(['n' => 'Doc.pdf', 'c' => "2019/06/doc-$m.pdf", 'm' => 'application/pdf', 's' => 2048]);
    $fid = (int) $pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, fisier_id, continut_html, publicat_la) VALUES (:s, :p, 999, :t, :sl, :tip, :f, :h, :d)');
    $ins->execute(['s' => $s['id'], 'p' => null, 't' => "Dosar $m", 'sl' => "dosar-$m", 'tip' => 'dosar', 'f' => null, 'h' => null, 'd' => null]);
    $d = (int) $pdo->lastInsertId(); $ids[] = $d;
    $ins->execute(['s' => $s['id'], 'p' => $d, 't' => "Fara data $m", 'sl' => "fara-$m", 'tip' => 'document', 'f' => $fid, 'h' => null, 'd' => null]); $ids[] = (int) $pdo->lastInsertId();
    $ins->execute(['s' => $s['id'], 'p' => $d, 't' => "Cu data $m", 'sl' => "cu-$m", 'tip' => 'pagina', 'f' => null, 'h' => '<p>x</p>', 'd' => '2023-12-29']); $ids[] = (int) $pdo->lastInsertId();
    $c = corp(cerere('GET', "/2014-2020/dosar-$m"));
    ok('document fără publicat_la: luna și anul din calea fișierului', str_contains($c, 'PDF · 2 KB · <time class="fp-doc__data">iunie 2019</time>'));
    ok('pagină cu publicat_la: data completă + datetime', str_contains($c, 'Pagină · <time class="fp-doc__data" datetime="2023-12-29">29 decembrie 2023</time>'));
} finally {
    if ($ids) { $pdo->exec('DELETE FROM meniu WHERE id IN (' . implode(',', array_reverse($ids)) . ')'); }
    if ($fid) { $pdo->exec("DELETE FROM fisiere WHERE id = $fid"); }
}

// `cu_baza`: prefixează BASE_PATH la src/href absolute din conținut, o singură dată.
$st = settings(); $st['app']['base_path'] = '/nou'; $st['app']['url'] = 'http://flagprahova.test/nou';
$db = new \App\Database($st['db']);
$ctx = new \App\Public\Context(new \App\Meniu\Repository($db), new \App\Fisiere\Repository($db), new \App\Setari\Repository($db), $st);
$env = new \Twig\Environment(new \Twig\Loader\ArrayLoader(['t' => '{{ h|cu_baza|raw }}']));
\App\Bootstrap::functiiTwig($env, $st, $ctx);
$h = '<p><img src="/fisiere/2017/08/a.jpg"> <a href="/2014-2020/x">x</a> <a href=\'/nou/deja\'>d</a> <a href="//cdn.x/y">c</a> <a href="https://e.org/">e</a> <span data-src="/nu">n</span></p>';
ok('cu_baza prefixează src/href absolute', $env->render('t', ['h' => $h]) === '<p><img src="/nou/fisiere/2017/08/a.jpg"> <a href="/nou/2014-2020/x">x</a> <a href=\'/nou/deja\'>d</a> <a href="//cdn.x/y">c</a> <a href="https://e.org/">e</a> <span data-src="/nu">n</span></p>');
$env2 = new \Twig\Environment(new \Twig\Loader\ArrayLoader(['t' => '{{ h|cu_baza|raw }}']));
$st2 = $st; $st2['app']['base_path'] = '';
\App\Bootstrap::functiiTwig($env2, $st2, $ctx);
ok('cu_baza fără BASE_PATH nu schimbă nimic', $env2->render('t', ['h' => $h]) === $h);
final_test();
