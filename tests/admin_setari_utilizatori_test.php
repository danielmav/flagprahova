<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$uid = logheaza_test();
$db  = new App\Database(settings()['db']);
$log = dirname(__DIR__) . '/storage/logs/mail.log';
$email2 = 'invitat-' . bin2hex(random_bytes(3)) . '@example.com';
try {
    // Setări
    $r = cerere('GET', '/admin/setari');
    ok('GET setari => 200 cu câmpul email', $r->getStatusCode() === 200 && str_contains(corp($r), 'name="contact_email_destinatar"'));
    $vechi = (new App\Setari\Repository($db))->get('landing_titlu');
    $r = cerere('POST', '/admin/setari', ['_csrf' => 'abc', 'contact_email_destinatar' => 'x@y.ro', 'landing_titlu' => 'Titlu test', 'landing_text' => 't', 'footer_text' => 'f']);
    ok('POST setari => 302', $r->getStatusCode() === 302);
    ok('  valoarea salvată', (new App\Setari\Repository($db))->get('landing_titlu') === 'Titlu test');
    (new App\Setari\Repository($db))->set('landing_titlu', $vechi);
    (new App\Setari\Repository($db))->set('contact_email_destinatar', 'flagprahova@gmail.com');

    // Utilizatori: adaugă => cont fără parolă + email cu link
    $dim0 = is_file($log) ? filesize($log) : 0;
    $r = cerere('POST', '/admin/utilizatori/adauga', ['_csrf' => 'abc', 'email' => $email2, 'nume' => 'Coleg']);
    ok('adauga => 302', $r->getStatusCode() === 302);
    $u2 = $pdo->query('SELECT * FROM utilizatori WHERE email = ' . $pdo->quote($email2))->fetch();
    ok('  cont creat cu parola_hash gol', $u2 && $u2['parola_hash'] === '');
    ok('  token emis', (int) $pdo->query('SELECT COUNT(*) FROM parola_tokens WHERE utilizator_id = ' . (int) $u2['id'])->fetchColumn() === 1);
    clearstatcache();
    ok('  mail scris în mail.log (SMTP gol pe dev)', is_file($log) && filesize($log) > $dim0 && str_contains(file_get_contents($log), '/admin/parola/'));

    // extrage tokenul din log și setează parola (fără sesiune)
    preg_match_all('#/admin/parola/([a-f0-9]{64})#', file_get_contents($log), $m);
    $token = end($m[1]);
    $_SESSION = ['csrf' => 'abc'];
    $r = cerere('GET', '/admin/parola/' . $token);
    ok('GET parola/{token} => 200', $r->getStatusCode() === 200 && str_contains(corp($r), 'name="parola2"'));
    $r = cerere('POST', '/admin/parola/' . $token, ['_csrf' => 'abc', 'parola' => 'scurta', 'parola2' => 'scurta']);
    ok('parolă scurtă => 200 cu eroare', $r->getStatusCode() === 200 && str_contains(corp($r), '10'));
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
    $pdo->exec('DELETE FROM utilizatori WHERE email = ' . $pdo->quote($email2));
    $pdo->exec("DELETE FROM utilizatori WHERE id = $uid");
    $pdo->exec("DELETE FROM login_incercari WHERE scope = 'parola'");
}
final_test();
