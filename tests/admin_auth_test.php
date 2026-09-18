<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo  = pdo();
app();
$auth = new App\Admin\Auth(new App\Database(settings()['db']));
$email  = 'test-' . bin2hex(random_bytes(4)) . '@example.com';
$parola = 'Parola-Test-' . bin2hex(random_bytes(4));
$pdo->prepare('INSERT INTO utilizatori (email, nume, parola_hash) VALUES (:e, :n, :h)')
    ->execute(['e' => $email, 'n' => 'Test', 'h' => password_hash($parola, PASSWORD_DEFAULT)]);
try {
    ok('parolă greșită => false', $auth->attempt($email, 'nu') === false);
    ok('email inexistent => false', $auth->attempt('nimeni@example.com', $parola) === false);
    ok('parolă goală => false', $auth->attempt($email, '') === false);
    ok('corect => true', $auth->attempt($email, $parola) === true);
    ok('check() după login', $auth->check() === true);
    ok('user() are email', ($auth->user()['email'] ?? null) === $email);
    ok('sesiunea nu conține hash', !str_contains(json_encode($_SESSION) ?: '', 'parola_hash'));
    $auth->logout();
    ok('după logout check() false', $auth->check() === false);

    // Rutele: fără sesiune, /admin redirectează la login
    $_SESSION = ['csrf' => 'abc'];
    $r = cerere('GET', '/admin');
    ok('GET /admin fără sesiune => 302', $r->getStatusCode() === 302);
    ok('  Location la /admin/login', str_ends_with($r->getHeaderLine('Location'), '/admin/login'));
    $r = cerere('GET', '/admin/login');
    ok('GET /admin/login => 200', $r->getStatusCode() === 200);
    ok('  formular cu _csrf', str_contains(corp($r), 'name="_csrf"'));

    // Login prin POST, CSRF greșit
    $r = cerere('POST', '/admin/login', ['_csrf' => 'gresit', 'email' => $email, 'parola' => $parola]);
    ok('POST login CSRF greșit => 200 cu eroare, nelogat', $r->getStatusCode() === 200 && !isset($_SESSION['admin_user']));
    // Login corect
    $r = cerere('POST', '/admin/login', ['_csrf' => 'abc', 'email' => $email, 'parola' => $parola]);
    ok('POST login corect => 302 la /admin', $r->getStatusCode() === 302 && str_ends_with($r->getHeaderLine('Location'), '/admin'));
    ok('  sesiune de admin pusă', isset($_SESSION['admin_user']['id']));
    $r = cerere('GET', '/admin');
    ok('GET /admin logat => 200', $r->getStatusCode() === 200);
    ok('  meniul lateral are Meniu/Fișiere/Setări/Utilizatori/Mesaje',
        substr_count(corp($r), 'class="adm-nav__link') >= 5);

    // Throttle: 5 eșecuri blochează
    $_ENV['IP_SALT'] = 'sare-test';
    $th = new App\Admin\LoginThrottle(new App\Database(settings()['db']), 'test-' . bin2hex(random_bytes(3)));
    $h  = ip_hash('127.0.0.1');
    for ($i = 0; $i < 5; $i++) { $th->record($h); }
    ok('throttle: 5 eșecuri => tooMany', $th->tooMany($h) === true);
    $th->clear($h);
    ok('throttle: clear => liber', $th->tooMany($h) === false);
    ok('throttle: ip null => fail-open', $th->tooMany(null) === false);
} finally {
    $pdo->exec('DELETE FROM utilizatori WHERE email = ' . $pdo->quote($email));
    $pdo->exec("DELETE FROM login_incercari WHERE scope LIKE 'test-%'");
}
final_test();
