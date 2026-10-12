<?php
declare(strict_types=1);
// ID invalid = ca și gol: nu ajunge în pagină nimic din ce nu arată a ID de GA4.
$_ENV['GA4_ID'] = '"><script>alert(1)</script>';
require __DIR__ . '/_bootstrap.php';

ok('GA4_ID invalid => setarea e goală', settings()['app']['ga4_id'] === '');
$c = corp(cerere('GET', '/'));
ok('  fără banner, fără consent.js, fără link în subsol', !str_contains($c, 'fp-consent')
    && !str_contains($c, 'consent.js') && !str_contains($c, 'data-consent-deschide'));
final_test();
