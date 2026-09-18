<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Migrare\Harta;

$exista = fn(string $cale): bool => $cale === '2017/06/Organigrama.pdf';
$item = fn(array $o) => $o + ['id' => 1, 'ordine' => 1, 'parent' => 0, 'tip' => 'custom', 'obiect_id' => 0, 'titlu' => 'X', 'url' => ''];

$r = Harta::clasifica($item(['url' => '/wp-content/uploads/2017/06/Organigrama.pdf']), null, false, $exista);
ok('document existent', $r['tip'] === 'document' && $r['cale'] === '2017/06/Organigrama.pdf');
$r = Harta::clasifica($item(['url' => '/wp-content/uploads/2019/05/Lipsa.pdf']), null, false, $exista);
ok('document lipsă => sari', $r['tip'] === 'sari' && str_contains((string) $r['motiv'], 'lipsa'));
ok('# cu copii => dosar', Harta::clasifica($item(['url' => '#']), null, true, $exista)['tip'] === 'dosar');
ok('http://ab cu copii => dosar', Harta::clasifica($item(['url' => 'http://ab']), null, true, $exista)['tip'] === 'dosar');
ok('folder cu copii => dosar', Harta::clasifica($item(['url' => '/wp-content/uploads/2017/08/']), null, true, $exista)['tip'] === 'dosar');
ok('# fără copii => sari', Harta::clasifica($item(['url' => '#']), null, false, $exista)['tip'] === 'sari');
$r = Harta::clasifica($item(['url' => 'https://www.madr.ro/pescuit.html']), null, false, $exista);
ok('extern => link', $r['tip'] === 'link' && $r['url'] === 'https://www.madr.ro/pescuit.html');
ok('pagină cu conținut => pagina', Harta::clasifica($item(['tip' => 'post_type', 'obiect_id' => 567]), ['id' => 567, 'titlu' => 'M1', 'slug' => 'm1', 'continut' => '<p>x</p>'], false, $exista)['tip'] === 'pagina');
ok('pagină goală cu copii => dosar', Harta::clasifica($item(['tip' => 'post_type', 'obiect_id' => 2714]), ['id' => 2714, 'titlu' => 'R', 'slug' => 'r', 'continut' => ''], true, $exista)['tip'] === 'dosar');
ok('pagină goală fără copii => sari', Harta::clasifica($item(['tip' => 'post_type', 'obiect_id' => 307]), ['id' => 307, 'titlu' => 'S', 'slug' => 's', 'continut' => ''], false, $exista)['tip'] === 'sari');
ok('pagină inexistentă => sari', Harta::clasifica($item(['tip' => 'post_type', 'obiect_id' => 1]), null, false, $exista)['tip'] === 'sari');
ok('sectiuneaPentru 3622 => 2021-2027', Harta::sectiuneaPentru(3622) === '2021-2027');
ok('sectiuneaPentru 3601 => 2014-2020', Harta::sectiuneaPentru(3601) === '2014-2020');
ok('MENIU_2021 are 9 rădăcini, Media cu 3 copii', count(Harta::MENIU_2021) === 9 && count(Harta::MENIU_2021[6]['copii']) === 3 && Harta::MENIU_2021[8]['sablon'] === 'contact');
final_test();
