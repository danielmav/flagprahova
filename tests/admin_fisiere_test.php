<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$uid = logheaza_test();
$dir = settings()['upload']['dir'];
$creat = [];
// Slim\Psr7\UploadedFile::moveTo() face rename() pe sursă sub CLI => „consumă" fișierul.
// Copiem fixture-ul într-un temporar înainte de fiecare upload simulat, ca fixture-ele comise să rămână intacte.
$fx = function (string $nume): string {
    $tmp = sys_get_temp_dir() . "/fp-fx-" . bin2hex(random_bytes(4)) . "-" . $nume;
    copy(__DIR__ . "/fixtures/" . $nume, $tmp);
    return $tmp;
};
$numara = fn(): int => (int) $pdo->query('SELECT COUNT(*) FROM fisiere')->fetchColumn();
$flash = fn(): array => ['tip' => $_SESSION['flash']['tip'] ?? '', 'mesaj' => $_SESSION['flash']['mesaj'] ?? ''];
try {
    $r = cerere('GET', '/admin/fisiere');
    ok('GET /admin/fisiere => 200', $r->getStatusCode() === 200);
    ok('  are formular de încărcare multiplă', str_contains(corp($r), 'name="fisiere[]"') && str_contains(corp($r), 'multiple'));

    $r = cerere('POST', '/admin/fisiere/incarca', ['_csrf' => 'abc'], ['fisiere' => ['cale' => $fx('mic.pdf'), 'nume' => 'Test upload ' . bin2hex(random_bytes(2)) . '.pdf']]);
    ok('POST incarca => 302', $r->getStatusCode() === 302);
    $f = $pdo->query("SELECT * FROM fisiere WHERE nume_afisat LIKE 'Test upload %' ORDER BY id DESC LIMIT 1")->fetch();
    ok('  fișier înregistrat cu incarcat_de = utilizatorul', $f && (int) $f['incarcat_de'] === $uid);
    if ($f) { $creat[] = (int) $f['id']; }
    ok('  există pe disc', $f && is_file($dir . '/' . $f['cale']));

    $r = cerere('GET', '/admin/fisiere?picker=1');
    ok('picker: fără sidebar', !str_contains(corp($r), 'adm-nav__link'));
    ok('picker: rândurile au data-fisier-id', str_contains(corp($r), 'data-fisier-id="' . $f['id'] . '"'));

    $r = cerere('POST', '/admin/fisiere/' . $f['id'] . '/redenumeste', ['_csrf' => 'abc', 'nume_afisat' => 'Redenumit.pdf']);
    ok('redenumeste => 302', $r->getStatusCode() === 302);
    ok('  nume schimbat', $pdo->query('SELECT nume_afisat FROM fisiere WHERE id = ' . (int) $f['id'])->fetchColumn() === 'Redenumit.pdf');

    $r = cerere('POST', '/admin/fisiere/' . $f['id'] . '/redenumeste', ['_csrf' => 'abc', 'nume_afisat' => '   ']);
    ok('redenumeste cu nume gol => refuzat, nume neschimbat', $pdo->query('SELECT nume_afisat FROM fisiere WHERE id = ' . (int) $f['id'])->fetchColumn() === 'Redenumit.pdf');
    ok('  flash de eroare „Numele nu poate fi gol.”', $flash()['tip'] === 'eroare' && str_contains($flash()['mesaj'], 'Numele nu poate fi gol'));

    // CSRF invalid pe fiecare acțiune care scrie: nimic modificat + flash de eroare
    $inainte = $numara();
    $r = cerere('POST', '/admin/fisiere/incarca', ['_csrf' => 'gresit'], ['fisiere' => ['cale' => $fx('mic.pdf'), 'nume' => 'Csrf gresit.pdf']]);
    ok('incarca CSRF greșit => niciun fișier nou', $numara() === $inainte);
    ok('  flash de eroare (sesiune expirată)', $flash()['tip'] === 'eroare' && str_contains($flash()['mesaj'], 'expirat'));

    $r = cerere('POST', '/admin/fisiere/' . $f['id'] . '/redenumeste', ['_csrf' => 'gresit', 'nume_afisat' => 'Nu trebuie.pdf']);
    ok('redenumeste CSRF greșit => nume neschimbat', $pdo->query('SELECT nume_afisat FROM fisiere WHERE id = ' . (int) $f['id'])->fetchColumn() === 'Redenumit.pdf');
    ok('  flash de eroare (sesiune expirată)', $flash()['tip'] === 'eroare' && str_contains($flash()['mesaj'], 'expirat'));

    $r = cerere('POST', '/admin/fisiere/' . $f['id'] . '/sterge', ['_csrf' => 'gresit']);
    ok('sterge CSRF greșit => rândul rămâne', $pdo->query('SELECT COUNT(*) FROM fisiere WHERE id = ' . (int) $f['id'])->fetchColumn() == 1);
    ok('  flash de eroare (sesiune expirată)', $flash()['tip'] === 'eroare' && str_contains($flash()['mesaj'], 'expirat'));

    // fișier folosit de o intrare de meniu => ștergerea e refuzată
    $sid = (int) $pdo->query("SELECT id FROM sectiuni WHERE slug='2014-2020'")->fetchColumn();
    $pdo->prepare('INSERT INTO meniu (sectiune_id, titlu, slug, tip, fisier_id) VALUES (:s, :t, :sl, "document", :f)')
        ->execute(['s' => $sid, 't' => 'Test doc', 'sl' => 'test-doc-' . bin2hex(random_bytes(2)), 'f' => $f['id']]);
    $mid = (int) $pdo->lastInsertId();
    $r = cerere('POST', '/admin/fisiere/' . $f['id'] . '/sterge', ['_csrf' => 'abc']);
    ok('sterge folosit => refuzat (rândul rămâne)', $pdo->query('SELECT COUNT(*) FROM fisiere WHERE id = ' . (int) $f['id'])->fetchColumn() == 1);
    ok('  flash de eroare cu titlul intrării', ($_SESSION['flash']['tip'] ?? '') === 'eroare' && str_contains($_SESSION['flash']['mesaj'] ?? '', 'Test doc'));
    $pdo->exec("DELETE FROM meniu WHERE id = $mid");
    $r = cerere('POST', '/admin/fisiere/' . $f['id'] . '/sterge', ['_csrf' => 'abc']);
    ok('sterge nefolosit => șters din DB și disc', $pdo->query('SELECT COUNT(*) FROM fisiere WHERE id = ' . (int) $f['id'])->fetchColumn() == 0 && !is_file($dir . '/' . $f['cale']));
    $creat = [];

    // încărcare multiplă reală: două fișiere în același câmp `fisiere[]`, unul respins
    $inainte = $numara();
    $sufix = bin2hex(random_bytes(2));
    $r = cerere('POST', '/admin/fisiere/incarca', ['_csrf' => 'abc'], ['fisiere' => [
        ['cale' => $fx('mic.png'), 'nume' => "Multiplu ok $sufix.png"],
        ['cale' => $fx('rau.php.pdf'), 'nume' => "Multiplu rau $sufix.pdf"],
    ]]);
    ok('incarca multiplu => 302', $r->getStatusCode() === 302);
    ok('  un singur rând nou (al doilea respins)', $numara() === $inainte + 1);
    $fm = $pdo->query("SELECT * FROM fisiere WHERE nume_afisat LIKE 'Multiplu %' ORDER BY id DESC LIMIT 1")->fetch();
    if ($fm) { $creat[] = (int) $fm['id']; }
    ok('  rândul nou e cel acceptat (png)', $fm && $fm['nume_afisat'] === "Multiplu ok $sufix.png" && $fm['mime'] === 'image/png');
    ok('  flash: 1 reușit + „Respinse”', str_contains($flash()['mesaj'], '1 fișier') && str_contains($flash()['mesaj'], 'Respinse'));

    // upload din editor
    $r = cerere('POST', '/admin/fisiere/editor', ['_csrf' => 'abc'], ['imagine' => ['cale' => $fx('mic.png'), 'nume' => 'editor.png']]);
    $j = json_decode(corp($r), true);
    ok('editor: 200 + url', $r->getStatusCode() === 200 && str_starts_with((string) ($j['url'] ?? ''), '/fisiere/'));
    $fe = $pdo->query("SELECT * FROM fisiere WHERE cale LIKE '%/editor%.png' ORDER BY id DESC LIMIT 1")->fetch();
    if ($fe) { $creat[] = (int) $fe['id']; }
    $r = cerere('POST', '/admin/fisiere/editor', ['_csrf' => 'abc'], ['imagine' => ['cale' => $fx('mic.pdf'), 'nume' => 'nu.pdf']]);
    ok('editor: pdf => 422', $r->getStatusCode() === 422);
    $r = cerere('POST', '/admin/fisiere/editor', ['_csrf' => 'gresit'], ['imagine' => ['cale' => $fx('mic.png'), 'nume' => 'x.png']]);
    ok('editor: CSRF greșit => 403', $r->getStatusCode() === 403);
} finally {
    foreach ($creat as $id) {
        $f = $pdo->query("SELECT cale FROM fisiere WHERE id = $id")->fetch();
        if ($f && is_file($dir . '/' . $f['cale'])) { unlink($dir . '/' . $f['cale']); }
        $pdo->exec("DELETE FROM fisiere WHERE id = $id");
    }
    $pdo->exec("DELETE FROM utilizatori WHERE id = $uid");
}
final_test();
