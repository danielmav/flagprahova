<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$r = cerere('GET', '/');
ok('GET / => 200', $r->getStatusCode() === 200);
ok('GET / conține titlul', str_contains(corp($r), 'FLAG Prahova'));
ok('GET / are nosniff', $r->getHeaderLine('X-Content-Type-Options') === 'nosniff');

$r = cerere('GET', '/health');
ok('GET /health => 200 (DB locală pornită)', $r->getStatusCode() === 200);
ok('helper e() escapează', e('<a>') === '&lt;a&gt;');
ok('slugify diacritice', slugify('Apel lansare – Măsura 1 (rev.2) Șirna') === 'apel-lansare-masura-1-rev-2-sirna');
ok('slugify gol => intrare', slugify('---') === 'intrare');
final_test();
