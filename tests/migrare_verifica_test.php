<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require dirname(__DIR__) . '/database/verifica_migrare.php';
$pdo = pdo();
$sid = (int) $pdo->query("SELECT id FROM sectiuni WHERE slug='2014-2020'")->fetchColumn();
$marca = bin2hex(random_bytes(3));
$pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES ("x.pdf", :c, "application/pdf", 1)')->execute(['c' => "1999/01/lipsa-$marca.pdf"]);
$fid = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO meniu (sectiune_id, titlu, slug, tip, fisier_id) VALUES (:s, :t, :sl, "document", NULL)')->execute(['s' => $sid, 't' => "Fara fisier $marca", 'sl' => "fara-$marca"]);
$m1 = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO meniu (sectiune_id, titlu, slug, tip, fisier_id) VALUES (:s, :t, :sl, "document", :f)')->execute(['s' => $sid, 't' => "Fisier lipsa $marca", 'sl' => "lipsa-$marca", 'f' => $fid]);
$m2 = (int) $pdo->lastInsertId();
try {
    $r = verifica($pdo, settings()['upload']['dir']);
    ok('2 erori pentru cele 2 intrări', count(array_filter($r['erori'], fn($e) => str_contains($e, $marca))) === 2);
    ok('sumar are numărători pe tip', isset($r['sumar']['2014-2020']['document']));
    ok('documente_fara_fisier numara ambele intrari test', $r['documente_fara_fisier'] >= 2);
} finally {
    $pdo->exec("DELETE FROM meniu WHERE id IN ($m1,$m2)");
    $pdo->exec("DELETE FROM fisiere WHERE id = $fid");
}
final_test();
