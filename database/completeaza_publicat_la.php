<?php
/**
 * Completează `meniu.publicat_la` din baza WP veche pentru NOUTĂȚILE migrate care
 * pointează spre o pagină WP (data publicării paginii). Documentele urcate prin FTP
 * n-au atașament WP, deci rămân pe luna din calea fișierului (afișată automat).
 * Doar copiii dosarului `noutati`: paginile structurale (Utile, Contact, Măsuri) au
 * data creării în WP, nu una de „publicare" relevantă.
 *
 * Idempotent: scrie doar unde `publicat_la` e NULL. Pe lângă UPDATE-urile locale,
 * scrie și `storage/migrare/publicat_la.sql` (aceleași UPDATE-uri), de rulat în
 * phpMyAdmin pe staging, unde nu există baza WP.
 *
 *   php database/completeaza_publicat_la.php
 */
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
if (is_file($root . '/.env')) { Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
$settings = require $root . '/config/settings.php';
if (($settings['db_wp']['name'] ?? '') === '') { fwrite(STDERR, "DB_WP_NAME gol: nu am de unde citi datele.\n"); exit(1); }
$pdo = (new App\Database($settings['db']))->pdo();
$c   = $settings['db_wp'];
$wp  = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']), $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$p   = $settings['db_wp']['prefix'];

$obj = $wp->prepare("SELECT o.post_date FROM {$p}postmeta pm JOIN {$p}posts o ON o.ID = pm.meta_value
    WHERE pm.post_id = :id AND pm.meta_key = '_menu_item_object_id' AND o.post_type = 'page' LIMIT 1");
$upd = $pdo->prepare('UPDATE meniu SET publicat_la = :d WHERE id = :id AND publicat_la IS NULL');
$sql = ['-- publicat_la din datele paginilor WP (generat de database/completeaza_publicat_la.php, ' . date('Y-m-d') . ')'];
$n = 0;
foreach ($pdo->query("SELECT m.id, m.legacy_id, m.titlu FROM meniu m JOIN meniu p ON p.id = m.parent_id
    WHERE p.slug = 'noutati' AND m.legacy_id IS NOT NULL AND m.publicat_la IS NULL ORDER BY m.id") as $r) {
    $obj->execute(['id' => (int) $r['legacy_id']]);
    $data = $obj->fetchColumn();
    if (!$data) { continue; }
    $zi = substr((string) $data, 0, 10);
    $upd->execute(['d' => $zi, 'id' => (int) $r['id']]);
    $sql[] = sprintf('UPDATE meniu SET publicat_la = %s WHERE id = %d AND publicat_la IS NULL;', $pdo->quote($zi), (int) $r['id']);
    echo "#{$r['id']} $zi  {$r['titlu']}\n";
    $n++;
}
@mkdir($root . '/storage/migrare', 0775, true);
file_put_contents($root . '/storage/migrare/publicat_la.sql', implode("\n", $sql) . "\n");
echo "completate: $n (SQL pentru staging în storage/migrare/publicat_la.sql)\n";
