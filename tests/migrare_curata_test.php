<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Migrare\Curata;

$harta = ['2017/08/peste-la-cuptor.jpg' => '2017/08/peste-la-cuptor.jpg', '2022/06/Anexa A CF.docx' => '2022/06/anexa-a-cf.docx', '2023/12/Comunicat-SDL.pdf' => '2023/12/comunicat-sdl.pdf'];
$c = new Curata(fn(string $cale): ?string => $harta[$cale] ?? null);

/** Normalizează spațiile dintre elemente, ca aserțiunile stricte să nu depindă de formatarea lui Html::curata. */
$n = static fn(string $html): string => preg_replace('/>\s+</', '><', trim($html)) ?? $html;

// 1. Gutenberg wp:file
$r = $c->proceseaza('<!-- wp:file {"id":3538,"href":"https://www.flagprahova.ro/wp-content/uploads/2023/12/Comunicat-SDL.pdf"} -->' . "\n" . '<div class="wp-block-file"><object class="wp-block-file__embed" data="https://www.flagprahova.ro/wp-content/uploads/2023/12/Comunicat-SDL.pdf" type="application/pdf"></object><a id="x" href="https://www.flagprahova.ro/wp-content/uploads/2023/12/Comunicat-SDL.pdf">Comunicat-SDL</a><a href="https://www.flagprahova.ro/wp-content/uploads/2023/12/Comunicat-SDL.pdf" class="wp-block-file__button" download>Download</a></div>' . "\n" . '<!-- /wp:file --><!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->');
ok('wp:file => un singur link rescris', $n($r['html']) === '<p><a href="/fisiere/2023/12/comunicat-sdl.pdf">Comunicat-SDL</a></p><p>Text</p>');
ok('  fără comentarii wp:', !str_contains($r['html'], 'wp:'));

// 2. VC: video + galerii + shortcode-uri
$r = $c->proceseaza('[vc_row][vc_column][vc_column_text]<p>Titlu proiect</p>&nbsp;<p>A</p>[/vc_column_text][vc_empty_space height="50px"][vc_video link="https://www.youtube.com/watch?v=o6dPJ2CLD5g"][/vc_column][/vc_row][vc_row][vc_column][vc_column_text]<h3>GALERII FOTO</h3>[/vc_column_text][vc_tta_accordion][vc_tta_section title="Instruire vanzari" tab_id="a"][vc_gallery interval="3" images="2903,2904,2905" img_size="full"][/vc_tta_section][vc_tta_section title="Curs Culinar" tab_id="b"][vc_gallery images="2947" img_size="full"][/vc_tta_section][/vc_tta_accordion][/vc_column][/vc_row]');
ok('VC: text păstrat, shortcode-uri scoase', str_contains($r['html'], '<p>Titlu proiect</p>') && !str_contains($r['html'], '[vc_'));
ok('VC: video => iframe embed', str_contains($r['html'], '<iframe src="https://www.youtube.com/embed/o6dPJ2CLD5g"'));
ok('VC: 2 galerii extrase', count($r['galerii']) === 2 && $r['galerii'][0] === ['titlu' => 'Instruire vanzari', 'ids' => [2903, 2904, 2905]] && $r['galerii'][1]['ids'] === [2947]);
ok('VC: secțiunile de galerie nu rămân în html', !str_contains($r['html'], 'Instruire vanzari'));

// 3. shortcode necunoscut
ok('contact-form-7 dispare', $n($c->proceseaza('<p>A</p>[contact-form-7 title="" id="159"]<p>B</p>')['html']) === '<p>A</p><p>B</p>');

// 4. spam
$r = $c->proceseaza('<p>Ghid</p><p>Do a virtual book tour and get on some paper writing service podcasts.</p><p>Daher <a href="https://best-ghostwriter.com/">best-ghostwriter.com</a> die informationen.</p><ul><li><a href="/wp-content/uploads/2022/06/Anexa%20A%20CF.docx">Anexa A</a></li></ul>');
ok('spam: paragrafele cu text spam dispar', !str_contains($r['html'], 'paper writing') && !str_contains($r['html'], 'ghostwriter'));
ok('spam: contor', $r['spam_eliminat'] >= 2);
ok('spam: conținutul bun rămâne, link rescris', str_contains($r['html'], '<p>Ghid</p>') && str_contains($r['html'], 'href="/fisiere/2022/06/anexa-a-cf.docx"'));

// 5. fișier lipsă + img
$r = $c->proceseaza('<p><a href="/wp-content/uploads/2019/05/Lipsa.pdf">Lipsă</a> <img src="/wp-content/uploads/2017/08/peste-la-cuptor.jpg" alt="x"> <img src="/wp-content/uploads/2017/08/nu-exista.jpg"></p>');
ok('lipsă: linkul devine text, în linkuri_rupte', !str_contains($r['html'], '<a') && str_contains($r['html'], 'Lipsă') && $r['linkuri_rupte'] === ['/wp-content/uploads/2019/05/Lipsa.pdf', '/wp-content/uploads/2017/08/nu-exista.jpg']);
ok('lipsă: img existent rescris, img lipsă scos', substr_count($r['html'], '<img') === 1 && str_contains($r['html'], 'src="/fisiere/2017/08/peste-la-cuptor.jpg"'));

// 6. externe + 7. h1 + clase
$r = $c->proceseaza('<h1 class="t">Rețete</h1><p class="lead" style="x">A <a href="https://www.madr.ro/x" target="_blank">MADR</a></p>');
ok('h1 => h2, fără clase', str_starts_with($n($r['html']), '<h2>Rețete</h2><p>A '));
ok('extern păstrat, host raportat', str_contains($r['html'], 'href="https://www.madr.ro/x"') && $r['externe'] === ['www.madr.ro']);
ok('gol => gol', $c->proceseaza('')['html'] === '');
final_test();
