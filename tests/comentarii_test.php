<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Form\TimeToken;

$pdo = pdo();
$s = $pdo->query("SELECT * FROM sectiuni WHERE slug='2021-2027'")->fetch();
$m = strtolower('Cm' . bin2hex(random_bytes(3)));
$admin = settings()['admin']['path'];
$log = dirname(__DIR__) . '/storage/logs/mail.log';
$ids = []; $uid = null;
try {
    $ins = $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, comentarii) VALUES (:s, NULL, 999, :t, :sl, "dosar", :c)');
    $ins->execute(['s' => $s['id'], 't' => "Ghid $m", 'sl' => "ghid-$m", 'c' => 1]);
    $ids[] = $ghid = (int) $pdo->lastInsertId();
    $ins->execute(['s' => $s['id'], 't' => "Dosar $m", 'sl' => "dosar-$m", 'c' => 0]);
    $ids[] = (int) $pdo->lastInsertId();
    $url = "/2021-2027/ghid-$m";
    $_SESSION = ['csrf' => 'abc'];

    $c = corp(cerere('GET', $url));
    ok('dosar cu comentarii: secțiunea și formularul', str_contains($c, 'id="intrebari"') && str_contains($c, "action=\"/2021-2027/ghid-$m/intrebari#intrebari\"") && str_contains($c, 'name="acord"') && str_contains($c, 'name="website"'));
    ok('dosar fără comentarii: fără secțiune', !str_contains(corp(cerere('GET', "/2021-2027/dosar-$m")), 'id="intrebari"'));

    // Meniu: ghidul (dosar cu comentarii) e link simplu, fără submeniu; dosarul obișnuit își păstrează submeniul.
    $copil = $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip) VALUES (:s, :p, 0, :t, :sl, "pagina")');
    $copil->execute(['s' => $s['id'], 'p' => $ghid, 't' => "Anexa $m", 'sl' => "anexa-$m"]);
    $ids[] = (int) $pdo->lastInsertId();
    $copil->execute(['s' => $s['id'], 'p' => $ids[1], 't' => "Fiu $m", 'sl' => "fiu-$m"]);
    $ids[] = (int) $pdo->lastInsertId();
    $c = corp(cerere('GET', '/2021-2027/'));
    ok('meniu: ghidul fără submeniu, dosarul obișnuit cu submeniu', !str_contains($c, "Anexa $m") && str_contains($c, "Fiu $m"));
    ok('pagina ghidului listează în continuare anexa', str_contains(corp(cerere('GET', $url)), "Anexa $m"));

    $tok = (string) (time() - 10) . '.' . hash_hmac('sha256', (string) (time() - 10), 'abc');
    $bun = ['_csrf' => 'abc', '_t' => $tok, 'website' => '', 'nume' => "Ana $m", 'email' => "ana-$m@example.com", 'mesaj' => "Intrebare $m despre eligibilitate?", 'acord' => '1'];
    $n = fn() => (int) $pdo->query("SELECT COUNT(*) FROM comentarii WHERE meniu_id = $ghid")->fetchColumn();

    $r = cerere('POST', "$url/intrebari", ['_csrf' => 'gresit'] + $bun);
    ok('CSRF greșit => eroare, nimic salvat', $r->getStatusCode() === 200 && str_contains(corp($r), 'Sesiunea a expirat') && $n() === 0);
    $r = cerere('POST', "$url/intrebari", ['acord' => '', 'mesaj' => 'scurt'] + $bun);
    $c = corp($r);
    ok('fără acord + mesaj scurt => 2 erori, valorile păstrate', str_contains($c, 'Bifează acordul') && str_contains($c, 'între 10 și 5000') && str_contains($c, "value=\"Ana $m\"") && $n() === 0);
    ok('honeypot => 302 fals, nimic salvat', cerere('POST', "$url/intrebari", ['website' => 'x'] + $bun)->getStatusCode() === 302 && $n() === 0);
    ok('token proaspăt => 302 fals, nimic salvat', cerere('POST', "$url/intrebari", ['_t' => TimeToken::mint()] + $bun)->getStatusCode() === 302 && $n() === 0);
    ok('POST pe dosar fără comentarii => 404', cerere('POST', "/2021-2027/dosar-$m/intrebari", $bun)->getStatusCode() === 404);

    $r = cerere('POST', "$url/intrebari", $bun);
    ok('trimitere validă => 302 ?trimis=1#intrebari', $r->getStatusCode() === 302 && $r->getHeaderLine('Location') === "$url?trimis=1#intrebari");
    $row = $pdo->query("SELECT * FROM comentarii WHERE meniu_id = $ghid")->fetch();
    ok('  salvat ca „nou”, email trimis', $row && $row['stare'] === 'nou' && (int) $row['email_trimis'] === 1);
    ok('  notificarea are linkul de admin', str_contains((string) file_get_contents($log), "$admin/comentarii/{$row['id']}"));
    $c = corp(cerere('GET', "$url?trimis=1"));
    ok('  confirmarea apare, mesajul NU e public încă', str_contains($c, 'va fi publicat după verificare') && !str_contains($c, "Intrebare $m"));

    // Admin
    $uid = logheaza_test();
    $c = corp(cerere('GET', "$admin/comentarii"));
    ok('admin: lista cu DataTables și mesajul', str_contains($c, 'data-dt') && str_contains($c, 'dataTables.min.js') && str_contains($c, "ana-$m@example.com"));
    ok('admin: filtrul „publicat” nu-l arată', !str_contains(corp(cerere('GET', "$admin/comentarii?stare=publicat")), "ana-$m@example.com"));
    $cid = (int) $row['id'];
    $r = cerere('POST', "$admin/comentarii/$cid/aproba", ['_csrf' => 'gresit']);
    ok('admin: aprobare cu CSRF greșit => rămâne nou', $pdo->query("SELECT stare FROM comentarii WHERE id = $cid")->fetchColumn() === 'nou');
    cerere('POST', "$admin/comentarii/$cid/aproba", ['_csrf' => 'abc']);
    ok('admin: aprobat => publicat', $pdo->query("SELECT stare FROM comentarii WHERE id = $cid")->fetchColumn() === 'publicat');
    $c = corp(cerere('GET', $url));
    ok('public: mesajul apare, emailul nu', str_contains($c, "Intrebare $m") && str_contains($c, "Ana $m") && !str_contains($c, "ana-$m@example.com"));

    ok('admin: pagina de editare', str_contains(corp(cerere('GET', "$admin/comentarii/$cid")), 'name="raspuns"'));
    cerere('POST', "$admin/comentarii/$cid", ['_csrf' => 'abc', 'stare' => 'publicat', 'nume' => "Ana $m", 'mesaj' => "Intrebare $m <b>x</b>", 'raspuns' => "Raspuns $m\nrandul 2"]);
    $r2 = $pdo->query("SELECT * FROM comentarii WHERE id = $cid")->fetch();
    ok('admin: răspuns salvat cu data', $r2['raspuns'] === "Raspuns $m\nrandul 2" && $r2['raspuns_la'] !== null);
    $c = corp(cerere('GET', $url));
    ok('public: răspunsul FLAG apare, HTML-ul e escapat', str_contains($c, 'Răspuns FLAG Prahova') && str_contains($c, "Raspuns $m<br />") && str_contains($c, '&lt;b&gt;x&lt;/b&gt;'));
    cerere('POST', "$admin/comentarii/$cid", ['_csrf' => 'abc', 'stare' => 'respins', 'nume' => "Ana $m", 'mesaj' => "Intrebare $m", 'raspuns' => '']);
    $r3 = $pdo->query("SELECT * FROM comentarii WHERE id = $cid")->fetch();
    ok('admin: respins + răspuns golit', $r3['stare'] === 'respins' && $r3['raspuns'] === null && $r3['raspuns_la'] === null);
    ok('public: respinsul dispare', !str_contains(corp(cerere('GET', $url)), "Intrebare $m"));
    cerere('POST', "$admin/comentarii/$cid/sterge", ['_csrf' => 'abc']);
    ok('admin: șters', $n() === 0);

    // Bifa din formularul de meniu: se păstrează doar pe dosare.
    cerere('POST', "$admin/meniu/salveaza", ['_csrf' => 'abc', 'id' => $ids[1], 'titlu' => "Dosar $m", 'slug' => "dosar-$m", 'tip' => 'dosar', 'vizibil' => '1', 'comentarii' => '1']);
    ok('meniu: dosar + bifă => comentarii = 1', (int) $pdo->query("SELECT comentarii FROM meniu WHERE id = {$ids[1]}")->fetchColumn() === 1);
    cerere('POST', "$admin/meniu/salveaza", ['_csrf' => 'abc', 'id' => $ids[1], 'titlu' => "Dosar $m", 'slug' => "dosar-$m", 'tip' => 'pagina', 'vizibil' => '1', 'comentarii' => '1']);
    ok('meniu: pagină => comentarii = 0', (int) $pdo->query("SELECT comentarii FROM meniu WHERE id = {$ids[1]}")->fetchColumn() === 0);
    ok('meniu: formularul are bifa', str_contains(corp(cerere('GET', "$admin/meniu/$ghid")), 'name="comentarii" value="1" checked'));

    // Throttle: 5/oră/IP.
    $_SESSION = ['csrf' => 'abc'];
    $ip = ip_hash('127.0.0.1');
    if ($ip === null) {
        ok('IP_SALT gol => throttle sărit (notat)', true);
    } else {
        $umple = $pdo->prepare("INSERT INTO comentarii (meniu_id, nume, email, mesaj, ip_hash) VALUES (:m, 'Flood', 'f@example.com', 'x', :ip)");
        for ($i = 0; $i < 5; $i++) { $umple->execute(['m' => $ghid, 'ip' => $ip]); }
        $r = cerere('POST', "$url/intrebari", $bun);
        ok('al 6-lea/oră => 302 tăcut, nesalvat', $r->getStatusCode() === 302 && $n() === 5);
    }
} finally {
    if ($ids) { $pdo->exec('DELETE FROM meniu WHERE id IN (' . implode(',', $ids) . ')'); }
    if ($uid) { $pdo->exec("DELETE FROM utilizatori WHERE id = $uid"); }
}
final_test();
