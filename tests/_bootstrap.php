<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UploadedFileFactory;

$GLOBALS['_fails'] = 0;
$_SESSION = $_SESSION ?? [];

function ok(string $label, bool $cond): void
{
    echo ($cond ? 'PASS  ' : 'FAIL  ') . $label . "\n";
    if (!$cond) { $GLOBALS['_fails']++; }
}

function app(): \Slim\App
{
    static $app = null;
    return $app ??= \App\Bootstrap::create();
}

function pdo(): \PDO
{
    static $p = null;
    return $p ??= (new \App\Database(settings()['db']))->pdo();
}

function settings(): array
{
    static $s = null;
    if ($s === null) {
        $root = dirname(__DIR__);
        if (is_file($root . '/.env')) { \Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
        $s = require $root . '/config/settings.php';
    }
    return $s;
}

/**
 * Trimite o cerere în proces.
 * $files = ['camp' => ['cale' => '/abs/fisier', 'nume' => 'x.pdf']] pentru un singur fișier,
 * sau ['camp' => [['cale' => ..., 'nume' => ...], ...]] pentru un câmp multiplu (`camp[]`),
 * caz în care se construiește un array de UploadedFile, ca la un upload `multiple` real.
 */
function cerere(string $metoda, string $cale, array $body = [], array $files = []): \Psr\Http\Message\ResponseInterface
{
    $req = (new ServerRequestFactory())->createServerRequest($metoda, $cale, ['REMOTE_ADDR' => '127.0.0.1']);
    if ($body !== []) {
        $req = $req->withParsedBody($body)->withHeader('Content-Type', 'application/x-www-form-urlencoded');
    }
    if ($files !== []) {
        $creaza = function (array $f) {
            $stream = (new StreamFactory())->createStreamFromFile($f['cale']);
            return (new UploadedFileFactory())->createUploadedFile($stream, filesize($f['cale']), $f['eroare'] ?? UPLOAD_ERR_OK, $f['nume']);
        };
        $uf = [];
        foreach ($files as $camp => $f) {
            // Listă de fișiere (array de descrieri) => câmp multiplu; altfel, un singur fișier.
            $uf[$camp] = isset($f['cale']) ? $creaza($f) : array_map($creaza, array_values($f));
        }
        $req = $req->withUploadedFiles($uf);
    }
    return app()->handle($req);
}

function corp(\Psr\Http\Message\ResponseInterface $r): string
{
    $r->getBody()->rewind();
    return (string) $r->getBody();
}

function final_test(): void
{
    $f = $GLOBALS['_fails'];
    echo $f === 0 ? "\nOK — toate testele trec.\n" : "\n$f test(e) au eșuat.\n";
    exit($f === 0 ? 0 : 1);
}

/** Creează un utilizator temporar, pune sesiunea de admin și întoarce id-ul. Șterge-l în finally. */
function logheaza_test(): int
{
    $email = 'test-' . bin2hex(random_bytes(4)) . '@example.com';
    pdo()->prepare('INSERT INTO utilizatori (email, nume, parola_hash) VALUES (:e, :n, :h)')
        ->execute(['e' => $email, 'n' => 'Test', 'h' => password_hash('x', PASSWORD_DEFAULT)]);
    $id = (int) pdo()->lastInsertId();
    $_SESSION = ['csrf' => 'abc', 'admin_user' => ['id' => $id, 'email' => $email, 'nume' => 'Test']];
    return $id;
}
