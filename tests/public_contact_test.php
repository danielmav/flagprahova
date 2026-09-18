<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Form\TimeToken;

$pdo = pdo();
$s = $pdo->query("SELECT * FROM sectiuni WHERE slug='2021-2027'")->fetch();
$m = strtolower('Ct' . bin2hex(random_bytes(3)));
$_SESSION['csrf'] = 'abc';
$ids = []; $log = dirname(__DIR__) . '/storage/logs/mail.log';
try {
    $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, continut_html, sablon) VALUES (:s, NULL, 999, :t, :sl, "pagina", "<p>x</p>", "contact")')
        ->execute(['s' => $s['id'], 't' => "Contact $m", 'sl' => "contact-$m"]);
    $ids[] = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, continut_html) VALUES (:s, NULL, 999, :t, :sl, "pagina", "<p>y</p>")')
        ->execute(['s' => $s['id'], 't' => "Normala $m", 'sl' => "normala-$m"]);
    $ids[] = (int) $pdo->lastInsertId();
    $url = "/2021-2027/contact-$m";

    $r = cerere('GET', $url); $c = corp($r);
    ok('GET contact are formularul cu csrf, token, honeypot', str_contains($c, 'name="_csrf" value="abc"') && str_contains($c, 'name="_t"') && str_contains($c, 'name="website"') && str_contains($c, 'name="mesaj"'));
    ok('  setările de contact în sidebar', str_contains($c, '0762 609 685') || str_contains($c, 'fp-contact-rapid'));

    $tokVechi = (string) (time() - 10) . '.' . hash_hmac('sha256', (string) (time() - 10), 'abc');
    $bun = ['_csrf' => 'abc', '_t' => $tokVechi, 'website' => '', 'nume' => "Ion $m", 'email' => "ion-$m@example.com", 'mesaj' => "Salut, am o intrebare $m despre program."];
    $n0 = (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn();

    $r = cerere('POST', $url, ['_csrf' => 'gresit'] + $bun);
    ok('CSRF greșit => 200 cu eroare, nimic salvat', $r->getStatusCode() === 200 && str_contains(corp($r), 'Sesiunea a expirat') && (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn() === $n0);

    $r = cerere('POST', $url, ['nume' => 'I', 'email' => 'nu-e-email', 'mesaj' => 'scurt'] + $bun);
    $c = corp($r);
    ok('validare: 3 erori, valorile păstrate', $r->getStatusCode() === 200 && str_contains($c, 'is-invalid') && substr_count($c, 'invalid-feedback') >= 3 && str_contains($c, 'value="nu-e-email"'));
    ok('  nimic salvat', (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn() === $n0);

    $r = cerere('POST', $url, ['website' => 'http://spam'] + $bun);
    ok('honeypot plin => 302 „succes" fals, nimic salvat', $r->getStatusCode() === 302 && (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn() === $n0);
    $r = cerere('POST', $url, ['_t' => TimeToken::mint()] + $bun);
    ok('token prea proaspăt => 302 fals, nimic salvat', $r->getStatusCode() === 302 && (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn() === $n0);

    $r = cerere('POST', $url, $bun);
    ok('trimitere validă => 302 cu ?trimis=1', $r->getStatusCode() === 302 && $r->getHeaderLine('Location') === "$url?trimis=1#formular");
    $row = $pdo->query("SELECT * FROM mesaje_contact WHERE email = 'ion-$m@example.com'")->fetch();
    ok('  salvat cu secțiunea și marcat trimis (SMTP gol => log)', $row && (int) $row['sectiune_id'] === (int) $s['id'] && (int) $row['email_trimis'] === 1);
    ok('  emailul e în mail.log', is_file($log) && str_contains((string) file_get_contents($log), "intrebare $m"));
    $r = cerere('GET', "$url?trimis=1");
    ok('GET ?trimis=1 arată confirmarea', str_contains(corp($r), 'Mesajul a fost trimis'));

    $r = cerere('POST', "/2021-2027/normala-$m", $bun);
    ok('POST pe pagină fără șablon contact => 404', $r->getStatusCode() === 404);

    // Throttle: 5 mesaje pe oră de pe același IP, apoi „succes" tăcut fără salvare.
    $ip = ip_hash('127.0.0.1');
    if ($ip === null) {
        ok('IP_SALT gol => throttle-ul e sărit (ip_hash null), notat explicit', true);
    } else {
        $nAnte = (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn();
        $umple = $pdo->prepare("INSERT INTO mesaje_contact (sectiune_id, nume, email, mesaj, ip_hash) VALUES (:s, 'Flood', :e, 'x', :ip)");
        for ($i = 0; $i < 4; $i++) {
            $umple->execute(['s' => $s['id'], 'e' => "flood-$i-$m@example.com", 'ip' => $ip]);
        }
        ok('  4 mesaje adăugate pe același IP (total 5 în ultima oră)', (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn() === $nAnte + 4);
        $nPrag = (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn();
        $r = cerere('POST', $url, ['email' => "peste-prag-$m@example.com"] + $bun);
        ok('al 6-lea mesaj/oră => 302 tăcut, nimic salvat', $r->getStatusCode() === 302
            && (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn() === $nPrag
            && (int) $pdo->query("SELECT COUNT(*) FROM mesaje_contact WHERE email = 'peste-prag-$m@example.com'")->fetchColumn() === 0);
    }
} finally {
    $pdo->exec("DELETE FROM mesaje_contact WHERE email LIKE '%-$m@example.com'");
    $pdo->exec("DELETE FROM mesaje_contact WHERE email = 'ion-$m@example.com'");
    if ($ids) { $pdo->exec('DELETE FROM meniu WHERE id IN (' . implode(',', $ids) . ')'); }
}
final_test();
