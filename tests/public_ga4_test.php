<?php
declare(strict_types=1);
$_ENV['GA4_ID'] = 'G-TEST12345';
require __DIR__ . '/_bootstrap.php';

foreach (['/' => 'landing', '/2021-2027/' => 'acasă de secțiune', '/pagina-care-nu-exista' => '404'] as $cale => $nume) {
    $c = corp(cerere('GET', $cale));
    ok("GA4_ID setat: $nume are bannerul și consent.js cu ID-ul", str_contains($c, 'class="fp-consent')
        && str_contains($c, '/assets/js/consent.js" data-ga4="G-TEST12345"'));
    ok('  bannerul e ascuns până îl arată scriptul', (bool) preg_match('/<div class="fp-consent[^>]*\shidden>/', $c));
    ok('  scriptul Google NU e în HTML (se încarcă doar după accept)', !str_contains($c, 'googletagmanager.com'));
    ok('  subsolul are „Setări cookie-uri”', str_contains($c, 'data-consent-deschide'));
}
$c = corp(cerere('GET', settings()['admin']['path'] . '/login'));
ok('admin: fără banner și fără consent.js', !str_contains($c, 'fp-consent') && !str_contains($c, 'consent.js'));
final_test();
