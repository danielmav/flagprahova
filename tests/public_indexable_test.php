<?php
declare(strict_types=1);
$_ENV['APP_INDEXABLE'] = 'false';
require __DIR__ . '/_bootstrap.php';

$r = cerere('GET', '/');
ok('APP_INDEXABLE=false: landing are noindex', str_contains(corp($r), '<meta name="robots" content="noindex,nofollow">'));
$r = cerere('GET', '/2021-2027/');
ok('  acasă de secțiune are noindex', str_contains(corp($r), 'content="noindex,nofollow"'));
$r = cerere('GET', '/robots.txt'); $c = corp($r);
ok('  robots.txt blochează tot', str_contains($c, "User-agent: *\nDisallow: /\n") && !str_contains($c, 'Sitemap:'));
ok('  sitemap.xml rămâne accesibil (doar nu e anunțat)', cerere('GET', '/sitemap.xml')->getStatusCode() === 200);
final_test();
