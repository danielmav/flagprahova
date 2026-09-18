<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$root = dirname(__DIR__);
foreach (['eu-flag', 'guvernul-romaniei', 'flag-prahova', '2021-2027'] as $l) {
    ok("logo $l.png există", is_file("$root/assets/img/logo/$l.png"));
}
ok('ilustratie.svg e XML valid', is_file("$root/assets/img/ilustratie.svg") && simplexml_load_file("$root/assets/img/ilustratie.svg") !== false);
ok('og-default.png e 1200x630', is_file("$root/assets/img/og-default.png") && getimagesize("$root/assets/img/og-default.png")[0] === 1200 && getimagesize("$root/assets/img/og-default.png")[1] === 630);
$fonturi = glob("$root/assets/fonts/*.woff2") ?: [];
ok('cel puțin 4 fonturi woff2 (sans 400/700 + serif, latin + latin-ext)', count($fonturi) >= 4);
$fcss = (string) @file_get_contents("$root/assets/css/fonts.css");
ok('fonts.css declară DM Sans și DM Serif Display cu unicode-range', str_contains($fcss, "'DM Sans'") && str_contains($fcss, "'DM Serif Display'") && str_contains($fcss, 'unicode-range') && !str_contains($fcss, 'fonts.gstatic.com'));
$css = (string) @file_get_contents("$root/assets/css/site.css");
ok('site.css are tokenurile', str_contains($css, '--fp-navy: #272B5C') && str_contains($css, '--fp-blue: #1F6FC5') && str_contains($css, '--fp-accent: #EC8C16'));
foreach (['.fp-header', '.fp-nav', '.fp-hero', '.fp-eyebrow', '.fp-btn', '.fp-doc', '.fp-galerie', '.fp-footer', '.fp-prose', '.fp-sidebar', '.fp-cta'] as $c) {
    ok("site.css definește $c", str_contains($css, $c));
}
ok('site.js există', is_file("$root/assets/js/site.js"));
final_test();
