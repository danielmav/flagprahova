<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$uid = logheaza_test();
$db  = new App\Database(settings()['db']);
$log = dirname(__DIR__) . '/storage/logs/mail.log';
$email2 = 'invitat-' . bin2hex(random_bytes(3)) . '@example.com';
// Testul POSTează pe /admin/setari TOATE cheile, deci suprascrie și `landing_text`
// și `footer_text`. Luăm un instantaneu complet și îl punem la loc în `finally`, ca
// să nu rămână urme în baza REALĂ pe care rulează suita.
$setariRepo = new App\Setari\Repository($db);
$setariVechi = [];
foreach (App\Setari\Repository::CHEI as $cheie) { $setariVechi[$cheie] = $setariRepo->get($cheie); }
try {
    // Setări
    $r = cerere('GET', '/admin/setari');
    ok('GET setari => 200 cu câmpul email', $r->getStatusCode() === 200 && str_contains(corp($r), 'name="contact_email_destinatar"'));
    $r = cerere('POST', '/admin/setari', ['_csrf' => 'abc', 'contact_email_destinatar' => 'x@y.ro', 'landing_titlu' => 'Titlu test', 'landing_text' => 't', 'footer_text' => 'f']);
    ok('POST setari => 302', $r->getStatusCode() === 302);
    ok('  valoarea salvată', (new App\Setari\Repository($db))->get('landing_titlu') === 'Titlu test');
    // Restaurarea (exact ce era înainte, nu valori hard-codate) se face în `finally`.

    // Utilizatori: adaugă => cont fără parolă + email cu link
    $dim0 = is_file($log) ? filesize($log) : 0;
    $r = cerere('POST', '/admin/utilizatori/adauga', ['_csrf' => 'abc', 'email' => $email2, 'nume' => 'Coleg']);
    ok('adauga => 302', $r->getStatusCode() === 302);
    $u2 = $pdo->query('SELECT * FROM utilizatori WHERE email = ' . $pdo->quote($email2))->fetch();
    ok('  cont creat cu parola_hash gol', $u2 && $u2['parola_hash'] === '');
    ok('  token emis', (int) $pdo->query('SELECT COUNT(*) FROM parola_tokens WHERE utilizator_id = ' . (int) $u2['id'])->fetchColumn() === 1);
    // Invitația de cont e valabilă 7 zile (spre deosebire de „parolă uitată”, mai jos).
    ok('  invitația e valabilă ≥ 6 zile', (int) $pdo->query('SELECT COUNT(*) FROM parola_tokens WHERE utilizator_id = ' . (int) $u2['id']
        . ' AND folosit_la IS NULL AND expira_la >= NOW() + INTERVAL 6 DAY')->fetchColumn() === 1);
    clearstatcache();
    ok('  mail scris în mail.log (SMTP gol pe dev)', is_file($log) && filesize($log) > $dim0 && str_contains(file_get_contents($log), '/admin/parola/'));

    // extrage tokenul din log și setează parola (fără sesiune)
    preg_match_all('#/admin/parola/([a-f0-9]{64})#', file_get_contents($log), $m);
    $token = end($m[1]);
    $_SESSION = ['csrf' => 'abc'];
    $r = cerere('GET', '/admin/parola/' . $token);
    ok('GET parola/{token} => 200', $r->getStatusCode() === 200 && str_contains(corp($r), 'name="parola2"'));
    $r = cerere('POST', '/admin/parola/' . $token, ['_csrf' => 'abc', 'parola' => 'scurta', 'parola2' => 'scurta']);
    ok('parolă scurtă => 200 cu eroare', $r->getStatusCode() === 200 && str_contains(corp($r), 'cel puțin 10 caractere'));
    $r = cerere('POST', '/admin/parola/' . $token, ['_csrf' => 'abc', 'parola' => 'Parola-Lunga-123', 'parola2' => 'Parola-Lunga-123']);
    ok('parolă bună => 302 la login?ok=parola', $r->getStatusCode() === 302 && str_contains($r->getHeaderLine('Location'), 'ok=parola'));
    ok('  login-ul merge cu noua parolă', (new App\Admin\Auth($db))->attempt($email2, 'Parola-Lunga-123') === true);
    $r = cerere('GET', '/admin/parola/' . $token);
    ok('  tokenul nu mai e valabil', $r->getStatusCode() === 302 || str_contains(corp($r), 'expirat'));

    // parolă uitată: același mesaj pentru email inexistent
    $_SESSION = ['csrf' => 'abc'];
    $r1 = cerere('POST', '/admin/parola-uitata', ['_csrf' => 'abc', 'email' => $email2]);
    $r2 = cerere('POST', '/admin/parola-uitata', ['_csrf' => 'abc', 'email' => 'nimeni-' . bin2hex(random_bytes(2)) . '@example.com']);
    ok('parola-uitata: răspuns identic', corp($r1) === corp($r2) && str_contains(corp($r1), 'Dacă adresa există'));
    ok('  pagina anunță 30 de minute', str_contains(corp($r1), 'valabil 30 de minute'));

    // TTL de resetare: 30 min, nu 7 zile ca invitația. Ambele momente vin din
    // ceasul MySQL, ca să nu comparăm fusuri diferite.
    $expReset = strtotime((string) $pdo->query('SELECT expira_la FROM parola_tokens WHERE utilizator_id = ' . (int) $u2['id'] . ' ORDER BY id DESC LIMIT 1')->fetchColumn());
    $acumDb   = strtotime((string) $pdo->query('SELECT NOW()')->fetchColumn());
    ok('  tokenul de resetare expiră în ≤ 31 min', $expReset > $acumDb && ($expReset - $acumDb) <= 31 * 60);
    ok('  emailul de resetare spune „30 de minute”', str_contains(file_get_contents($log), 'valabil 30 de minute'));

    $u2id  = (int) $u2['id'];
    $tokrepo = new App\Admin\PasswordTokenRepository($db);
    $numaraTokens = static fn(): int => (int) pdo()->query('SELECT COUNT(*) FROM parola_tokens WHERE utilizator_id = ' . $u2id)->fetchColumn();

    // repository: issue(.., false) NU arde invitația veche; pastreazaDoar() o arde
    $vechi = $tokrepo->issue($u2id);
    $nou   = $tokrepo->issue($u2id, false);
    ok('issue(false) lasă tokenul vechi valabil', $tokrepo->esteValabil($vechi) && $tokrepo->esteValabil($nou));
    $tokrepo->invalideazaToken($nou);
    ok('  email eșuat => moare doar tokenul nou', !$tokrepo->esteValabil($nou) && $tokrepo->esteValabil($vechi));
    $nou2 = $tokrepo->issue($u2id, false);
    $tokrepo->pastreazaDoar($u2id, $nou2);
    ok('  pastreazaDoar() arde vechiul, păstrează noul', !$tokrepo->esteValabil($vechi) && $tokrepo->esteValabil($nou2));

    // token expirat => invalid, iar GET redirectează
    $pdo->exec('UPDATE parola_tokens SET expira_la = NOW() - INTERVAL 1 DAY WHERE utilizator_id = ' . $u2id . ' AND folosit_la IS NULL');
    ok('token expirat => esteValabil false', $tokrepo->esteValabil($nou2) === false);
    $r = cerere('GET', '/admin/parola/' . $nou2);
    ok('  GET pe token expirat => 302', $r->getStatusCode() === 302);

    // POST cu CSRF greșit pe /parola/{token} nu schimbă parola
    $tokCsrf = $tokrepo->issue($u2id);
    $r = cerere('POST', '/admin/parola/' . $tokCsrf, ['_csrf' => 'gresit', 'parola' => 'Alta-Parola-999', 'parola2' => 'Alta-Parola-999']);
    ok('parola/{token} cu CSRF greșit => 200, parola neschimbată', $r->getStatusCode() === 200
        && (new App\Admin\Auth($db))->attempt($email2, 'Parola-Lunga-123') === true
        && (new App\Admin\Auth($db))->attempt($email2, 'Alta-Parola-999') === false);
    ok('  tokenul rămâne valabil după CSRF greșit', $tokrepo->esteValabil($tokCsrf));

    // POST fără CSRF valid pe /parola-uitata nu emite token
    $_SESSION = ['csrf' => 'abc'];
    $inainte = $numaraTokens();
    $r = cerere('POST', '/admin/parola-uitata', ['_csrf' => 'gresit', 'email' => $email2]);
    ok('parola-uitata cu CSRF greșit => niciun token nou', $r->getStatusCode() === 200 && $numaraTokens() === $inainte);

    // throttle scope 'parola': după 5 încercări, a 6-a nu mai emite token
    $pdo->exec("DELETE FROM login_incercari WHERE scope = 'parola'");
    for ($i = 0; $i < 5; $i++) {
        cerere('POST', '/admin/parola-uitata', ['_csrf' => 'abc', 'email' => $email2]);
    }
    ok('throttle: 5 încercări înregistrate', (int) $pdo->query("SELECT COUNT(*) FROM login_incercari WHERE scope = 'parola'")->fetchColumn() === 5);
    $inainte = $numaraTokens();
    cerere('POST', '/admin/parola-uitata', ['_csrf' => 'abc', 'email' => $email2]);
    ok('  a 6-a e blocată => niciun token nou', $numaraTokens() === $inainte);

    // ștergere: nu pe sine, nu ultimul
    $_SESSION = ['csrf' => 'abc', 'admin_user' => ['id' => $uid, 'email' => 'x', 'nume' => 'Test']];
    $r = cerere('POST', '/admin/utilizatori/' . $uid . '/sterge', ['_csrf' => 'abc']);
    ok('sterge pe sine => refuzat', (int) $pdo->query("SELECT COUNT(*) FROM utilizatori WHERE id = $uid")->fetchColumn() === 1);
    $r = cerere('POST', '/admin/utilizatori/' . $u2['id'] . '/sterge', ['_csrf' => 'abc']);
    ok('sterge alt cont => șters', (int) $pdo->query('SELECT COUNT(*) FROM utilizatori WHERE id = ' . (int) $u2['id'])->fetchColumn() === 0);

    // Mesaje
    $pdo->prepare('INSERT INTO mesaje_contact (nume, email, mesaj) VALUES (:n, :e, :m)')->execute(['n' => 'Ion', 'e' => 'ion@example.com', 'm' => "Mesaj test $uid"]);
    $mid = (int) $pdo->lastInsertId();
    $r = cerere('GET', '/admin/mesaje');
    ok('GET mesaje listează mesajul', $r->getStatusCode() === 200 && str_contains(corp($r), "Mesaj test $uid"));
    $pdo->exec("DELETE FROM mesaje_contact WHERE id = $mid");
} finally {
    $restaurare = new App\Setari\Repository($db);
    foreach ($setariVechi as $cheie => $valoare) { $restaurare->set($cheie, $valoare); }
    $pdo->exec('DELETE FROM utilizatori WHERE email = ' . $pdo->quote($email2));
    $pdo->exec("DELETE FROM utilizatori WHERE id = $uid");
    $pdo->exec("DELETE FROM login_incercari WHERE scope = 'parola'");
}
final_test();
