<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$coloane = function (string $tabel) use ($pdo): array {
    $st = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t');
    $st->execute(['t' => $tabel]);
    return array_map(fn($r) => $r['COLUMN_NAME'], $st->fetchAll());
};
$asteptat = [
    'sectiuni'        => ['id','slug','titlu','subtitlu','acasa_html','hero_imagine','ordine'],
    'meniu'           => ['id','sectiune_id','parent_id','ordine','titlu','slug','tip','continut_html','fisier_id','url','sablon','vizibil','legacy_id','creat_la','modificat_la'],
    'fisiere'         => ['id','nume_afisat','cale','mime','marime','incarcat_la','incarcat_de','legacy_url'],
    'galerie_imagini' => ['id','meniu_id','fisier_id','ordine','legenda'],
    'utilizatori'     => ['id','email','nume','parola_hash','ultimul_login','creat_la'],
    'parola_tokens'   => ['id','utilizator_id','token_hash','expira_la','folosit_la','creat_la'],
    'login_incercari' => ['id','ip_hash','scope','la'],
    'setari'          => ['cheie','valoare'],
    'mesaje_contact'  => ['id','sectiune_id','nume','email','mesaj','ip_hash','trimis_la','email_trimis'],
];
foreach ($asteptat as $tabel => $cols) {
    $are = $coloane($tabel);
    ok("tabela $tabel există", $are !== []);
    foreach ($cols as $c) {
        ok("  $tabel.$c", in_array($c, $are, true));
    }
}
$n = (int) $pdo->query("SELECT COUNT(*) FROM sectiuni WHERE slug IN ('2021-2027','2014-2020')")->fetchColumn();
ok('seed: 2 secțiuni', $n === 2);
$ordine = $pdo->query("SELECT slug FROM sectiuni ORDER BY ordine")->fetchAll(PDO::FETCH_COLUMN);
ok('seed: 2021-2027 prima', $ordine === ['2021-2027', '2014-2020']);
ok('seed: setare contact_email_destinatar', $pdo->query("SELECT COUNT(*) FROM setari WHERE cheie='contact_email_destinatar'")->fetchColumn() == 1);
final_test();
