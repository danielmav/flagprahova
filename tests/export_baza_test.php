<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$root = dirname(__DIR__);
$PHP  = 'C:/laragon/bin/php/php-8.3.31-nts-Win32-vs16-x64/php.exe';
$pdo  = pdo();
$m    = bin2hex(random_bytes(3));
$out  = "$root/storage/migrare/test-export-$m.sql";
$uid  = null;
try {
    // fără cont de client => refuz
    $are = (int) $pdo->query("SELECT COUNT(*) FROM utilizatori WHERE email <> 'admin@flagprahova.ro'")->fetchColumn();
    if ($are === 0) {
        exec("\"$PHP\" \"$root/scripts/export_baza.php\" --out=\"$out\" 2>&1", $o1, $c1);
        ok('fără cont de client => exit 1 cu mesaj', $c1 === 1 && str_contains(implode("\n", $o1), 'contul clientului'));
    }
    // cont temporar de client + un token + o încercare + un mesaj, care NU trebuie să ajungă în dump
    $pdo->prepare('INSERT INTO utilizatori (email, nume, parola_hash) VALUES (:e, "Client Test", "x")')->execute(['e' => "client-$m@example.com"]);
    $uid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO parola_tokens (utilizator_id, token_hash, expira_la) VALUES (:u, :h, NOW())')->execute(['u' => $uid, 'h' => str_repeat('a', 57) . $m . '0']);
    $pdo->prepare('INSERT INTO login_incercari (ip_hash, scope) VALUES (:h, "admin")')->execute(['h' => str_repeat('b', 58) . $m]);
    $pdo->prepare('INSERT INTO mesaje_contact (nume, email, mesaj) VALUES ("T", :e, "secret")')->execute(['e' => "mesaj-$m@example.com"]);

    exec("\"$PHP\" \"$root/scripts/export_baza.php\" --out=\"$out\" 2>&1", $o2, $c2);
    ok('export => exit 0', $c2 === 0);
    ok('  fișierul există și e mare', is_file($out) && filesize($out) > 100000);
    $sql = (string) file_get_contents($out);
    ok('  utf8mb4 + FK checks', str_contains($sql, 'SET NAMES utf8mb4') && str_contains($sql, 'FOREIGN_KEY_CHECKS=0') && str_contains($sql, 'FOREIGN_KEY_CHECKS=1'));
    foreach (['sectiuni', 'fisiere', 'meniu', 'galerie_imagini', 'setari', 'utilizatori', 'parola_tokens', 'login_incercari', 'mesaje_contact'] as $t) {
        ok("  CREATE TABLE `$t`", str_contains($sql, "CREATE TABLE `$t`"));
    }
    ok('  contul local NU e în dump', !str_contains($sql, 'admin@flagprahova.ro'));
    ok('  contul clientului E în dump', str_contains($sql, "client-$m@example.com"));
    ok('  tokenurile/încercările/mesajele NU sunt', !str_contains($sql, $m . '0') && !str_contains($sql, str_repeat('b', 58) . $m) && !str_contains($sql, "mesaj-$m@example.com"));
    ok('  meniul e complet', substr_count($sql, 'INSERT INTO `meniu`') >= 1 && str_contains($sql, 'cooperare'));
    ok('  ordinea: sectiuni înainte de meniu', strpos($sql, 'CREATE TABLE `sectiuni`') < strpos($sql, 'CREATE TABLE `meniu`'));
} finally {
    if ($uid) {
        $pdo->exec("DELETE FROM parola_tokens WHERE utilizator_id = $uid");
        $pdo->exec("DELETE FROM utilizatori WHERE id = $uid");
    }
    $pdo->prepare('DELETE FROM login_incercari WHERE ip_hash = :h')->execute(['h' => str_repeat('b', 58) . $m]);
    $pdo->prepare('DELETE FROM mesaje_contact WHERE email = :e')->execute(['e' => "mesaj-$m@example.com"]);
    @unlink($out);
}
final_test();
