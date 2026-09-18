<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Migrare\Legacy;

$s = settings();
ok('settings are db_wp.name', ($s['db_wp']['name'] ?? '') === 'flagprahova_wp_old');
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $s['db_wp']['host'], $s['db_wp']['port'], $s['db_wp']['name']), $s['db_wp']['user'], $s['db_wp']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$l = new Legacy($pdo);

$m = $l->meniu();
ok('meniu(): 231 intrări', count($m) === 231);
$byId = array_column($m, null, 'id');
ok('  579 Organigrama: custom, parent 237, url decodat', $byId[579]['tip'] === 'custom' && $byId[579]['parent'] === 237 && $byId[579]['url'] === '/wp-content/uploads/2017/06/Organigrama.pdf');
ok('  243 url cu %20 decodat', $byId[243]['url'] === '/wp-content/uploads/2021/09/POESP - R3.pdf');
ok('  176 e pagina 75 cu titlul paginii', $byId[176]['tip'] === 'post_type' && $byId[176]['obiect_id'] === 75 && $byId[176]['titlu'] === 'Acasă');
ok('  250 Noutăți rădăcină', $byId[250]['parent'] === 0 && $byId[250]['titlu'] === 'Noutăți');
ok('  ordonat după menu_order', $m[0]['ordine'] <= $m[1]['ordine'] && $m[1]['ordine'] <= $m[2]['ordine']);

$p = $l->pagina(75);
ok('pagina(75) din post_content', $p !== null && str_contains($p['continut'], 'contractului de finanțare'));
$p = $l->pagina(156);
ok('pagina(156) din builder html', $p !== null && str_contains($p['continut'], '0762 609 685'));
ok('pagina(362) goală => continut ""', ($l->pagina(362)['continut'] ?? 'x') === '');
ok('pagina(999999) => null', $l->pagina(999999) === null);

ok('atasamentCale(2903)', $l->atasamentCale(2903) === '2022/10/Instruire_01_Busteni.jpg');
ok('atasamenteCai', $l->atasamenteCai([2903, 2947]) === [2903 => '2022/10/Instruire_01_Busteni.jpg', 2947 => '2022/10/Culinar_01_Busteni.jpg']);

ok('caleDinUrl relativ', Legacy::caleDinUrl('/wp-content/uploads/2021/09/POESP%20-%20R3.pdf') === '2021/09/POESP - R3.pdf');
ok('caleDinUrl absolut http', Legacy::caleDinUrl('http://www.flagprahova.ro/wp-content/uploads/2017/08/Calendar%20Estimativ-2.jpg') === '2017/08/Calendar Estimativ-2.jpg');
ok('caleDinUrl folder => null', Legacy::caleDinUrl('/wp-content/uploads/2017/08/') === null);
ok('caleDinUrl # => null', Legacy::caleDinUrl('#') === null);
ok('caleDinUrl http://ab => null', Legacy::caleDinUrl('http://ab') === null);
ok('caleDinUrl extern => null', Legacy::caleDinUrl('https://example.com/x.pdf') === null);
ok('caleDinUrl gol => null', Legacy::caleDinUrl('') === null);

ok('meniu inexistent => []', (new Legacy($pdo, 'wpt9_', 'Inexistent'))->meniu() === []);
final_test();
