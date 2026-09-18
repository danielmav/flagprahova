# FLAG Prahova — Plan 1: fundație + admin (M0 + M1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un back-office funcțional la `/admin` în care clientul administrează arborele de meniu al celor două perioade, încarcă fișiere, scrie pagini cu WYSIWYG și gestionează utilizatori și setări.

**Architecture:** Slim 4 + Twig + PDO, front controller `index.php`, container-array construit în `App\Bootstrap`, rute într-un closure în `src/Routes.php`. Intrarea de meniu este unitatea de conținut (tabela `meniu`, arbore prin `parent_id`). Testele sunt scripturi PHP în `tests/` care rulează aplicația în proces (`$app->handle($request)`) pe baza locală `flagprahova`.

**Tech Stack:** PHP 8.3 (CLI Laragon `C:/laragon/bin/php/php-8.3.31-nts-Win32-vs16-x64/php.exe`; codul rămâne compatibil 8.1), Slim 4, slim/twig-view, vlucas/phpdotenv, phpmailer, MySQL 8 local (`root`, fără parolă), Bootstrap 5.3, Quill 1.3, SortableJS 1.15, toate vendorate în `assets/vendor/`.

**Spec:** `docs/superpowers/specs/2026-09-18-flagprahova-site-nou-design.md`

**Planuri următoare (separate):** Plan 2 = sit public (landing, secțiuni, pagini, galerie, contact, SEO). Plan 3 = migrare WordPress + staging + lansare.

## Global Constraints

- PHP ≥ 8.1 în cod (`composer.json` `"php": ">=8.1"`), fără funcții 8.2+/8.3+.
- Fără build step, fără npm, fără CDN: orice bibliotecă e copiată în `assets/vendor/`.
- SQL scris de mână, prepared statements native (`ATTR_EMULATE_PREPARES => false`): nume de placeholder DISTINCTE într-o interogare, `LIMIT` inline ca `(int)`.
- UTF-8 end-to-end: `charset=utf8mb4`, `JSON_UNESCAPED_UNICODE` la orice `json_encode`.
- Limba interfeței (admin și public): română, cu diacritice corecte (ș/ț cu virgulă).
- Toate formularele POST din admin au `_csrf`; verificarea e în `App\Admin\Helpers::csrfOk()`.
- Adminul e la `/admin` (setare `ADMIN_PATH`, implicit `admin`).
- Uploads în `/fisiere/AAAA/LL/`, extensii permise: `pdf doc docx xls xlsx ppt pptx odt jpg jpeg png webp zip`, max 50 MB, MIME verificat din conținut.
- Comenzile CLI se rulează cu `PHP="C:/laragon/bin/php/php-8.3.31-nts-Win32-vs16-x64/php.exe"`; MySQL cu `MYSQL="C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root --default-character-set=utf8mb4`.
- Nu se pune text cu diacritice pe linia de comandă `mysql.exe` (corupe encodarea); datele cu diacritice se scriu prin PDO din PHP.
- Commit după fiecare task; fără push.

## Structura de fișiere (creată în acest plan)

| Fișier | Responsabilitate |
|---|---|
| `composer.json` | dependențe + autoload `App\` → `src/`, `files: src/Support/helpers.php` |
| `index.php`, `router.php`, `.htaccess`, `.env.example` | front controller, router pentru `php -S`, rutare Apache, variabile |
| `config/settings.php` | config din `.env` (app, admin, db, mail, upload) |
| `src/Support/helpers.php` | `e()`, `slugify()`, `ip_hash()`, fus orar |
| `src/Database.php` | fabrică PDO memoizată |
| `src/Bootstrap.php` | env, sesiune, Slim, Twig, container, erori, rute |
| `src/Routes.php` | tabelul de rute (public minimal + admin) |
| `src/Admin/Helpers.php` | trait: `redirect()`, `csrfOk()`, `flash()`, `adminPath()` |
| `src/Admin/Auth.php`, `LoginThrottle.php`, `AuthMiddleware.php` | autentificare (fără roluri) |
| `src/Admin/LoginController.php` | login, logout, parolă uitată, setare parolă |
| `src/Admin/UtilizatoriRepository.php`, `PasswordTokenRepository.php`, `UtilizatoriController.php` | conturi |
| `src/Meniu/Repository.php` | arbore, CRUD, slug unic, reordonare |
| `src/Admin/MeniuController.php` | ecranele Meniu + Editare intrare + reordonare JSON |
| `src/Fisiere/Repository.php`, `src/Fisiere/Upload.php` | registrul fișierelor + validare/salvare upload |
| `src/Admin/FisiereController.php` | ecran Fișiere, upload, ștergere, picker, upload din editor |
| `src/Setari/Repository.php`, `src/Admin/SetariController.php` | cheie/valoare |
| `src/Admin/MesajeController.php` | listă mesaje contact |
| `src/Mail/Mailer.php` | trimitere email (copiat din pestelocal) |
| `database/schema.sql`, `database/migrate.php`, `database/seed.php`, `database/create_admin.php` | schema idempotentă, seed, cont inițial |
| `templates/admin/*.twig` | layout, login, meniu, intrare, fisiere, setari, utilizatori, mesaje |
| `assets/css/admin.css`, `assets/js/admin.js`, `assets/vendor/{bootstrap,quill,sortable}/` | UI admin |
| `tests/_bootstrap.php` + `tests/*_test.php` | harness în proces + teste |

---

### Task 1: Schelet aplicație (composer, Bootstrap, Twig, rută de sănătate)

**Files:**
- Create: `composer.json`, `index.php`, `router.php`, `.htaccess`, `.env.example`, `.env`, `config/settings.php`, `src/Support/helpers.php`, `src/Database.php`, `src/Bootstrap.php`, `src/Routes.php`, `templates/layout.twig`, `templates/home.twig`, `storage/cache/.gitkeep`, `storage/logs/.gitkeep`, `tests/_bootstrap.php`, `tests/schelet_test.php`

**Interfaces:**
- Produces: `App\Bootstrap::create(): Slim\App`; `App\Database::pdo(): PDO`; helpers globale `e(?string): string`, `slugify(string): string`, `ip_hash(?string): ?string`; containerul (array) cu cheile `settings`, `db`; în teste funcțiile `ok(string, bool)`, `app()`, `cerere(string $metoda, string $cale, array $body = [], array $files = [])`, `pdo()`.

- [ ] **Step 1: composer.json + install**

```json
{
    "name": "danielmav/flagprahova",
    "description": "Situl Asociației FLAG Prahova — două perioade de programare, admin minimal.",
    "type": "project",
    "require": {
        "php": ">=8.1",
        "phpmailer/phpmailer": "^6.9",
        "slim/psr7": "^1.6",
        "slim/slim": "^4.12",
        "slim/twig-view": "^3.3",
        "vlucas/phpdotenv": "^5.6"
    },
    "autoload": {
        "psr-4": { "App\\": "src/" },
        "files": [ "src/Support/helpers.php" ]
    },
    "config": {
        "optimize-autoloader": true,
        "sort-packages": true,
        "platform": { "php": "8.1.10" }
    }
}
```

Run: `cd C:/laragon/www/flagprahova && composer install`
Expected: `vendor/` creat, fără erori.

- [ ] **Step 2: helpers, Database, settings, .env**

`src/Support/helpers.php`:

```php
<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Bucharest');

if (!function_exists('e')) {
    function e(?string $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('slugify')) {
    /** „Apel lansare – Măsura 1 (rev.2)” → „apel-lansare-masura-1-rev-2” */
    function slugify(string $text): string
    {
        $map = ['ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ş'=>'s','ț'=>'t','ţ'=>'t',
                'Ă'=>'a','Â'=>'a','Î'=>'i','Ș'=>'s','Ş'=>'s','Ț'=>'t','Ţ'=>'t'];
        $t = strtr($text, $map);
        $t = function_exists('iconv') ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t) : $t;
        $t = strtolower($t);
        $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
        return trim($t, '-') ?: 'intrare';
    }
}

if (!function_exists('ip_hash')) {
    /** Hash sărat al IP-ului (throttle, jurnal). null când lipsește sarea sau IP-ul. */
    function ip_hash(?string $ip): ?string
    {
        $salt = (string) ($_ENV['IP_SALT'] ?? '');
        if ($salt === '' || $ip === null || $ip === '') {
            return null;
        }
        return hash('sha256', $salt . '|' . $ip);
    }
}
```

`src/Database.php` — copiat identic din `C:/laragon/www/pestelocal/src/Database.php` (fabrică PDO memoizată, `SET time_zone` pe offsetul PHP), doar prefixul mesajelor de log devine `[flagprahova][db]`.

`config/settings.php`:

```php
<?php
declare(strict_types=1);

return [
    'app' => [
        'env'       => $_ENV['APP_ENV'] ?? 'prod',
        'debug'     => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
        'url'       => rtrim($_ENV['APP_URL'] ?? 'http://flagprahova.test', '/'),
        'base_path' => rtrim($_ENV['BASE_PATH'] ?? '', '/'),
        'name'      => 'FLAG Prahova',
    ],
    'admin' => [
        'path' => '/' . trim($_ENV['ADMIN_PATH'] ?? 'admin', '/'),
    ],
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'name' => $_ENV['DB_NAME'] ?? 'flagprahova',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'pass' => $_ENV['DB_PASS'] ?? '',
    ],
    'mail' => [
        'from'        => $_ENV['MAIL_FROM'] ?? 'noreply@flagprahova.ro',
        'from_name'   => $_ENV['MAIL_FROM_NAME'] ?? 'FLAG Prahova',
        'admin'       => $_ENV['MAIL_ADMIN'] ?? 'flagprahova@gmail.com',
        'smtp_host'   => $_ENV['SMTP_HOST'] ?? '',
        'smtp_port'   => (int) ($_ENV['SMTP_PORT'] ?? 587),
        'smtp_user'   => $_ENV['SMTP_USER'] ?? '',
        'smtp_pass'   => $_ENV['SMTP_PASS'] ?? '',
        'smtp_secure' => $_ENV['SMTP_SECURE'] ?? 'tls',
    ],
    'upload' => [
        'dir'       => dirname(__DIR__) . '/fisiere',
        'url'       => '/fisiere',
        'max_bytes' => 50 * 1024 * 1024,
    ],
    'twig' => [
        'templates' => dirname(__DIR__) . '/templates',
        'cache'     => filter_var($_ENV['TWIG_CACHE'] ?? false, FILTER_VALIDATE_BOOL)
            ? dirname(__DIR__) . '/storage/cache/twig' : false,
    ],
];
```

`.env.example` (și `.env` local, copie cu aceleași valori):

```
APP_ENV=dev
APP_DEBUG=true
APP_URL=http://flagprahova.test
BASE_PATH=
ADMIN_PATH=admin
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=flagprahova
DB_USER=root
DB_PASS=
MAIL_FROM=noreply@flagprahova.ro
MAIL_FROM_NAME=FLAG Prahova
MAIL_ADMIN=flagprahova@gmail.com
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
SMTP_SECURE=tls
IP_SALT=schimba-ma-cu-un-sir-lung-aleator
TWIG_CACHE=false
```

- [ ] **Step 3: Bootstrap, Routes, index.php, router.php, .htaccess**

`src/Bootstrap.php`:

```php
<?php
declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

final class Bootstrap
{
    public static function create(): App
    {
        $root = dirname(__DIR__);
        if (is_file($root . '/.env')) {
            Dotenv::createImmutable($root)->safeLoad();
        }
        /** @var array<string,mixed> $settings */
        $settings = require $root . '/config/settings.php';

        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
            $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? '') === '443';
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => ($settings['app']['base_path'] ?: '/'),
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $https,
            ]);
            session_name('fp_session');
            session_start();
        }
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        $app = AppFactory::create();
        if ($settings['app']['base_path'] !== '') {
            $app->setBasePath($settings['app']['base_path']);
        }
        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();

        $twig = Twig::create($settings['twig']['templates'], [
            'cache'       => $settings['twig']['cache'],
            'auto_reload' => true,
        ]);
        $env = $twig->getEnvironment();
        $env->addGlobal('app', $settings['app']);
        $env->addGlobal('base', $settings['app']['base_path']);
        $env->addGlobal('admin_path', $settings['admin']['path']);
        $env->addGlobal('csrf', $_SESSION['csrf']);
        $env->addGlobal('fisiere_url', $settings['upload']['url']);
        $app->add(TwigMiddleware::create($app, $twig));

        $app->add(function (Request $request, RequestHandler $handler): Response {
            $response = $handler->handle($request);
            return $response
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->withHeader('X-Frame-Options', 'SAMEORIGIN');
        });

        $db = new Database($settings['db']);
        $container = [
            'settings' => $settings,
            'db'       => $db,
        ];
        self::extinde($container, $root, $env);

        $app->addErrorMiddleware((bool) $settings['app']['debug'], true, true);
        (require $root . '/src/Routes.php')($app, $twig, $container);
        return $app;
    }

    /**
     * Punctul în care task-urile următoare adaugă servicii în container și
     * funcții Twig. Ținut separat ca `create()` să rămână lizibil.
     * @param array<string,mixed> $container
     */
    private static function extinde(array &$container, string $root, \Twig\Environment $env): void
    {
        // Task 3+: auth, throttle, meniu, fisiere, setari, mailer.
    }
}
```

`src/Routes.php`:

```php
<?php
declare(strict_types=1);

use Slim\App;
use Slim\Views\Twig;

return function (App $app, Twig $twig, array $container): void {
    $app->get('/', function ($request, $response) use ($twig) {
        return $twig->render($response, 'home.twig', ['titlu' => 'FLAG Prahova']);
    })->setName('home');

    $app->get('/health', function ($request, $response) use ($container) {
        $ok = true;
        try { $container['db']->pdo()->query('SELECT 1'); } catch (\Throwable) { $ok = false; }
        $response->getBody()->write(json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($ok ? 200 : 500);
    });
};
```

`index.php` și `router.php` — copiate din pestelocal (`require vendor/autoload.php; App\Bootstrap::create()->run();` respectiv routerul pentru `php -S`). `.htaccess` — copiat din pestelocal, cu blocul de internals adaptat: `RewriteRule ^(src|templates|config|storage|vendor|database|materiale|docs|tests|\.git)/ - [F,L]` și gazda de dezvoltare `flagprahova\.test`.

`templates/layout.twig` minimal (va fi rescris în Plan 2):

```twig
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{% block title %}{{ titlu|default('FLAG Prahova') }}{% endblock %}</title>
</head>
<body>
{% block content %}{% endblock %}
</body>
</html>
```

`templates/home.twig`: `{% extends 'layout.twig' %}{% block content %}<h1>{{ titlu }}</h1>{% endblock %}`.

- [ ] **Step 4: Harness de test în proces**

`tests/_bootstrap.php`:

```php
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
 * Trimite o cerere în proces. $files = ['camp' => ['cale' => '/abs/fisier', 'nume' => 'x.pdf']].
 */
function cerere(string $metoda, string $cale, array $body = [], array $files = []): \Psr\Http\Message\ResponseInterface
{
    $req = (new ServerRequestFactory())->createServerRequest($metoda, $cale, ['REMOTE_ADDR' => '127.0.0.1']);
    if ($body !== []) {
        $req = $req->withParsedBody($body)->withHeader('Content-Type', 'application/x-www-form-urlencoded');
    }
    if ($files !== []) {
        $uf = [];
        foreach ($files as $camp => $f) {
            $stream = (new StreamFactory())->createStreamFromFile($f['cale']);
            $uf[$camp] = (new UploadedFileFactory())->createUploadedFile($stream, filesize($f['cale']), UPLOAD_ERR_OK, $f['nume']);
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
```

`tests/schelet_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$r = cerere('GET', '/');
ok('GET / => 200', $r->getStatusCode() === 200);
ok('GET / conține titlul', str_contains(corp($r), 'FLAG Prahova'));
ok('GET / are nosniff', $r->getHeaderLine('X-Content-Type-Options') === 'nosniff');

$r = cerere('GET', '/health');
ok('GET /health => 200 (DB locală pornită)', $r->getStatusCode() === 200);
ok('helper e() escapează', e('<a>') === '&lt;a&gt;');
ok('slugify diacritice', slugify('Apel lansare – Măsura 1 (rev.2) Șirna') === 'apel-lansare-masura-1-rev-2-sirna');
ok('slugify gol => intrare', slugify('---') === 'intrare');
final_test();
```

- [ ] **Step 5: Creează baza locală și rulează testul**

Run: `$MYSQL -e "CREATE DATABASE IF NOT EXISTS flagprahova CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"` apoi `$PHP tests/schelet_test.php`
Expected: 7 × PASS, `OK — toate testele trec.`

- [ ] **Step 6: Verifică în browser**

Run: `curl -s -o /dev/null -w "%{http_code}" http://flagprahova.test/` (după restart Laragon pentru vhost)
Expected: `200`

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock index.php router.php .htaccess .env.example config src templates storage tests
git commit -m "M0: schelet Slim+Twig, config, helpers, harness de test in proces"
```

### Task 2: Schema bazei de date, migrare idempotentă, seed

**Files:**
- Create: `database/schema.sql`, `database/migrate.php`, `database/seed.php`, `tests/schema_test.php`

**Interfaces:**
- Produces: tabelele `sectiuni`, `meniu`, `fisiere`, `galerie_imagini`, `utilizatori`, `parola_tokens`, `login_incercari`, `setari`, `mesaje_contact` cu coloanele de mai jos; `database/migrate.php` (rulabil oricând, nedistructiv); `database/seed.php` (cele 2 secțiuni + setări implicite, idempotent pe `slug`/`cheie`).

- [ ] **Step 1: Testul care verifică tabelele și coloanele**

`tests/schema_test.php`:

```php
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
```

- [ ] **Step 2: Rulează testul, trebuie să pice**

Run: `$PHP tests/schema_test.php`
Expected: FAIL pe „tabela sectiuni există” și restul.

- [ ] **Step 3: schema.sql**

`database/schema.sql` (fiecare instrucțiune `CREATE TABLE IF NOT EXISTS`, ca re-rularea să fie sigură):

```sql
CREATE TABLE IF NOT EXISTS sectiuni (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(20)  NOT NULL UNIQUE,
  titlu        VARCHAR(120) NOT NULL,
  subtitlu     VARCHAR(255) NOT NULL DEFAULT '',
  acasa_html   MEDIUMTEXT   NULL,
  hero_imagine VARCHAR(255) NULL,
  ordine       TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fisiere (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nume_afisat  VARCHAR(255) NOT NULL,
  cale         VARCHAR(255) NOT NULL UNIQUE,
  mime         VARCHAR(100) NOT NULL,
  marime       INT UNSIGNED NOT NULL DEFAULT 0,
  incarcat_la  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  incarcat_de  INT UNSIGNED NULL,
  legacy_url   VARCHAR(500) NULL,
  KEY idx_fisiere_cale_prefix (cale(7)),
  KEY idx_fisiere_legacy (legacy_url(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meniu (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sectiune_id   INT UNSIGNED NOT NULL,
  parent_id     INT UNSIGNED NULL,
  ordine        INT UNSIGNED NOT NULL DEFAULT 0,
  titlu         VARCHAR(255) NOT NULL,
  slug          VARCHAR(160) NOT NULL,
  tip           ENUM('pagina','document','dosar','link','galerie') NOT NULL DEFAULT 'document',
  continut_html MEDIUMTEXT   NULL,
  fisier_id     INT UNSIGNED NULL,
  url           VARCHAR(500) NULL,
  sablon        ENUM('standard','contact') NOT NULL DEFAULT 'standard',
  vizibil       TINYINT(1)   NOT NULL DEFAULT 1,
  legacy_id     INT UNSIGNED NULL,
  creat_la      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificat_la  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_meniu_slug (sectiune_id, slug),
  KEY idx_meniu_parinte (sectiune_id, parent_id, ordine),
  KEY idx_meniu_legacy (legacy_id),
  CONSTRAINT fk_meniu_sectiune FOREIGN KEY (sectiune_id) REFERENCES sectiuni(id),
  CONSTRAINT fk_meniu_parent   FOREIGN KEY (parent_id)   REFERENCES meniu(id) ON DELETE CASCADE,
  CONSTRAINT fk_meniu_fisier   FOREIGN KEY (fisier_id)   REFERENCES fisiere(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS galerie_imagini (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  meniu_id  INT UNSIGNED NOT NULL,
  fisier_id INT UNSIGNED NOT NULL,
  ordine    INT UNSIGNED NOT NULL DEFAULT 0,
  legenda   VARCHAR(255) NOT NULL DEFAULT '',
  KEY idx_gal_meniu (meniu_id, ordine),
  CONSTRAINT fk_gal_meniu  FOREIGN KEY (meniu_id)  REFERENCES meniu(id)   ON DELETE CASCADE,
  CONSTRAINT fk_gal_fisier FOREIGN KEY (fisier_id) REFERENCES fisiere(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS utilizatori (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL UNIQUE,
  nume          VARCHAR(120) NOT NULL DEFAULT '',
  parola_hash   VARCHAR(255) NOT NULL DEFAULT '',
  ultimul_login DATETIME NULL,
  creat_la      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parola_tokens (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  utilizator_id  INT UNSIGNED NOT NULL,
  token_hash     CHAR(64) NOT NULL UNIQUE,
  expira_la      DATETIME NOT NULL,
  folosit_la     DATETIME NULL,
  creat_la       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ptok_user FOREIGN KEY (utilizator_id) REFERENCES utilizatori(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_incercari (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip_hash CHAR(64) NOT NULL,
  scope   VARCHAR(20) NOT NULL DEFAULT 'admin',
  la      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login (ip_hash, scope, la)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS setari (
  cheie   VARCHAR(80) NOT NULL PRIMARY KEY,
  valoare TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mesaje_contact (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sectiune_id  INT UNSIGNED NULL,
  nume         VARCHAR(120) NOT NULL,
  email        VARCHAR(190) NOT NULL,
  mesaj        TEXT NOT NULL,
  ip_hash      CHAR(64) NULL,
  trimis_la    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  email_trimis TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- [ ] **Step 4: migrate.php și seed.php**

`database/migrate.php`:

```php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
if (is_file($root . '/.env')) { Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
$settings = require $root . '/config/settings.php';
$pdo = (new App\Database($settings['db']))->pdo();

$sql = file_get_contents(__DIR__ . '/schema.sql');
$instructiuni = array_filter(array_map('trim', explode(';', $sql)));
$n = 0;
foreach ($instructiuni as $i) {
    $pdo->exec($i);
    $n++;
}
echo "migrate: $n instrucțiuni rulate pe {$settings['db']['name']}\n";
```

`database/seed.php` (diacriticele stau în PHP, nu pe linia de comandă):

```php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
if (is_file($root . '/.env')) { Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
$settings = require $root . '/config/settings.php';
$pdo = (new App\Database($settings['db']))->pdo();

$sectiuni = [
    ['slug' => '2021-2027', 'titlu' => 'FLAG Prahova 2021-2027', 'subtitlu' => 'Programul pentru Acvacultură și Pescuit 2021-2027', 'ordine' => 1],
    ['slug' => '2014-2020', 'titlu' => 'FLAG Prahova 2014-2020', 'subtitlu' => 'Programul Operațional pentru Pescuit și Afaceri Maritime 2014-2020', 'ordine' => 2],
];
$st = $pdo->prepare('INSERT INTO sectiuni (slug, titlu, subtitlu, ordine) VALUES (:s, :t, :st, :o)
    ON DUPLICATE KEY UPDATE titlu = VALUES(titlu), subtitlu = VALUES(subtitlu), ordine = VALUES(ordine)');
foreach ($sectiuni as $s) {
    $st->execute(['s' => $s['slug'], 't' => $s['titlu'], 'st' => $s['subtitlu'], 'o' => $s['ordine']]);
}

$setari = [
    'contact_email_destinatar' => 'flagprahova@gmail.com',
    'landing_titlu'            => 'Asociația FLAG Prahova',
    'landing_text'             => 'Grup de acțiune locală pentru pescuit și acvacultură în județul Prahova.',
    'footer_text'              => 'Conținutul acestui material nu reprezintă în mod obligatoriu poziția oficială a Uniunii Europene sau a Guvernului României.',
];
$st = $pdo->prepare('INSERT IGNORE INTO setari (cheie, valoare) VALUES (:c, :v)');
foreach ($setari as $c => $v) {
    $st->execute(['c' => $c, 'v' => $v]);
}
echo "seed: " . count($sectiuni) . " secțiuni, " . count($setari) . " setări\n";
```

- [ ] **Step 5: Rulează migrarea, seed-ul și testul**

Run: `$PHP database/migrate.php && $PHP database/seed.php && $PHP tests/schema_test.php`
Expected: toate PASS. Rulează încă o dată `migrate.php` + `seed.php`: fără erori (idempotent).

- [ ] **Step 6: Commit**

```bash
git add database tests/schema_test.php
git commit -m "M0: schema DB idempotenta, migrate + seed (sectiuni, setari)"
```

### Task 3: Autentificare admin (Auth, throttle, middleware, login/logout, layout admin)

**Files:**
- Create: `src/Admin/Auth.php`, `src/Admin/LoginThrottle.php`, `src/Admin/AuthMiddleware.php`, `src/Admin/Helpers.php`, `src/Admin/LoginController.php`, `database/create_admin.php`, `templates/admin/layout.twig`, `templates/admin/_sidebar.twig`, `templates/admin/login.twig`, `templates/admin/dashboard.twig`, `assets/css/admin.css`, `assets/js/admin.js`, `assets/vendor/bootstrap/bootstrap.min.css`, `assets/vendor/bootstrap/bootstrap.bundle.min.js`, `tests/admin_auth_test.php`
- Modify: `src/Bootstrap.php` (`extinde()`), `src/Routes.php`

**Interfaces:**
- Consumes: `App\Database`, tabelele `utilizatori`, `login_incercari`.
- Produces: `App\Admin\Auth::attempt(string $email, string $parola): bool`, `check(): bool`, `user(): ?array{id:int,email:string,nume:string}`, `logout(): void`; `App\Admin\LoginThrottle::tooMany(?string $ipHash): bool`, `record(?string)`, `clear(?string)`; `App\Admin\AuthMiddleware` (redirect la `{admin}/login` fără sesiune); trait `App\Admin\Helpers` cu `redirect(Response, string $caleRelativaAdmin): Response`, `csrfOk(Request): bool`, `flash(string $tip, string $mesaj): void`, `preiaFlash(): ?array`, `adminPath(): string`, `render(Response, string $template, array $vars): Response` (adaugă automat `utilizator`, `flash`, `admin_path`); container: `auth`, `login_throttle`. Toate rutele de admin sunt în grupul `$app->group($adminPath, ...)->add(AuthMiddleware)`, mai puțin `/login`, `/logout`, `/parola-uitata`, `/parola/{token}` care sunt în afara grupului. În teste, funcția `logheaza_test(): int` creează un utilizator temporar și pune sesiunea.

- [ ] **Step 1: Test de autentificare**

`tests/admin_auth_test.php`:

```php
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
```

Adaugă în `tests/_bootstrap.php`:

```php
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
```

- [ ] **Step 2: Rulează testul (pică pe clasa lipsă)**

Run: `$PHP tests/admin_auth_test.php`
Expected: eroare `Class "App\Admin\Auth" not found`.

- [ ] **Step 3: Auth, LoginThrottle, Helpers, AuthMiddleware**

`src/Admin/Auth.php` — pornește de la `C:/laragon/www/pestelocal/src/Admin/Auth.php` și simplifică: tabela `utilizatori` (coloane `email, nume, parola_hash, ultimul_login`), fără `role`/`status`/`areColoanaStatus()`; păstrează `DUMMY_HASH`, regenerarea sesiunii și a CSRF-ului la login/logout, cheia `SESSION_KEY = 'admin_user'`, `touchLastLogin()` (`UPDATE utilizatori SET ultimul_login = NOW()`). Metode: `attempt`, `check`, `user` (`{id, email, nume}`), `utilizatorCurent()` (recitește din `utilizatori` — dacă rândul lipsește, întoarce null), `logout`.

`src/Admin/LoginThrottle.php` — copiat din pestelocal, tabela devine `login_incercari` (coloane `ip_hash, scope, la`), `MAX_FAILED = 5`, prefix log `[flagprahova]`.

`src/Admin/Helpers.php`:

```php
<?php
declare(strict_types=1);

namespace App\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Comun tuturor controllerelor de admin. Cere proprietățile $twig (Slim\Views\Twig), $settings și $auth. */
trait Helpers
{
    protected function adminPath(): string
    {
        return (string) $this->settings['admin']['path'];
    }

    protected function redirect(Response $response, string $cale = ''): Response
    {
        $base = (string) $this->settings['app']['base_path'];
        return $response->withHeader('Location', $base . $this->adminPath() . $cale)->withStatus(302);
    }

    protected function csrfOk(Request $request): bool
    {
        $in = (array) $request->getParsedBody();
        $tok = (string) ($in['_csrf'] ?? $request->getHeaderLine('X-CSRF'));
        return $tok !== '' && hash_equals((string) ($_SESSION['csrf'] ?? ''), $tok);
    }

    protected function flash(string $tip, string $mesaj): void
    {
        $_SESSION['flash'] = ['tip' => $tip, 'mesaj' => $mesaj];
    }

    protected function preiaFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($f) ? $f : null;
    }

    /** @param array<string,mixed> $vars */
    protected function render(Response $response, string $template, array $vars = []): Response
    {
        return $this->twig->render($response, $template, $vars + [
            'utilizator' => $this->auth->user(),
            'flash'      => $this->preiaFlash(),
        ]);
    }

    protected function json(Response $response, array $date, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($date, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($status);
    }
}
```

`src/Admin/AuthMiddleware.php`:

```php
<?php
declare(strict_types=1);

namespace App\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth, private string $adminPath, private string $basePath) {}

    public function process(Request $request, Handler $handler): Response
    {
        if ($this->auth->utilizatorCurent() === null) {
            return (new SlimResponse())
                ->withHeader('Location', $this->basePath . $this->adminPath . '/login')
                ->withStatus(302);
        }
        return $handler->handle($request);
    }
}
```

- [ ] **Step 4: LoginController + rute + container**

`src/Admin/LoginController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class LoginController
{
    use Helpers;

    private Auth $auth;
    private LoginThrottle $throttle;
    private array $settings;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth     = $container['auth'];
        $this->throttle = $container['login_throttle'];
        $this->settings = $container['settings'];
    }

    public function dashboard(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/dashboard.twig');
    }

    public function form(Request $request, Response $response): Response
    {
        if ($this->auth->check()) {
            return $this->redirect($response);
        }
        return $this->render($response, 'admin/login.twig', ['eroare' => null, 'email' => '']);
    }

    public function submit(Request $request, Response $response): Response
    {
        $in     = (array) $request->getParsedBody();
        $email  = trim((string) ($in['email'] ?? ''));
        $parola = (string) ($in['parola'] ?? '');
        $ip     = ip_hash($request->getServerParams()['REMOTE_ADDR'] ?? null);

        if (!$this->csrfOk($request)) {
            return $this->render($response, 'admin/login.twig', ['eroare' => 'Sesiunea a expirat. Încearcă din nou.', 'email' => $email]);
        }
        if ($this->throttle->tooMany($ip)) {
            return $this->render($response, 'admin/login.twig', ['eroare' => 'Prea multe încercări. Așteaptă 15 minute.', 'email' => $email]);
        }
        if ($email === '' || $parola === '' || !$this->auth->attempt($email, $parola)) {
            $this->throttle->record($ip);
            return $this->render($response, 'admin/login.twig', ['eroare' => 'Email sau parolă greșite.', 'email' => $email]);
        }
        $this->throttle->clear($ip);
        return $this->redirect($response);
    }

    public function logout(Request $request, Response $response): Response
    {
        if ($this->csrfOk($request)) {
            $this->auth->logout();
        }
        return $this->redirect($response, '/login');
    }
}
```

În `Bootstrap::extinde()`:

```php
$container['auth']           = new Admin\Auth($container['db']);
$container['login_throttle'] = new Admin\LoginThrottle($container['db'], 'admin');
```

În `src/Routes.php` (după rutele publice):

```php
use App\Admin\AuthMiddleware;
use App\Admin\LoginController;
// ...
$adminPath = (string) $container['settings']['admin']['path'];
$basePath  = (string) $container['settings']['app']['base_path'];

$app->get($adminPath . '/login', fn($rq, $rs) => (new LoginController($twig, $container))->form($rq, $rs));
$app->post($adminPath . '/login', fn($rq, $rs) => (new LoginController($twig, $container))->submit($rq, $rs));
$app->post($adminPath . '/logout', fn($rq, $rs) => (new LoginController($twig, $container))->logout($rq, $rs));

$app->group($adminPath, function ($g) use ($twig, $container) {
    $g->get('', fn($rq, $rs) => (new LoginController($twig, $container))->dashboard($rq, $rs));
    // Task 5+: meniu, fisiere, setari, utilizatori, mesaje
})->add(new AuthMiddleware($container['auth'], $adminPath, $basePath));
```

- [ ] **Step 5: Layout admin, login, dashboard, CSS, vendor Bootstrap**

Run (descarcă o singură dată, fișierele se comit):

```bash
mkdir -p assets/vendor/bootstrap
curl -sL -o assets/vendor/bootstrap/bootstrap.min.css https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css
curl -sL -o assets/vendor/bootstrap/bootstrap.bundle.min.js https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js
```

`templates/admin/layout.twig`:

```twig
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{% block title %}Administrare{% endblock %} · FLAG Prahova</title>
    <script>window.ADMIN_BASE={{ (base ~ admin_path)|json_encode|raw }};window.CSRF={{ csrf|json_encode|raw }};</script>
    <link rel="stylesheet" href="{{ base }}/assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="{{ base }}/assets/css/admin.css">
    {% block head %}{% endblock %}
</head>
<body class="adm{% if utilizator %} adm--nav{% endif %}">
{% if utilizator %}
    <nav class="navbar navbar-dark bg-primary adm-topbar">
        <div class="container-fluid">
            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adm-side" aria-label="Meniu"><span class="navbar-toggler-icon"></span></button>
            <a class="navbar-brand" href="{{ base }}{{ admin_path }}">FLAG Prahova · administrare</a>
            <div class="d-flex align-items-center gap-2">
                <a class="btn btn-sm btn-outline-light" href="{{ base }}/" target="_blank" rel="noopener">Vezi situl</a>
                <form method="post" action="{{ base }}{{ admin_path }}/logout" class="d-flex align-items-center gap-2 m-0">
                    <input type="hidden" name="_csrf" value="{{ csrf }}">
                    <span class="text-white-50 small d-none d-md-inline">{{ utilizator.nume ?: utilizator.email }}</span>
                    <button class="btn btn-sm btn-outline-light" type="submit">Ieșire</button>
                </form>
            </div>
        </div>
    </nav>
    {% include 'admin/_sidebar.twig' %}
{% endif %}
<main class="adm-main">
    {% if flash %}
        <div class="alert alert-{{ flash.tip == 'ok' ? 'success' : 'danger' }} adm-flash" role="alert">{{ flash.mesaj }}</div>
    {% endif %}
    {% block content %}{% endblock %}
</main>
<script src="{{ base }}/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="{{ base }}/assets/js/admin.js" defer></script>
{% block scripts %}{% endblock %}
</body>
</html>
```

`templates/admin/_sidebar.twig`:

```twig
{% set intrari = [
    {'ruta': '/meniu',       'eticheta': 'Meniu'},
    {'ruta': '/fisiere',     'eticheta': 'Fișiere'},
    {'ruta': '/setari',      'eticheta': 'Setări'},
    {'ruta': '/utilizatori', 'eticheta': 'Utilizatori'},
    {'ruta': '/mesaje',      'eticheta': 'Mesaje'},
] %}
<aside class="offcanvas-lg offcanvas-start adm-side" tabindex="-1" id="adm-side">
    <div class="offcanvas-header d-lg-none"><span class="fw-bold">Administrare</span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#adm-side" aria-label="Închide"></button></div>
    <ul class="nav flex-column adm-nav">
        {% for i in intrari %}
            <li class="nav-item"><a class="adm-nav__link nav-link" href="{{ base }}{{ admin_path }}{{ i.ruta }}">{{ i.eticheta }}</a></li>
        {% endfor %}
    </ul>
</aside>
```

`templates/admin/login.twig`:

```twig
{% extends 'admin/layout.twig' %}
{% block title %}Autentificare{% endblock %}
{% block content %}
<div class="adm-login">
    <div class="card shadow-sm adm-login__card">
        <div class="card-body">
            <h1 class="h4 mb-3">FLAG Prahova · administrare</h1>
            {% if eroare %}<div class="alert alert-danger" role="alert">{{ eroare }}</div>{% endif %}
            <form method="post" action="{{ base }}{{ admin_path }}/login">
                <input type="hidden" name="_csrf" value="{{ csrf }}">
                <div class="mb-3"><label class="form-label" for="f-email">Email</label>
                    <input class="form-control" id="f-email" name="email" type="email" value="{{ email }}" required autocomplete="username" autofocus></div>
                <div class="mb-3"><label class="form-label" for="f-parola">Parolă</label>
                    <input class="form-control" id="f-parola" name="parola" type="password" required autocomplete="current-password"></div>
                <button class="btn btn-primary w-100" type="submit">Intră</button>
            </form>
            <p class="mt-3 mb-0 small"><a href="{{ base }}{{ admin_path }}/parola-uitata">Ți-ai uitat parola?</a></p>
        </div>
    </div>
</div>
{% endblock %}
```

`templates/admin/dashboard.twig`:

```twig
{% extends 'admin/layout.twig' %}
{% block title %}Panou{% endblock %}
{% block content %}
<h1 class="h3 mb-4">Bun venit</h1>
<div class="row g-3">
    <div class="col-md-6"><a class="card adm-card text-decoration-none" href="{{ base }}{{ admin_path }}/meniu?sectiune=2021-2027"><div class="card-body"><h2 class="h5">FLAG Prahova 2021-2027</h2><p class="mb-0 text-muted">Meniul și documentele perioadei curente.</p></div></a></div>
    <div class="col-md-6"><a class="card adm-card text-decoration-none" href="{{ base }}{{ admin_path }}/meniu?sectiune=2014-2020"><div class="card-body"><h2 class="h5">FLAG Prahova 2014-2020</h2><p class="mb-0 text-muted">Arhiva perioadei anterioare.</p></div></a></div>
</div>
{% endblock %}
```

`assets/css/admin.css`:

```css
:root { --fp-albastru: #0B4F9C; --fp-albastru-inchis: #083A72; --fp-deschis: #E8F1FB; }
.adm { background: #f5f7fa; min-height: 100vh; }
.adm .bg-primary { background: var(--fp-albastru) !important; }
.adm .btn-primary { background: var(--fp-albastru); border-color: var(--fp-albastru); }
.adm .btn-primary:hover { background: var(--fp-albastru-inchis); border-color: var(--fp-albastru-inchis); }
.adm-side { width: 230px; background: #fff; border-right: 1px solid #e3e8ee; }
.adm-nav__link { color: #1b2430; padding: .75rem 1.25rem; border-left: 3px solid transparent; }
.adm-nav__link:hover, .adm-nav__link.active { background: var(--fp-deschis); border-left-color: var(--fp-albastru); color: var(--fp-albastru); }
.adm-main { padding: 1.5rem; }
@media (min-width: 992px) {
  .adm--nav .adm-side { position: fixed; top: 56px; bottom: 0; left: 0; }
  .adm--nav .adm-main { margin-left: 230px; }
}
.adm-login { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.adm-login__card { width: 100%; max-width: 420px; }
.adm-card:hover { border-color: var(--fp-albastru); }
```

`assets/js/admin.js`: pentru moment doar marchează linkul activ din sidebar:

```js
document.querySelectorAll('.adm-nav__link').forEach(function (a) {
  if (location.pathname.startsWith(new URL(a.href).pathname)) a.classList.add('active');
});
```

`database/create_admin.php` (CLI: `$PHP database/create_admin.php email@x.ro "Nume" parola`):

```php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
if (is_file($root . '/.env')) { Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
$settings = require $root . '/config/settings.php';
[$_, $email, $nume, $parola] = $argv + [null, null, null, null];
if (!$email || !$parola) { fwrite(STDERR, "Folosire: create_admin.php email nume parola\n"); exit(1); }
$pdo = (new App\Database($settings['db']))->pdo();
$pdo->prepare('INSERT INTO utilizatori (email, nume, parola_hash) VALUES (:e, :n, :h)
    ON DUPLICATE KEY UPDATE nume = VALUES(nume), parola_hash = VALUES(parola_hash)')
    ->execute(['e' => $email, 'n' => (string) $nume, 'h' => password_hash($parola, PASSWORD_DEFAULT)]);
echo "OK: $email\n";
```

- [ ] **Step 6: Rulează testul și verifică în browser**

Run: `$PHP tests/admin_auth_test.php`
Expected: toate PASS.
Run: `$PHP database/create_admin.php admin@flagprahova.ro "Administrator" parola-locala` apoi deschide `http://flagprahova.test/admin/login`, autentifică-te.
Expected: dashboard cu două carduri, sidebar cu 5 intrări.

- [ ] **Step 7: Commit**

```bash
git add src templates assets database/create_admin.php tests
git commit -m "M0: autentificare admin (Auth, throttle, middleware, login), layout admin Bootstrap"
```

### Task 4: `App\Meniu\Repository` (arbore, CRUD, slug unic, reordonare)

**Files:**
- Create: `src/Meniu/Repository.php`, `tests/meniu_repository_test.php`
- Modify: `src/Bootstrap.php` (`extinde()`: `$container['meniu'] = new Meniu\Repository($container['db']);`)

**Interfaces:**
- Produces:
  - `sectiuni(): array` — rânduri `sectiuni` ordonate după `ordine`.
  - `sectiuneDupaSlug(string $slug): ?array`.
  - `arbore(int $sectiuneId, bool $doarVizibile = false): array` — listă de noduri `{id, parent_id, ordine, titlu, slug, tip, url, fisier_id, vizibil, copii: []}`, ordonată după `ordine`, adâncime nelimitată.
  - `gaseste(int $id): ?array` — rândul complet.
  - `gasesteDupaSlug(int $sectiuneId, string $slug): ?array`.
  - `copii(int $parentId): array`.
  - `slugUnic(int $sectiuneId, string $slug, ?int $exceptId = null): string` — adaugă `-2`, `-3`… la coliziune.
  - `creeaza(array $date): int` — chei: `sectiune_id, parent_id, titlu, slug, tip, continut_html, fisier_id, url, sablon, vizibil, legacy_id`; `ordine` = ultimul + 1 între frații săi.
  - `actualizeaza(int $id, array $date): void` — aceleași chei minus `sectiune_id`.
  - `sterge(int $id): int` — întoarce numărul de rânduri șterse (cu descendenți, prin `ON DELETE CASCADE`).
  - `numaraDescendenti(int $id): int`.
  - `reordoneaza(int $sectiuneId, array $arbore): void` — primește `[{id, copii:[...]}]` și rescrie `parent_id` + `ordine` într-o tranzacție; refuză (aruncă `InvalidArgumentException`) un id care nu aparține secțiunii.
  - `fisierFolosit(int $fisierId): array` — lista `{id, titlu}` a intrărilor care îl referă (prin `fisier_id`, `galerie_imagini` sau `continut_html LIKE '%/cale%'`).

- [ ] **Step 1: Testul repository-ului**

`tests/meniu_repository_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo  = pdo();
$repo = new App\Meniu\Repository(new App\Database(settings()['db']));
$sec  = $repo->sectiuneDupaSlug('2014-2020');
ok('sectiuneDupaSlug', $sec !== null && $sec['slug'] === '2014-2020');
$sid  = (int) $sec['id'];
$marca = 'test-' . bin2hex(random_bytes(3));
$creati = [];
try {
    $a = $repo->creeaza(['sectiune_id' => $sid, 'parent_id' => null, 'titlu' => "Strategie $marca", 'slug' => '', 'tip' => 'dosar']);
    $b = $repo->creeaza(['sectiune_id' => $sid, 'parent_id' => $a, 'titlu' => "Ghidul solicitantului $marca", 'slug' => '', 'tip' => 'dosar']);
    $c = $repo->creeaza(['sectiune_id' => $sid, 'parent_id' => $b, 'titlu' => "Măsura 1 – Rev. 5", 'slug' => '', 'tip' => 'link', 'url' => 'https://example.com/m1.pdf']);
    $d = $repo->creeaza(['sectiune_id' => $sid, 'parent_id' => $b, 'titlu' => "Măsura 1 – Rev. 5", 'slug' => '', 'tip' => 'link', 'url' => 'https://example.com/m1b.pdf']);
    $creati = [$a, $b, $c, $d];

    ok('slug generat din titlu', $repo->gaseste($c)['slug'] === 'masura-1-rev-5');
    ok('slug duplicat primește sufix -2', $repo->gaseste($d)['slug'] === 'masura-1-rev-5-2');
    ok('ordine crește între frați', (int) $repo->gaseste($d)['ordine'] === (int) $repo->gaseste($c)['ordine'] + 1);

    $arb = $repo->arbore($sid);
    $nodA = null;
    foreach ($arb as $n) { if ((int) $n['id'] === $a) { $nodA = $n; } }
    ok('arbore: nodul A la nivel 1', $nodA !== null);
    ok('arbore: A → B → [C, D] (3 niveluri)', $nodA !== null && count($nodA['copii']) === 1
        && count($nodA['copii'][0]['copii']) === 2);

    $repo->actualizeaza($c, ['titlu' => 'Măsura 1 – Rev. 6', 'slug' => 'masura-1-rev-6', 'tip' => 'link', 'url' => 'https://example.com/m1c.pdf', 'vizibil' => 0]);
    ok('actualizeaza schimbă titlul', $repo->gaseste($c)['titlu'] === 'Măsura 1 – Rev. 6');
    $viz = $repo->arbore($sid, true);
    $nodAv = null;
    foreach ($viz as $n) { if ((int) $n['id'] === $a) { $nodAv = $n; } }
    ok('arbore doarVizibile exclude C', $nodAv !== null && count($nodAv['copii'][0]['copii']) === 1);

    // reordoneaza: D înaintea lui C, ambele mutate direct sub A
    $repo->reordoneaza($sid, [['id' => $a, 'copii' => [['id' => $d, 'copii' => []], ['id' => $c, 'copii' => []], ['id' => $b, 'copii' => []]]]]);
    ok('reordoneaza: D are parent A și ordine 0', (int) $repo->gaseste($d)['parent_id'] === $a && (int) $repo->gaseste($d)['ordine'] === 0);
    ok('reordoneaza: B e al treilea', (int) $repo->gaseste($b)['ordine'] === 2);
    $arunca = false;
    try { $repo->reordoneaza($sid, [['id' => 999999999, 'copii' => []]]); } catch (InvalidArgumentException) { $arunca = true; }
    ok('reordoneaza refuză id străin', $arunca);

    ok('numaraDescendenti(A) = 3', $repo->numaraDescendenti($a) === 3);
    ok('sterge(A) șterge 4', $repo->sterge($a) === 4);
    ok('după ștergere B lipsește', $repo->gaseste($b) === null);
    $creati = [];
} finally {
    foreach ($creati as $id) { $pdo->exec("DELETE FROM meniu WHERE id = $id"); }
}
final_test();
```

- [ ] **Step 2: Rulează, pică pe clasa lipsă**

Run: `$PHP tests/meniu_repository_test.php` — Expected: `Class "App\Meniu\Repository" not found`.

- [ ] **Step 3: Implementarea**

`src/Meniu/Repository.php`:

```php
<?php
declare(strict_types=1);

namespace App\Meniu;

use App\Database;
use InvalidArgumentException;
use PDO;

final class Repository
{
    public const TIPURI  = ['pagina', 'document', 'dosar', 'link', 'galerie'];
    public const SABLOANE = ['standard', 'contact'];
    private const COLOANE = ['parent_id', 'titlu', 'slug', 'tip', 'continut_html', 'fisier_id', 'url', 'sablon', 'vizibil', 'legacy_id'];

    private PDO $pdo;

    public function __construct(Database $db)
    {
        $this->pdo = $db->pdo();
    }

    public function sectiuni(): array
    {
        return $this->pdo->query('SELECT * FROM sectiuni ORDER BY ordine, id')->fetchAll();
    }

    public function sectiuneDupaSlug(string $slug): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM sectiuni WHERE slug = :s LIMIT 1');
        $st->execute(['s' => $slug]);
        return $st->fetch() ?: null;
    }

    public function arbore(int $sectiuneId, bool $doarVizibile = false): array
    {
        $sql = 'SELECT id, parent_id, ordine, titlu, slug, tip, url, fisier_id, vizibil FROM meniu WHERE sectiune_id = :s'
            . ($doarVizibile ? ' AND vizibil = 1' : '') . ' ORDER BY ordine, id';
        $st = $this->pdo->prepare($sql);
        $st->execute(['s' => $sectiuneId]);
        $noduri = [];
        foreach ($st->fetchAll() as $r) {
            $r['copii'] = [];
            $noduri[(int) $r['id']] = $r;
        }
        $radacini = [];
        foreach ($noduri as $id => &$n) {
            $p = $n['parent_id'] === null ? null : (int) $n['parent_id'];
            if ($p !== null && isset($noduri[$p])) {
                $noduri[$p]['copii'][] = &$n;
            } else {
                $radacini[] = &$n;
            }
        }
        unset($n);
        return $radacini;
    }

    public function gaseste(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM meniu WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function gasesteDupaSlug(int $sectiuneId, string $slug): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM meniu WHERE sectiune_id = :s AND slug = :sl LIMIT 1');
        $st->execute(['s' => $sectiuneId, 'sl' => $slug]);
        return $st->fetch() ?: null;
    }

    public function copii(int $parentId): array
    {
        $st = $this->pdo->prepare('SELECT * FROM meniu WHERE parent_id = :p ORDER BY ordine, id');
        $st->execute(['p' => $parentId]);
        return $st->fetchAll();
    }

    public function slugUnic(int $sectiuneId, string $slug, ?int $exceptId = null): string
    {
        $baza = slugify($slug);
        $cand = $baza;
        for ($i = 2; $i < 1000; $i++) {
            $st = $this->pdo->prepare('SELECT id FROM meniu WHERE sectiune_id = :s AND slug = :sl AND id <> :ex LIMIT 1');
            $st->execute(['s' => $sectiuneId, 'sl' => $cand, 'ex' => $exceptId ?? 0]);
            if (!$st->fetch()) {
                return $cand;
            }
            $cand = $baza . '-' . $i;
        }
        return $baza . '-' . bin2hex(random_bytes(3));
    }

    public function creeaza(array $date): int
    {
        $sid    = (int) $date['sectiune_id'];
        $parent = isset($date['parent_id']) && $date['parent_id'] !== '' && $date['parent_id'] !== null ? (int) $date['parent_id'] : null;
        $slug   = $this->slugUnic($sid, ($date['slug'] ?? '') !== '' ? (string) $date['slug'] : (string) $date['titlu']);
        $st = $this->pdo->prepare('SELECT COALESCE(MAX(ordine), -1) + 1 FROM meniu WHERE sectiune_id = :s AND ' . ($parent === null ? 'parent_id IS NULL' : 'parent_id = :p'));
        $st->execute($parent === null ? ['s' => $sid] : ['s' => $sid, 'p' => $parent]);
        $ordine = (int) $st->fetchColumn();

        $st = $this->pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, continut_html, fisier_id, url, sablon, vizibil, legacy_id)
            VALUES (:s, :p, :o, :t, :sl, :tip, :c, :f, :u, :sab, :v, :lg)');
        $st->execute([
            's' => $sid, 'p' => $parent, 'o' => $ordine,
            't' => trim((string) $date['titlu']), 'sl' => $slug,
            'tip' => in_array($date['tip'] ?? '', self::TIPURI, true) ? $date['tip'] : 'document',
            'c' => $date['continut_html'] ?? null,
            'f' => isset($date['fisier_id']) && $date['fisier_id'] !== '' ? (int) $date['fisier_id'] : null,
            'u' => ($date['url'] ?? '') !== '' ? (string) $date['url'] : null,
            'sab' => in_array($date['sablon'] ?? '', self::SABLOANE, true) ? $date['sablon'] : 'standard',
            'v' => (int) ($date['vizibil'] ?? 1),
            'lg' => isset($date['legacy_id']) ? (int) $date['legacy_id'] : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function actualizeaza(int $id, array $date): void
    {
        $curent = $this->gaseste($id);
        if ($curent === null) {
            return;
        }
        $sid = (int) $curent['sectiune_id'];
        if (array_key_exists('slug', $date) || array_key_exists('titlu', $date)) {
            $date['slug'] = $this->slugUnic($sid, ($date['slug'] ?? '') !== '' ? (string) $date['slug'] : (string) ($date['titlu'] ?? $curent['titlu']), $id);
        }
        if (array_key_exists('parent_id', $date)) {
            $date['parent_id'] = $date['parent_id'] === '' || $date['parent_id'] === null ? null : (int) $date['parent_id'];
            if ($date['parent_id'] === $id) {
                $date['parent_id'] = $curent['parent_id'];
            }
        }
        $set = [];
        $par = ['id' => $id];
        foreach (self::COLOANE as $c) {
            if (array_key_exists($c, $date)) {
                $set[]   = "$c = :$c";
                $par[$c] = $date[$c] === '' && in_array($c, ['fisier_id', 'url', 'continut_html', 'legacy_id'], true) ? null : $date[$c];
            }
        }
        if ($set === []) {
            return;
        }
        $this->pdo->prepare('UPDATE meniu SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($par);
    }

    public function numaraDescendenti(int $id): int
    {
        $n = 0;
        foreach ($this->copii($id) as $c) {
            $n += 1 + $this->numaraDescendenti((int) $c['id']);
        }
        return $n;
    }

    public function sterge(int $id): int
    {
        $total = 1 + $this->numaraDescendenti($id);
        $st = $this->pdo->prepare('DELETE FROM meniu WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->rowCount() === 0 ? 0 : $total;
    }

    public function reordoneaza(int $sectiuneId, array $arbore): void
    {
        $st = $this->pdo->prepare('SELECT id FROM meniu WHERE sectiune_id = :s');
        $st->execute(['s' => $sectiuneId]);
        $permise = array_flip(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
        $upd = $this->pdo->prepare('UPDATE meniu SET parent_id = :p, ordine = :o WHERE id = :id AND sectiune_id = :s');
        $this->pdo->beginTransaction();
        try {
            $parcurge = function (array $noduri, ?int $parent) use (&$parcurge, $permise, $upd, $sectiuneId): void {
                foreach (array_values($noduri) as $i => $n) {
                    $id = (int) ($n['id'] ?? 0);
                    if (!isset($permise[$id])) {
                        throw new InvalidArgumentException("Intrarea $id nu aparține secțiunii $sectiuneId");
                    }
                    $upd->execute(['p' => $parent, 'o' => $i, 'id' => $id, 's' => $sectiuneId]);
                    $parcurge((array) ($n['copii'] ?? []), $id);
                }
            };
            $parcurge($arbore, null);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function fisierFolosit(int $fisierId): array
    {
        $st = $this->pdo->prepare('SELECT cale FROM fisiere WHERE id = :id');
        $st->execute(['id' => $fisierId]);
        $cale = (string) $st->fetchColumn();
        $st = $this->pdo->prepare('SELECT DISTINCT m.id, m.titlu FROM meniu m
            LEFT JOIN galerie_imagini g ON g.meniu_id = m.id
            WHERE m.fisier_id = :f1 OR g.fisier_id = :f2 OR (:cale <> '' AND m.continut_html LIKE :like)
            ORDER BY m.titlu');
        $st->execute(['f1' => $fisierId, 'f2' => $fisierId, 'cale' => $cale, 'like' => '%' . $cale . '%']);
        return $st->fetchAll();
    }
}
```

- [ ] **Step 4: Rulează testul**

Run: `$PHP tests/meniu_repository_test.php` — Expected: toate PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Meniu src/Bootstrap.php tests/meniu_repository_test.php
git commit -m "M1: Meniu\\Repository — arbore, CRUD, slug unic, reordonare"
```

### Task 5: Fișiere — `App\Fisiere\Upload` (validare) și `App\Fisiere\Repository`

**Files:**
- Create: `src/Fisiere/Upload.php`, `src/Fisiere/Repository.php`, `fisiere/.htaccess`, `fisiere/.gitkeep`, `tests/fisiere_upload_test.php`, `tests/fixtures/mic.pdf`, `tests/fixtures/mic.png`, `tests/fixtures/rau.php.pdf`
- Modify: `src/Bootstrap.php` (`extinde()`: `$container['fisiere'] = new Fisiere\Repository($container['db']); $container['upload'] = new Fisiere\Upload($settings['upload']['dir'], $settings['upload']['max_bytes']);`), `.gitignore` (adaugă `/fisiere/*` + `!/fisiere/.htaccess` + `!/fisiere/.gitkeep`)

**Interfaces:**
- Produces:
  - `Upload::__construct(string $dir, int $maxBytes)`; `Upload::salveaza(UploadedFileInterface $f): array{cale:?string, nume_afisat:string, mime:string, marime:int, motiv:?string}` — `motiv` ∈ `gol|prea_mare|tip_nepermis|eroare`; `cale` e relativă (`AAAA/LL/nume-normalizat.ext`), unică (sufix `-2`, `-3` la coliziune pe disc).
  - `Upload::EXTENSII` = `['pdf','doc','docx','xls','xlsx','ppt','pptx','odt','jpg','jpeg','png','webp','zip']`; `Upload::esteImagine(string $mime): bool`.
  - `Upload::numeSigur(string $numeOriginal): string` — `Anunț angajare – Manager.PDF` → `anunt-angajare-manager.pdf`.
  - `Repository::inregistreaza(array $r): int` (chei `nume_afisat, cale, mime, marime, incarcat_de, legacy_url`; pe `cale` existentă face UPDATE și întoarce id-ul existent), `gaseste(int): ?array`, `gasesteDupaCale(string): ?array`, `lista(?string $an, ?string $luna, string $cauta, bool $doarImagini = false): array` (ordonat `incarcat_la DESC`), `aniLuni(): array` (`[['an'=>'2026','luni'=>['08','07']], …]`), `sterge(int $id, string $dirAbs): bool` (șterge rândul și fișierul), `redenumeste(int $id, string $numeAfisat): void`.

- [ ] **Step 1: Fixtures și test**

Creează fixtures cu PHP (nu prin shell, ca să nu depindă de encodare):

```php
// tests/fixtures/_genereaza.php — rulează o singură dată, fișierele se comit
file_put_contents(__DIR__ . '/mic.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
file_put_contents(__DIR__ . '/mic.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
file_put_contents(__DIR__ . '/rau.php.pdf', "<?php echo 'x';");
```

`tests/fisiere_upload_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Factory\UploadedFileFactory;

$dir = sys_get_temp_dir() . '/fp-upload-' . bin2hex(random_bytes(3));
mkdir($dir);
$up = new App\Fisiere\Upload($dir, 1024 * 1024);
$uf = function (string $cale, string $nume, int $err = UPLOAD_ERR_OK) {
    $s = (new StreamFactory())->createStreamFromFile($cale);
    return (new UploadedFileFactory())->createUploadedFile($s, filesize($cale), $err, $nume);
};

ok('numeSigur normalizează', App\Fisiere\Upload::numeSigur('Anunț angajare – Manager.PDF') === 'anunt-angajare-manager.pdf');
ok('numeSigur taie path traversal', !str_contains(App\Fisiere\Upload::numeSigur('../../x.pdf'), '/'));

$r = $up->salveaza($uf(__DIR__ . '/fixtures/mic.pdf', 'Comunicat SDL.pdf'));
ok('pdf acceptat', $r['motiv'] === null && $r['cale'] !== null);
ok('  cale AAAA/LL/nume', preg_match('#^\d{4}/\d{2}/comunicat-sdl\.pdf$#', (string) $r['cale']) === 1);
ok('  mime application/pdf', $r['mime'] === 'application/pdf');
ok('  nume_afisat păstrat', $r['nume_afisat'] === 'Comunicat SDL.pdf');
ok('  fișierul există pe disc', is_file($dir . '/' . $r['cale']));
$r2 = $up->salveaza($uf(__DIR__ . '/fixtures/mic.pdf', 'Comunicat SDL.pdf'));
ok('al doilea cu același nume primește -2', str_ends_with((string) $r2['cale'], 'comunicat-sdl-2.pdf'));

$r = $up->salveaza($uf(__DIR__ . '/fixtures/rau.php.pdf', 'rau.php.pdf'));
ok('php deghizat în pdf => tip_nepermis (MIME din conținut)', $r['motiv'] === 'tip_nepermis');
$r = $up->salveaza($uf(__DIR__ . '/fixtures/mic.png', 'poza.exe'));
ok('png cu extensie exe => salvat ca .png', $r['motiv'] === null && str_ends_with((string) $r['cale'], '.png'));
ok('esteImagine', App\Fisiere\Upload::esteImagine('image/png') && !App\Fisiere\Upload::esteImagine('application/pdf'));
$r = $up->salveaza($uf(__DIR__ . '/fixtures/mic.pdf', 'x.pdf', UPLOAD_ERR_NO_FILE));
ok('fără fișier => gol', $r['motiv'] === 'gol');
$mic = new App\Fisiere\Upload($dir, 10);
ok('peste limită => prea_mare', $mic->salveaza($uf(__DIR__ . '/fixtures/mic.pdf', 'x.pdf'))['motiv'] === 'prea_mare');

// Repository
$repo = new App\Fisiere\Repository(new App\Database(settings()['db']));
$cale = date('Y/m') . '/test-' . bin2hex(random_bytes(3)) . '.pdf';
try {
    $id = $repo->inregistreaza(['nume_afisat' => 'Test.pdf', 'cale' => $cale, 'mime' => 'application/pdf', 'marime' => 12, 'incarcat_de' => null, 'legacy_url' => null]);
    ok('inregistreaza => id', $id > 0);
    ok('inregistreaza pe aceeași cale întoarce același id', $repo->inregistreaza(['nume_afisat' => 'Test2.pdf', 'cale' => $cale, 'mime' => 'application/pdf', 'marime' => 12]) === $id);
    ok('  și actualizează numele', $repo->gaseste($id)['nume_afisat'] === 'Test2.pdf');
    ok('gasesteDupaCale', ($repo->gasesteDupaCale($cale)['id'] ?? 0) == $id);
    $l = $repo->lista(date('Y'), date('m'), 'test-');
    ok('lista filtrează pe an/lună/căutare', count(array_filter($l, fn($x) => (int) $x['id'] === $id)) === 1);
    ok('lista doarImagini exclude pdf', array_filter($repo->lista(null, null, '', true), fn($x) => (int) $x['id'] === $id) === []);
    ok('aniLuni conține luna curentă', in_array(date('m'), array_column(array_values(array_filter($repo->aniLuni(), fn($a) => $a['an'] === date('Y')))[0]['luni'] ?? [], null), true));
    $repo->redenumeste($id, 'Nou.pdf');
    ok('redenumeste', $repo->gaseste($id)['nume_afisat'] === 'Nou.pdf');
    ok('sterge (fără fișier pe disc) => true', $repo->sterge($id, $dir) === true && $repo->gaseste($id) === null);
} finally {
    pdo()->exec('DELETE FROM fisiere WHERE cale = ' . pdo()->quote($cale));
    array_map('unlink', glob($dir . '/*/*/*') ?: []);
}
final_test();
```

- [ ] **Step 2: Rulează, pică pe clasa lipsă**

Run: `$PHP tests/fixtures/_genereaza.php && $PHP tests/fisiere_upload_test.php` — Expected: `Class "App\Fisiere\Upload" not found`.

- [ ] **Step 3: Upload.php**

```php
<?php
declare(strict_types=1);

namespace App\Fisiere;

use finfo;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

final class Upload
{
    public const EXTENSII = ['pdf','doc','docx','xls','xlsx','ppt','pptx','odt','jpg','jpeg','png','webp','zip'];

    /** MIME (din conținut) → extensie canonică. */
    private const MIME = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/zip' => 'zip',
    ];

    public function __construct(private string $dir, private int $maxBytes) {}

    public static function esteImagine(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    public static function numeSigur(string $numeOriginal): string
    {
        $baza = basename(str_replace('\\', '/', $numeOriginal));
        $ext  = strtolower(pathinfo($baza, PATHINFO_EXTENSION));
        $nume = pathinfo($baza, PATHINFO_FILENAME);
        $slug = slugify($nume);
        return $slug . ($ext !== '' ? '.' . $ext : '');
    }

    public function salveaza(UploadedFileInterface $f): array
    {
        $numeOriginal = (string) ($f->getClientFilename() ?? 'fisier');
        $nu = fn(string $motiv) => ['cale' => null, 'nume_afisat' => $numeOriginal, 'mime' => '', 'marime' => 0, 'motiv' => $motiv];

        $err = $f->getError();
        if ($err === UPLOAD_ERR_NO_FILE) { return $nu('gol'); }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) { return $nu('prea_mare'); }
        if ($err !== UPLOAD_ERR_OK) { return $nu('eroare'); }

        try {
            $stream = $f->getStream();
            $marime = (int) ($stream->getSize() ?? 0);
            if ($marime <= 0 || $marime > $this->maxBytes) {
                return $nu($marime > $this->maxBytes ? 'prea_mare' : 'eroare');
            }
            $stream->rewind();
            $cap = $stream->read(8192);
            $stream->rewind();
        } catch (Throwable) {
            return $nu('eroare');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($cap) ?: '';
        // Fișierele Office (docx/xlsx/pptx) sunt zip-uri: finfo poate întoarce application/zip. Acceptăm după extensia declarată.
        $extDeclarata = strtolower(pathinfo($numeOriginal, PATHINFO_EXTENSION));
        if ($mime === 'application/zip' && in_array($extDeclarata, ['docx', 'xlsx', 'pptx', 'odt'], true)) {
            $mime = array_search($extDeclarata, self::MIME, true) ?: $mime;
        }
        if (!isset(self::MIME[$mime])) {
            return $nu('tip_nepermis');
        }
        $ext = self::MIME[$mime];

        $sub = date('Y/m');
        $dirAbs = rtrim($this->dir, '/\\') . '/' . $sub;
        if (!is_dir($dirAbs) && !mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            return $nu('eroare');
        }
        $nume = pathinfo(self::numeSigur($numeOriginal), PATHINFO_FILENAME);
        $cand = $nume . '.' . $ext;
        for ($i = 2; is_file($dirAbs . '/' . $cand); $i++) {
            $cand = $nume . '-' . $i . '.' . $ext;
        }
        try {
            $f->moveTo($dirAbs . '/' . $cand);
        } catch (Throwable) {
            return $nu('eroare');
        }
        return ['cale' => $sub . '/' . $cand, 'nume_afisat' => $numeOriginal, 'mime' => $mime, 'marime' => $marime, 'motiv' => null];
    }
}
```

- [ ] **Step 4: Repository.php**

```php
<?php
declare(strict_types=1);

namespace App\Fisiere;

use App\Database;
use PDO;

final class Repository
{
    private PDO $pdo;

    public function __construct(Database $db)
    {
        $this->pdo = $db->pdo();
    }

    public function inregistreaza(array $r): int
    {
        $ex = $this->gasesteDupaCale((string) $r['cale']);
        if ($ex !== null) {
            $this->pdo->prepare('UPDATE fisiere SET nume_afisat = :n, mime = :m, marime = :s, legacy_url = COALESCE(:lg, legacy_url) WHERE id = :id')
                ->execute(['n' => $r['nume_afisat'], 'm' => $r['mime'], 's' => (int) $r['marime'], 'lg' => $r['legacy_url'] ?? null, 'id' => $ex['id']]);
            return (int) $ex['id'];
        }
        $this->pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime, incarcat_de, legacy_url) VALUES (:n, :c, :m, :s, :u, :lg)')
            ->execute(['n' => $r['nume_afisat'], 'c' => $r['cale'], 'm' => $r['mime'], 's' => (int) $r['marime'],
                       'u' => $r['incarcat_de'] ?? null, 'lg' => $r['legacy_url'] ?? null]);
        return (int) $this->pdo->lastInsertId();
    }

    public function gaseste(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM fisiere WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function gasesteDupaCale(string $cale): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM fisiere WHERE cale = :c');
        $st->execute(['c' => $cale]);
        return $st->fetch() ?: null;
    }

    public function lista(?string $an, ?string $luna, string $cauta = '', bool $doarImagini = false): array
    {
        $w = []; $p = [];
        if ($an !== null && $an !== '') { $w[] = 'cale LIKE :an'; $p['an'] = $an . '/%'; }
        if ($luna !== null && $luna !== '' && $an) { $w[] = 'cale LIKE :luna'; $p['luna'] = $an . '/' . $luna . '/%'; }
        if ($cauta !== '') { $w[] = '(nume_afisat LIKE :q1 OR cale LIKE :q2)'; $p['q1'] = '%' . $cauta . '%'; $p['q2'] = '%' . $cauta . '%'; }
        if ($doarImagini) { $w[] = "mime IN ('image/jpeg','image/png','image/webp')"; }
        $sql = 'SELECT * FROM fisiere' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY incarcat_la DESC, id DESC LIMIT 500';
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    }

    public function aniLuni(): array
    {
        $rows = $this->pdo->query("SELECT DISTINCT SUBSTRING(cale, 1, 4) an, SUBSTRING(cale, 6, 2) luna FROM fisiere ORDER BY an DESC, luna DESC")->fetchAll();
        $out = [];
        foreach ($rows as $r) { $out[$r['an']][] = $r['luna']; }
        return array_map(fn($an, $luni) => ['an' => $an, 'luni' => $luni], array_keys($out), $out);
    }

    public function redenumeste(int $id, string $numeAfisat): void
    {
        $this->pdo->prepare('UPDATE fisiere SET nume_afisat = :n WHERE id = :id')->execute(['n' => trim($numeAfisat), 'id' => $id]);
    }

    public function sterge(int $id, string $dirAbs): bool
    {
        $f = $this->gaseste($id);
        if ($f === null) { return false; }
        $abs = rtrim($dirAbs, '/\\') . '/' . $f['cale'];
        if (is_file($abs)) { @unlink($abs); }
        return $this->pdo->prepare('DELETE FROM fisiere WHERE id = :id')->execute(['id' => $id]);
    }
}
```

`fisiere/.htaccess`:

```
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phps
RemoveType .php .phtml
<FilesMatch "\.(php|phtml|phar|cgi|pl)$">
    Require all denied
</FilesMatch>
Options -Indexes
```

- [ ] **Step 5: Rulează testul**

Run: `$PHP tests/fisiere_upload_test.php` — Expected: toate PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Fisiere src/Bootstrap.php fisiere/.htaccess fisiere/.gitkeep .gitignore tests/fisiere_upload_test.php tests/fixtures
git commit -m "M1: Fisiere\\Upload (validare MIME, nume sigur) + Fisiere\\Repository"
```

### Task 6: Ecranul Fișiere în admin (listă, încărcare multiplă, redenumire, ștergere protejată, mod picker, upload din editor)

**Files:**
- Create: `src/Admin/FisiereController.php`, `templates/admin/fisiere.twig`, `tests/admin_fisiere_test.php`
- Modify: `src/Routes.php` (în grupul admin), `assets/js/admin.js`, `assets/css/admin.css`

**Interfaces:**
- Consumes: `Fisiere\Upload::salveaza()`, `Fisiere\Repository`, `Meniu\Repository::fisierFolosit()`, trait `Helpers`.
- Produces rute (toate sub `{admin}`):
  - `GET /fisiere?an=&luna=&q=&picker=1&imagini=1` — listă; cu `picker=1` randează fără sidebar, iar click pe un rând trimite `window.parent.postMessage({tip:'fisier', id, cale, nume, url}, '*')`.
  - `POST /fisiere/incarca` — `multipart`, câmp `fisiere[]`; pentru fiecare: salvează + înregistrează; flash cu numărul reușite/eșuate; redirect înapoi cu aceiași parametri (`?picker=1` păstrat).
  - `POST /fisiere/{id}/redenumeste` — câmp `nume_afisat`.
  - `POST /fisiere/{id}/sterge` — refuză cu flash `eroare` dacă `fisierFolosit()` nu e gol (listă titluri în mesaj).
  - `POST /fisiere/editor` — JSON pentru Quill: câmp `imagine`; răspuns `{"url": "/fisiere/2026/09/x.png"}` sau `{"eroare": "..."}` cu 422. Cere `X-CSRF` header.
- URL public al unui fișier: `{{ base }}{{ fisiere_url }}/{{ f.cale }}`.

- [ ] **Step 1: Test**

`tests/admin_fisiere_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$uid = logheaza_test();
$dir = settings()['upload']['dir'];
$creat = [];
try {
    $r = cerere('GET', '/admin/fisiere');
    ok('GET /admin/fisiere => 200', $r->getStatusCode() === 200);
    ok('  are formular de încărcare multiplă', str_contains(corp($r), 'name="fisiere[]"') && str_contains(corp($r), 'multiple'));

    $r = cerere('POST', '/admin/fisiere/incarca', ['_csrf' => 'abc'], ['fisiere' => ['cale' => __DIR__ . '/fixtures/mic.pdf', 'nume' => 'Test upload ' . bin2hex(random_bytes(2)) . '.pdf']]);
    ok('POST incarca => 302', $r->getStatusCode() === 302);
    $f = $pdo->query("SELECT * FROM fisiere WHERE nume_afisat LIKE 'Test upload %' ORDER BY id DESC LIMIT 1")->fetch();
    ok('  fișier înregistrat cu incarcat_de = utilizatorul', $f && (int) $f['incarcat_de'] === $uid);
    $creat[] = (int) $f['id'];
    ok('  există pe disc', is_file($dir . '/' . $f['cale']));

    $r = cerere('GET', '/admin/fisiere?picker=1');
    ok('picker: fără sidebar', !str_contains(corp($r), 'adm-nav__link'));
    ok('picker: rândurile au data-fisier-id', str_contains(corp($r), 'data-fisier-id="' . $f['id'] . '"'));

    $r = cerere('POST', '/admin/fisiere/' . $f['id'] . '/redenumeste', ['_csrf' => 'abc', 'nume_afisat' => 'Redenumit.pdf']);
    ok('redenumeste => 302', $r->getStatusCode() === 302);
    ok('  nume schimbat', $pdo->query('SELECT nume_afisat FROM fisiere WHERE id = ' . (int) $f['id'])->fetchColumn() === 'Redenumit.pdf');

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

    // upload din editor
    $r = cerere('POST', '/admin/fisiere/editor', ['_csrf' => 'abc'], ['imagine' => ['cale' => __DIR__ . '/fixtures/mic.png', 'nume' => 'editor.png']]);
    $j = json_decode(corp($r), true);
    ok('editor: 200 + url', $r->getStatusCode() === 200 && str_starts_with((string) ($j['url'] ?? ''), '/fisiere/'));
    $fe = $pdo->query("SELECT * FROM fisiere WHERE cale LIKE '%/editor%.png' ORDER BY id DESC LIMIT 1")->fetch();
    if ($fe) { $creat[] = (int) $fe['id']; }
    $r = cerere('POST', '/admin/fisiere/editor', ['_csrf' => 'abc'], ['imagine' => ['cale' => __DIR__ . '/fixtures/mic.pdf', 'nume' => 'nu.pdf']]);
    ok('editor: pdf => 422', $r->getStatusCode() === 422);
    $r = cerere('POST', '/admin/fisiere/editor', ['_csrf' => 'gresit'], ['imagine' => ['cale' => __DIR__ . '/fixtures/mic.png', 'nume' => 'x.png']]);
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
```

- [ ] **Step 2: Rulează, pică (404 / clasă lipsă)**

Run: `$PHP tests/admin_fisiere_test.php` — Expected: FAIL pe `GET /admin/fisiere => 200`.

- [ ] **Step 3: Controller + rute**

`src/Admin/FisiereController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Admin;

use App\Fisiere\Repository as Fisiere;
use App\Fisiere\Upload;
use App\Meniu\Repository as Meniu;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class FisiereController
{
    use Helpers;

    private Auth $auth;
    private array $settings;
    private Fisiere $fisiere;
    private Upload $upload;
    private Meniu $meniu;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth     = $container['auth'];
        $this->settings = $container['settings'];
        $this->fisiere  = $container['fisiere'];
        $this->upload   = $container['upload'];
        $this->meniu    = $container['meniu'];
    }

    public function index(Request $request, Response $response): Response
    {
        $q      = $request->getQueryParams();
        $picker = ($q['picker'] ?? '') === '1';
        $an     = (string) ($q['an'] ?? '');
        $luna   = (string) ($q['luna'] ?? '');
        $cauta  = trim((string) ($q['q'] ?? ''));
        $imagini = ($q['imagini'] ?? '') === '1';
        return $this->render($response, 'admin/fisiere.twig', [
            'fisiere'  => $this->fisiere->lista($an ?: null, $luna ?: null, $cauta, $imagini),
            'ani_luni' => $this->fisiere->aniLuni(),
            'filtru'   => ['an' => $an, 'luna' => $luna, 'q' => $cauta, 'imagini' => $imagini, 'picker' => $picker],
            'picker'   => $picker,
            'utilizator' => $picker ? null : $this->auth->user(), // fără sidebar în picker
        ]);
    }

    public function incarca(Request $request, Response $response): Response
    {
        $inapoi = '/fisiere' . $this->queryInapoi($request);
        if (!$this->csrfOk($request)) {
            $this->flash('eroare', 'Sesiunea a expirat. Reîncarcă pagina.');
            return $this->redirect($response, $inapoi);
        }
        $files = $request->getUploadedFiles()['fisiere'] ?? [];
        if (!is_array($files)) { $files = [$files]; }
        $ok = 0; $erori = [];
        foreach ($files as $f) {
            $r = $this->upload->salveaza($f);
            if ($r['motiv'] === 'gol') { continue; }
            if ($r['motiv'] !== null) { $erori[] = $r['nume_afisat'] . ' (' . self::motiv($r['motiv']) . ')'; continue; }
            $this->fisiere->inregistreaza($r + ['incarcat_de' => $this->auth->user()['id'] ?? null]);
            $ok++;
        }
        $this->flash($erori ? 'eroare' : 'ok', "$ok fișier(e) încărcat(e)." . ($erori ? ' Respinse: ' . implode(', ', $erori) : ''));
        return $this->redirect($response, $inapoi);
    }

    public function redenumeste(Request $request, Response $response, array $args): Response
    {
        if ($this->csrfOk($request)) {
            $nume = trim((string) (((array) $request->getParsedBody())['nume_afisat'] ?? ''));
            if ($nume !== '') { $this->fisiere->redenumeste((int) $args['id'], $nume); $this->flash('ok', 'Nume actualizat.'); }
        }
        return $this->redirect($response, '/fisiere' . $this->queryInapoi($request));
    }

    public function sterge(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->csrfOk($request)) {
            return $this->redirect($response, '/fisiere');
        }
        $folosit = $this->meniu->fisierFolosit($id);
        if ($folosit !== []) {
            $this->flash('eroare', 'Fișierul nu poate fi șters: este folosit de „' . implode('”, „', array_column($folosit, 'titlu')) . '”.');
            return $this->redirect($response, '/fisiere' . $this->queryInapoi($request));
        }
        $this->fisiere->sterge($id, $this->settings['upload']['dir']);
        $this->flash('ok', 'Fișier șters.');
        return $this->redirect($response, '/fisiere' . $this->queryInapoi($request));
    }

    /** Upload de imagine din editorul Quill. JSON. */
    public function editor(Request $request, Response $response): Response
    {
        if (!$this->csrfOk($request)) {
            return $this->json($response, ['eroare' => 'CSRF'], 403);
        }
        $f = $request->getUploadedFiles()['imagine'] ?? null;
        if ($f === null) { return $this->json($response, ['eroare' => 'Lipsește imaginea.'], 422); }
        $r = $this->upload->salveaza($f);
        if ($r['motiv'] !== null || !Upload::esteImagine($r['mime'])) {
            if ($r['cale'] !== null) { @unlink($this->settings['upload']['dir'] . '/' . $r['cale']); }
            return $this->json($response, ['eroare' => 'Doar imagini jpg/png/webp, maxim ' . (int) ($this->settings['upload']['max_bytes'] / 1048576) . ' MB.'], 422);
        }
        $this->fisiere->inregistreaza($r + ['incarcat_de' => $this->auth->user()['id'] ?? null]);
        return $this->json($response, ['url' => $this->settings['app']['base_path'] . $this->settings['upload']['url'] . '/' . $r['cale']]);
    }

    private function queryInapoi(Request $request): string
    {
        $q = array_intersect_key((array) $request->getParsedBody() + $request->getQueryParams(), array_flip(['an', 'luna', 'q', 'picker', 'imagini']));
        return $q ? '?' . http_build_query($q) : '';
    }

    public static function motiv(string $m): string
    {
        return ['prea_mare' => 'prea mare', 'tip_nepermis' => 'tip de fișier nepermis', 'eroare' => 'eroare la încărcare'][$m] ?? $m;
    }
}
```

Rute (în grupul admin din `src/Routes.php`, cu `use App\Admin\FisiereController;`):

```php
$fc = fn() => new FisiereController($twig, $container);
$g->get('/fisiere',                    fn($rq, $rs) => $fc()->index($rq, $rs));
$g->post('/fisiere/incarca',           fn($rq, $rs) => $fc()->incarca($rq, $rs));
$g->post('/fisiere/editor',            fn($rq, $rs) => $fc()->editor($rq, $rs));
$g->post('/fisiere/{id:[0-9]+}/redenumeste', fn($rq, $rs, $a) => $fc()->redenumeste($rq, $rs, $a));
$g->post('/fisiere/{id:[0-9]+}/sterge',      fn($rq, $rs, $a) => $fc()->sterge($rq, $rs, $a));
```

- [ ] **Step 4: Template `templates/admin/fisiere.twig`**

```twig
{% extends 'admin/layout.twig' %}
{% block title %}Fișiere{% endblock %}
{% block content %}
{% set qs = {an: filtru.an, luna: filtru.luna, q: filtru.q, picker: picker ? '1' : '', imagini: filtru.imagini ? '1' : ''} %}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 m-0">Fișiere</h1>
    <form class="d-flex gap-2" method="get" action="{{ base }}{{ admin_path }}/fisiere">
        {% if picker %}<input type="hidden" name="picker" value="1">{% endif %}
        {% if filtru.imagini %}<input type="hidden" name="imagini" value="1">{% endif %}
        <select class="form-select form-select-sm" name="an" onchange="this.form.submit()">
            <option value="">Toți anii</option>
            {% for a in ani_luni %}<option value="{{ a.an }}" {{ filtru.an == a.an ? 'selected' }}>{{ a.an }}</option>{% endfor %}
        </select>
        <select class="form-select form-select-sm" name="luna" onchange="this.form.submit()">
            <option value="">Toate lunile</option>
            {% for a in ani_luni if a.an == filtru.an %}{% for l in a.luni %}<option value="{{ l }}" {{ filtru.luna == l ? 'selected' }}>{{ l }}</option>{% endfor %}{% endfor %}
        </select>
        <input class="form-control form-control-sm" type="search" name="q" value="{{ filtru.q }}" placeholder="Caută după nume">
        <button class="btn btn-sm btn-outline-primary" type="submit">Caută</button>
    </form>
</div>

<form class="adm-upload card card-body mb-3" method="post" enctype="multipart/form-data" action="{{ base }}{{ admin_path }}/fisiere/incarca">
    <input type="hidden" name="_csrf" value="{{ csrf }}">
    {% for k, v in qs %}{% if v %}<input type="hidden" name="{{ k }}" value="{{ v }}">{% endif %}{% endfor %}
    <label class="form-label fw-semibold" for="f-fisiere">Încarcă fișiere (pdf, doc/x, xls/x, ppt/x, odt, jpg, png, webp, zip; max 50 MB fiecare)</label>
    <div class="d-flex gap-2">
        <input class="form-control" id="f-fisiere" type="file" name="fisiere[]" multiple>
        <button class="btn btn-primary" type="submit">Încarcă</button>
    </div>
</form>

<div class="table-responsive">
<table class="table table-hover align-middle adm-table">
    <thead><tr><th>Nume</th><th class="d-none d-md-table-cell">Cale</th><th class="d-none d-md-table-cell">Mărime</th><th class="d-none d-lg-table-cell">Data</th><th></th></tr></thead>
    <tbody>
    {% for f in fisiere %}
        {% set url = base ~ fisiere_url ~ '/' ~ f.cale %}
        <tr data-fisier-id="{{ f.id }}" data-fisier-cale="{{ f.cale }}" data-fisier-nume="{{ f.nume_afisat }}" data-fisier-url="{{ url }}" {% if picker %}class="adm-picker__rand" role="button"{% endif %}>
            <td>
                {% if f.mime starts with 'image/' %}<img class="adm-thumb me-2" src="{{ url }}" alt="">{% endif %}
                {% if picker %}<span>{{ f.nume_afisat }}</span>{% else %}
                <form class="d-inline-flex gap-1" method="post" action="{{ base }}{{ admin_path }}/fisiere/{{ f.id }}/redenumeste">
                    <input type="hidden" name="_csrf" value="{{ csrf }}">
                    {% for k, v in qs %}{% if v %}<input type="hidden" name="{{ k }}" value="{{ v }}">{% endif %}{% endfor %}
                    <input class="form-control form-control-sm adm-inline-input" name="nume_afisat" value="{{ f.nume_afisat }}">
                    <button class="btn btn-sm btn-outline-secondary" type="submit" title="Salvează numele">✓</button>
                </form>{% endif %}
            </td>
            <td class="d-none d-md-table-cell text-muted small"><a href="{{ url }}" target="_blank" rel="noopener">{{ f.cale }}</a></td>
            <td class="d-none d-md-table-cell small">{{ (f.marime / 1024)|round }} KB</td>
            <td class="d-none d-lg-table-cell small">{{ f.incarcat_la|date('d.m.Y') }}</td>
            <td class="text-end text-nowrap">
                {% if picker %}
                    <button class="btn btn-sm btn-primary adm-picker__alege" type="button">Alege</button>
                {% else %}
                    <button class="btn btn-sm btn-outline-secondary adm-copiaza" type="button" data-copiaza="{{ url }}">Copiază link</button>
                    <form class="d-inline" method="post" action="{{ base }}{{ admin_path }}/fisiere/{{ f.id }}/sterge" onsubmit="return confirm('Ștergi definitiv „{{ f.nume_afisat|e('js') }}”?')">
                        <input type="hidden" name="_csrf" value="{{ csrf }}">
                        {% for k, v in qs %}{% if v %}<input type="hidden" name="{{ k }}" value="{{ v }}">{% endif %}{% endfor %}
                        <button class="btn btn-sm btn-outline-danger" type="submit">Șterge</button>
                    </form>
                {% endif %}
            </td>
        </tr>
    {% else %}
        <tr><td colspan="5" class="text-muted">Niciun fișier{% if filtru.q %} pentru „{{ filtru.q }}”{% endif %}.</td></tr>
    {% endfor %}
    </tbody>
</table>
</div>
{% endblock %}
```

În `assets/js/admin.js` adaugă:

```js
// Copiază link
document.addEventListener('click', function (ev) {
  var b = ev.target.closest('[data-copiaza]');
  if (!b) return;
  navigator.clipboard.writeText(location.origin + b.dataset.copiaza).then(function () {
    b.textContent = 'Copiat!'; setTimeout(function () { b.textContent = 'Copiază link'; }, 1500);
  });
});
// Picker: trimite alegerea către fereastra părinte (modal din editorul de intrare)
document.addEventListener('click', function (ev) {
  var r = ev.target.closest('.adm-picker__rand');
  if (!r || !window.parent || window.parent === window) return;
  window.parent.postMessage({ tip: 'fisier', id: r.dataset.fisierId, cale: r.dataset.fisierCale, nume: r.dataset.fisierNume, url: r.dataset.fisierUrl }, '*');
});
```

În `assets/css/admin.css` adaugă: `.adm-thumb{width:40px;height:40px;object-fit:cover;border-radius:4px}.adm-inline-input{min-width:220px}.adm-picker__rand{cursor:pointer}`.

- [ ] **Step 5: Rulează testul + verifică în browser**

Run: `$PHP tests/admin_fisiere_test.php` — Expected: toate PASS.
Browser: `http://flagprahova.test/admin/fisiere` — încarcă 2 PDF-uri deodată, redenumește, copiază link, șterge.

- [ ] **Step 6: Commit**

```bash
git add src/Admin/FisiereController.php src/Routes.php templates/admin/fisiere.twig assets tests/admin_fisiere_test.php
git commit -m "M1: ecran Fisiere (incarcare multipla, redenumire, stergere protejata, picker, upload editor)"
```

### Task 7: Ecranul Meniu (arbore pe secțiune, adăugare/editare dosar·document·link, ștergere, reordonare drag & drop)

**Files:**
- Create: `src/Admin/MeniuController.php`, `templates/admin/meniu.twig`, `templates/admin/_meniu_nod.twig`, `templates/admin/intrare.twig`, `assets/vendor/sortable/Sortable.min.js`, `tests/admin_meniu_test.php`
- Modify: `src/Routes.php`, `assets/js/admin.js`, `assets/css/admin.css`

**Interfaces:**
- Consumes: `Meniu\Repository` (Task 4), `Fisiere\Repository::gaseste()`, picker-ul din Task 6.
- Produces rute sub `{admin}`:
  - `GET /meniu?sectiune=2021-2027` — taburi pe secțiuni, arborele secțiunii curente (implicit prima), buton „Adaugă intrare”.
  - `GET /meniu/nou?sectiune=…&parent=…` și `GET /meniu/{id}` — formularul `intrare.twig`.
  - `POST /meniu/salveaza` — câmpuri `id` (gol = nou), `sectiune_id, parent_id, titlu, slug, tip, url, fisier_id, sablon, vizibil, continut_html`; validare: titlu obligatoriu; `document` cere `fisier_id` existent; `link` cere URL `http(s)://`; la eroare re-randează formularul cu mesaj; la succes flash + redirect la `/meniu?sectiune=…`.
  - `POST /meniu/{id}/sterge` — confirmare în UI; flash cu numărul șterse.
  - `POST /meniu/reordoneaza` — JSON `{sectiune_id, arbore:[{id, copii:[…]}]}`, header `X-CSRF`; răspuns `{ok:true}` sau 4xx.
- Formularul pentru `pagina`/`galerie` are câmpurile lor (Task 8) deja în template, dar Task 7 le lasă doar ca `<textarea>` simplu / listă goală.

- [ ] **Step 1: Test**

`tests/admin_meniu_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$uid = logheaza_test();
$sid = (int) $pdo->query("SELECT id FROM sectiuni WHERE slug='2021-2027'")->fetchColumn();
$marca = 'Test ' . bin2hex(random_bytes(3));
$ids = [];
try {
    $r = cerere('GET', '/admin/meniu?sectiune=2021-2027');
    ok('GET /admin/meniu => 200', $r->getStatusCode() === 200);
    ok('  are taburile secțiunilor', str_contains(corp($r), '2021-2027') && str_contains(corp($r), '2014-2020'));
    ok('  are linkul Adaugă intrare', str_contains(corp($r), '/admin/meniu/nou?sectiune=2021-2027'));

    // creare dosar la nivel 1
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => '', 'titlu' => "Noutăți $marca", 'tip' => 'dosar', 'vizibil' => '1']);
    ok('salveaza dosar => 302', $r->getStatusCode() === 302);
    $dosar = $pdo->query("SELECT * FROM meniu WHERE titlu = " . $pdo->quote("Noutăți $marca"))->fetch();
    ok('  dosar creat, slug corect', $dosar && str_starts_with($dosar['slug'], 'noutati-test-'));
    $ids[] = (int) $dosar['id'];

    // titlu gol => formular re-randat cu eroare, nimic creat
    $n0 = (int) $pdo->query('SELECT COUNT(*) FROM meniu')->fetchColumn();
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => $dosar['id'], 'titlu' => '', 'tip' => 'dosar']);
    ok('titlu gol => 200 cu eroare', $r->getStatusCode() === 200 && str_contains(corp($r), 'Titlul este obligatoriu'));
    ok('  nimic creat', (int) $pdo->query('SELECT COUNT(*) FROM meniu')->fetchColumn() === $n0);

    // link fără http => eroare
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => $dosar['id'], 'titlu' => 'L', 'tip' => 'link', 'url' => 'ftp://x']);
    ok('link invalid => eroare', $r->getStatusCode() === 200 && str_contains(corp($r), 'http://'));

    // document fără fișier => eroare; cu fișier => ok
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => $dosar['id'], 'titlu' => 'D', 'tip' => 'document', 'fisier_id' => '']);
    ok('document fără fișier => eroare', $r->getStatusCode() === 200 && str_contains(corp($r), 'Alege un fișier'));
    $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES ("t.pdf", :c, "application/pdf", 1)')->execute(['c' => '2026/01/test-' . bin2hex(random_bytes(3)) . '.pdf']);
    $fid = (int) $pdo->lastInsertId();
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => $dosar['id'], 'titlu' => "Comunicat $marca", 'tip' => 'document', 'fisier_id' => $fid]);
    $doc = $pdo->query("SELECT * FROM meniu WHERE titlu = " . $pdo->quote("Comunicat $marca"))->fetch();
    ok('document creat sub dosar', $doc && (int) $doc['parent_id'] === (int) $dosar['id'] && (int) $doc['fisier_id'] === $fid);
    $ids[] = (int) $doc['id'];

    // editare
    $r = cerere('GET', '/admin/meniu/' . $doc['id']);
    ok('GET formular editare => 200 cu titlul', $r->getStatusCode() === 200 && str_contains(corp($r), "Comunicat $marca"));
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'id' => $doc['id'], 'sectiune_id' => $sid, 'parent_id' => $dosar['id'], 'titlu' => "Comunicat $marca v2", 'tip' => 'document', 'fisier_id' => $fid, 'vizibil' => '0']);
    $doc2 = $pdo->query('SELECT * FROM meniu WHERE id = ' . (int) $doc['id'])->fetch();
    ok('editare salvează titlu + vizibil', $doc2['titlu'] === "Comunicat $marca v2" && (int) $doc2['vizibil'] === 0);

    // arborele afișează ambele, cu data-id
    $r = cerere('GET', '/admin/meniu?sectiune=2021-2027');
    ok('arbore: nodurile au data-id', str_contains(corp($r), 'data-id="' . $dosar['id'] . '"') && str_contains(corp($r), 'data-id="' . $doc['id'] . '"'));

    // reordonare JSON: doc devine rădăcină, dosar copil al lui
    $req = (new Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('POST', '/admin/meniu/reordoneaza')
        ->withHeader('Content-Type', 'application/json')->withHeader('X-CSRF', 'abc');
    $req->getBody()->write(json_encode(['sectiune_id' => $sid, 'arbore' => [['id' => (int) $doc['id'], 'copii' => [['id' => (int) $dosar['id'], 'copii' => []]]]]]));
    $req->getBody()->rewind();
    $r = app()->handle($req);
    ok('reordoneaza => 200 ok', $r->getStatusCode() === 200 && (json_decode(corp($r), true)['ok'] ?? false) === true);
    ok('  dosar acum copil al documentului', (int) $pdo->query('SELECT parent_id FROM meniu WHERE id = ' . (int) $dosar['id'])->fetchColumn() === (int) $doc['id']);
    // dar arborele trimis doar cu rădăcina noastră NU atinge alte intrări ale secțiunii
    ok('  reordonarea parțială nu șterge alte intrări', (int) $pdo->query("SELECT COUNT(*) FROM meniu WHERE sectiune_id = $sid")->fetchColumn() >= 2);

    // ștergere cu descendenți
    $r = cerere('POST', '/admin/meniu/' . $doc['id'] . '/sterge', ['_csrf' => 'abc']);
    ok('sterge => 302 + flash 2 intrări', $r->getStatusCode() === 302 && str_contains($_SESSION['flash']['mesaj'] ?? '', '2'));
    ok('  ambele șterse', (int) $pdo->query('SELECT COUNT(*) FROM meniu WHERE id IN (' . (int) $doc['id'] . ',' . (int) $dosar['id'] . ')')->fetchColumn() === 0);
    $ids = [];
    $pdo->exec("DELETE FROM fisiere WHERE id = $fid");
} finally {
    foreach ($ids as $id) { $pdo->exec("DELETE FROM meniu WHERE id = $id"); }
    $pdo->exec("DELETE FROM utilizatori WHERE id = $uid");
}
final_test();
```

- [ ] **Step 2: Rulează, pică**

Run: `$PHP tests/admin_meniu_test.php` — Expected: FAIL pe `GET /admin/meniu => 200`.

- [ ] **Step 3: Controller**

`src/Admin/MeniuController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Admin;

use App\Fisiere\Repository as Fisiere;
use App\Meniu\Repository as Meniu;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class MeniuController
{
    use Helpers;

    private Auth $auth;
    private array $settings;
    private Meniu $meniu;
    private Fisiere $fisiere;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth     = $container['auth'];
        $this->settings = $container['settings'];
        $this->meniu    = $container['meniu'];
        $this->fisiere  = $container['fisiere'];
    }

    private function sectiuneCurenta(Request $request, ?int $id = null): array
    {
        $sectiuni = $this->meniu->sectiuni();
        $slug = (string) ($request->getQueryParams()['sectiune'] ?? '');
        foreach ($sectiuni as $s) {
            if (($id !== null && (int) $s['id'] === $id) || ($id === null && $s['slug'] === $slug)) {
                return $s;
            }
        }
        return $sectiuni[0];
    }

    public function index(Request $request, Response $response): Response
    {
        $sec = $this->sectiuneCurenta($request);
        return $this->render($response, 'admin/meniu.twig', [
            'sectiuni' => $this->meniu->sectiuni(),
            'sectiune' => $sec,
            'arbore'   => $this->meniu->arbore((int) $sec['id']),
        ]);
    }

    public function nou(Request $request, Response $response): Response
    {
        $sec = $this->sectiuneCurenta($request);
        $parent = (int) ($request->getQueryParams()['parent'] ?? 0) ?: null;
        return $this->formular($response, [
            'id' => null, 'sectiune_id' => (int) $sec['id'], 'parent_id' => $parent, 'titlu' => '', 'slug' => '',
            'tip' => 'document', 'url' => '', 'fisier_id' => null, 'sablon' => 'standard', 'vizibil' => 1, 'continut_html' => '',
        ], $sec);
    }

    public function editeaza(Request $request, Response $response, array $args): Response
    {
        $intrare = $this->meniu->gaseste((int) $args['id']);
        if ($intrare === null) {
            $this->flash('eroare', 'Intrarea nu există.');
            return $this->redirect($response, '/meniu');
        }
        return $this->formular($response, $intrare, $this->sectiuneCurenta($request, (int) $intrare['sectiune_id']));
    }

    private function formular(Response $response, array $intrare, array $sec, ?string $eroare = null): Response
    {
        $fisier = !empty($intrare['fisier_id']) ? $this->fisiere->gaseste((int) $intrare['fisier_id']) : null;
        return $this->render($response, 'admin/intrare.twig', [
            'intrare'  => $intrare,
            'sectiune' => $sec,
            'parinti'  => $this->optiuniParinte((int) $sec['id'], isset($intrare['id']) ? (int) $intrare['id'] : null),
            'fisier'   => $fisier,
            'galerie'  => [],
            'eroare'   => $eroare,
            'tipuri'   => Meniu::TIPURI,
        ]);
    }

    /** Lista plată „— — Titlu” pentru <select>, excluzând intrarea editată și descendenții ei. */
    private function optiuniParinte(int $sectiuneId, ?int $exclude): array
    {
        $out = [];
        $parcurge = function (array $noduri, int $nivel) use (&$parcurge, &$out, $exclude): void {
            foreach ($noduri as $n) {
                if ($exclude !== null && (int) $n['id'] === $exclude) { continue; }
                $out[] = ['id' => (int) $n['id'], 'eticheta' => str_repeat('— ', $nivel) . $n['titlu']];
                $parcurge($n['copii'], $nivel + 1);
            }
        };
        $parcurge($this->meniu->arbore($sectiuneId), 0);
        return $out;
    }

    public function salveaza(Request $request, Response $response): Response
    {
        $in  = (array) $request->getParsedBody();
        $id  = (int) ($in['id'] ?? 0) ?: null;
        $sid = (int) ($in['sectiune_id'] ?? 0);
        $sec = $this->sectiuneCurenta($request, $sid);
        $date = [
            'id' => $id, 'sectiune_id' => (int) $sec['id'],
            'parent_id' => ($in['parent_id'] ?? '') === '' ? null : (int) $in['parent_id'],
            'titlu' => trim((string) ($in['titlu'] ?? '')),
            'slug'  => trim((string) ($in['slug'] ?? '')),
            'tip'   => in_array($in['tip'] ?? '', Meniu::TIPURI, true) ? $in['tip'] : 'document',
            'url'   => trim((string) ($in['url'] ?? '')),
            'fisier_id' => (int) ($in['fisier_id'] ?? 0) ?: null,
            'sablon' => in_array($in['sablon'] ?? '', Meniu::SABLOANE, true) ? $in['sablon'] : 'standard',
            'vizibil' => (int) (($in['vizibil'] ?? '0') === '1'),
            'continut_html' => (string) ($in['continut_html'] ?? ''),
        ];
        if (!$this->csrfOk($request)) {
            return $this->formular($response, $date, $sec, 'Sesiunea a expirat. Trimite din nou.');
        }
        if ($date['titlu'] === '') {
            return $this->formular($response, $date, $sec, 'Titlul este obligatoriu.');
        }
        if ($date['tip'] === 'link' && !preg_match('#^https?://#i', $date['url'])) {
            return $this->formular($response, $date, $sec, 'Linkul trebuie să înceapă cu http:// sau https://.');
        }
        if ($date['tip'] === 'document' && ($date['fisier_id'] === null || $this->fisiere->gaseste($date['fisier_id']) === null)) {
            return $this->formular($response, $date, $sec, 'Alege un fișier pentru această intrare.');
        }
        if ($date['tip'] !== 'document') { $date['fisier_id'] = null; }
        if ($date['tip'] !== 'link') { $date['url'] = ''; }
        if ($date['tip'] !== 'pagina') { $date['continut_html'] = ''; $date['sablon'] = 'standard'; }

        if ($id === null) {
            $id = $this->meniu->creeaza($date);
            $this->flash('ok', 'Intrare adăugată.');
        } else {
            $this->meniu->actualizeaza($id, $date);
            $this->flash('ok', 'Intrare salvată.');
        }
        $this->dupaSalvare($id, $request); // Task 8: galerie
        return $this->redirect($response, '/meniu?sectiune=' . $sec['slug']);
    }

    /** Extins în Task 8 (imaginile galeriei). */
    private function dupaSalvare(int $id, Request $request): void {}

    public function sterge(Request $request, Response $response, array $args): Response
    {
        $intrare = $this->meniu->gaseste((int) $args['id']);
        if ($intrare !== null && $this->csrfOk($request)) {
            $n = $this->meniu->sterge((int) $intrare['id']);
            $this->flash('ok', "$n intrare(i) ștearsă(e).");
        }
        $sec = $intrare ? $this->sectiuneCurenta($request, (int) $intrare['sectiune_id']) : $this->sectiuneCurenta($request);
        return $this->redirect($response, '/meniu?sectiune=' . $sec['slug']);
    }

    public function reordoneaza(Request $request, Response $response): Response
    {
        if (!$this->csrfOk($request)) {
            return $this->json($response, ['eroare' => 'CSRF'], 403);
        }
        $in = (array) $request->getParsedBody();
        $sid = (int) ($in['sectiune_id'] ?? 0);
        $arbore = $in['arbore'] ?? null;
        if ($sid <= 0 || !is_array($arbore)) {
            return $this->json($response, ['eroare' => 'Date lipsă.'], 422);
        }
        try {
            $this->meniu->reordoneaza($sid, $arbore);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['eroare' => $e->getMessage()], 422);
        }
        return $this->json($response, ['ok' => true]);
    }
}
```

Rute (grup admin, `use App\Admin\MeniuController;`):

```php
$mc = fn() => new MeniuController($twig, $container);
$g->get('/meniu',                     fn($rq, $rs) => $mc()->index($rq, $rs));
$g->get('/meniu/nou',                 fn($rq, $rs) => $mc()->nou($rq, $rs));
$g->post('/meniu/salveaza',           fn($rq, $rs) => $mc()->salveaza($rq, $rs));
$g->post('/meniu/reordoneaza',        fn($rq, $rs) => $mc()->reordoneaza($rq, $rs));
$g->get('/meniu/{id:[0-9]+}',         fn($rq, $rs, $a) => $mc()->editeaza($rq, $rs, $a));
$g->post('/meniu/{id:[0-9]+}/sterge', fn($rq, $rs, $a) => $mc()->sterge($rq, $rs, $a));
```

- [ ] **Step 4: Template-uri**

`templates/admin/meniu.twig`:

```twig
{% extends 'admin/layout.twig' %}
{% block title %}Meniu · {{ sectiune.titlu }}{% endblock %}
{% block content %}
<ul class="nav nav-tabs mb-3">
    {% for s in sectiuni %}
        <li class="nav-item"><a class="nav-link {{ s.id == sectiune.id ? 'active' }}" href="{{ base }}{{ admin_path }}/meniu?sectiune={{ s.slug }}">{{ s.titlu }}</a></li>
    {% endfor %}
</ul>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 m-0">Meniul secțiunii {{ sectiune.slug }}</h1>
    <a class="btn btn-primary" href="{{ base }}{{ admin_path }}/meniu/nou?sectiune={{ sectiune.slug }}">+ Adaugă intrare</a>
</div>
<p class="text-muted small">Trage intrările ca să le reordonezi sau să le muți sub altă intrare. Se salvează automat.</p>
<div id="adm-arbore" data-sectiune-id="{{ sectiune.id }}" data-url="{{ base }}{{ admin_path }}/meniu/reordoneaza">
    <ol class="adm-arbore list-unstyled">
        {% for nod in arbore %}{% include 'admin/_meniu_nod.twig' with {nod: nod, sectiune: sectiune} %}{% endfor %}
    </ol>
</div>
<div id="adm-arbore-stare" class="small text-muted mt-2" aria-live="polite"></div>
{% endblock %}
{% block scripts %}<script src="{{ base }}/assets/vendor/sortable/Sortable.min.js"></script>{% endblock %}
```

`templates/admin/_meniu_nod.twig`:

```twig
{% set icon = {pagina: '📄', document: '📎', dosar: '📁', link: '🔗', galerie: '🖼️'} %}
<li class="adm-nod" data-id="{{ nod.id }}">
    <div class="adm-nod__rand {{ nod.vizibil ? '' : 'adm-nod__rand--ascuns' }}">
        <span class="adm-nod__grip" title="Trage">⋮⋮</span>
        <span class="adm-nod__tip" title="{{ nod.tip }}">{{ icon[nod.tip] }}</span>
        <a class="adm-nod__titlu" href="{{ base }}{{ admin_path }}/meniu/{{ nod.id }}">{{ nod.titlu }}</a>
        {% if not nod.vizibil %}<span class="badge text-bg-secondary">ascuns</span>{% endif %}
        <span class="adm-nod__actiuni">
            <a class="btn btn-sm btn-outline-secondary" href="{{ base }}{{ admin_path }}/meniu/nou?sectiune={{ sectiune.slug }}&parent={{ nod.id }}">+ sub</a>
            <a class="btn btn-sm btn-outline-primary" href="{{ base }}{{ admin_path }}/meniu/{{ nod.id }}">Editează</a>
            <form class="d-inline" method="post" action="{{ base }}{{ admin_path }}/meniu/{{ nod.id }}/sterge"
                  onsubmit="return confirm('Ștergi „{{ nod.titlu|e('js') }}”{% if nod.copii|length %} și cele {{ nod.copii|length }} intrări de sub ea{% endif %}?')">
                <input type="hidden" name="_csrf" value="{{ csrf }}">
                <button class="btn btn-sm btn-outline-danger" type="submit">Șterge</button>
            </form>
        </span>
    </div>
    <ol class="adm-arbore list-unstyled">
        {% for c in nod.copii %}{% include 'admin/_meniu_nod.twig' with {nod: c, sectiune: sectiune} %}{% endfor %}
    </ol>
</li>
```

`templates/admin/intrare.twig`:

```twig
{% extends 'admin/layout.twig' %}
{% block title %}{{ intrare.id ? 'Editează intrarea' : 'Intrare nouă' }}{% endblock %}
{% block head %}<link rel="stylesheet" href="{{ base }}/assets/vendor/quill/quill.snow.css">{% endblock %}
{% block content %}
<h1 class="h4 mb-3">{{ intrare.id ? 'Editează intrarea' : 'Intrare nouă' }} <small class="text-muted">· {{ sectiune.titlu }}</small></h1>
{% if eroare %}<div class="alert alert-danger" role="alert">{{ eroare }}</div>{% endif %}
<form method="post" action="{{ base }}{{ admin_path }}/meniu/salveaza" id="f-intrare" data-tip="{{ intrare.tip }}" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="{{ csrf }}">
    <input type="hidden" name="id" value="{{ intrare.id }}">
    <input type="hidden" name="sectiune_id" value="{{ sectiune.id }}">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="mb-3"><label class="form-label" for="f-titlu">Titlu <span class="text-danger">*</span></label>
                <input class="form-control" id="f-titlu" name="titlu" value="{{ intrare.titlu }}" required></div>
            <div class="mb-3"><label class="form-label" for="f-tip">Tip</label>
                <select class="form-select" id="f-tip" name="tip">
                    {% set etichete = {document: 'Document (link către un fișier)', dosar: 'Dosar (doar grupează intrări)', pagina: 'Pagină (text cu imagini)', link: 'Link extern', galerie: 'Galerie foto'} %}
                    {% for t in tipuri %}<option value="{{ t }}" {{ intrare.tip == t ? 'selected' }}>{{ etichete[t] }}</option>{% endfor %}
                </select></div>

            <div class="adm-camp" data-pentru="document">
                <label class="form-label">Fișier <span class="text-danger">*</span></label>
                <div class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="fisier_id" id="f-fisier-id" value="{{ intrare.fisier_id }}">
                    <span class="form-control bg-light" id="f-fisier-nume">{{ fisier ? fisier.nume_afisat : 'Niciun fișier ales' }}</span>
                    <button class="btn btn-outline-primary text-nowrap" type="button" data-picker="fisier">Alege din Fișiere</button>
                </div>
                <div class="form-text">Nu l-ai încărcat încă? Deschide „Alege din Fișiere” și încarcă-l acolo.</div>
            </div>

            <div class="adm-camp" data-pentru="link">
                <label class="form-label" for="f-url">Adresa (URL) <span class="text-danger">*</span></label>
                <input class="form-control" id="f-url" name="url" value="{{ intrare.url }}" placeholder="https://…">
            </div>

            <div class="adm-camp" data-pentru="pagina">
                <label class="form-label">Conținut</label>
                <div id="f-editor" class="bg-white">{{ intrare.continut_html|raw }}</div>
                <textarea name="continut_html" id="f-continut" hidden>{{ intrare.continut_html }}</textarea>
                <label class="form-label mt-3" for="f-sablon">Șablon</label>
                <select class="form-select" id="f-sablon" name="sablon">
                    <option value="standard" {{ intrare.sablon == 'standard' ? 'selected' }}>Standard</option>
                    <option value="contact" {{ intrare.sablon == 'contact' ? 'selected' }}>Contact (adaugă formularul de contact sub text)</option>
                </select>
            </div>

            <div class="adm-camp" data-pentru="galerie">
                {% include 'admin/_galerie_camp.twig' ignore missing %}
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-body">
                <div class="mb-3"><label class="form-label" for="f-parent">Se află sub</label>
                    <select class="form-select" id="f-parent" name="parent_id">
                        <option value="">— nivelul principal —</option>
                        {% for p in parinti %}<option value="{{ p.id }}" {{ intrare.parent_id == p.id ? 'selected' }}>{{ p.eticheta }}</option>{% endfor %}
                    </select></div>
                <div class="mb-3"><label class="form-label" for="f-slug">Adresă (slug)</label>
                    <input class="form-control" id="f-slug" name="slug" value="{{ intrare.slug }}" placeholder="se generează din titlu"></div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="f-vizibil" name="vizibil" value="1" {{ intrare.vizibil ? 'checked' }}>
                    <label class="form-check-label" for="f-vizibil">Vizibil pe site</label></div>
                <button class="btn btn-primary w-100" type="submit">Salvează</button>
                <a class="btn btn-link w-100" href="{{ base }}{{ admin_path }}/meniu?sectiune={{ sectiune.slug }}">Renunță</a>
            </div>
        </div>
    </div>
</form>

<div class="modal fade" id="adm-picker" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h2 class="h5 m-0">Alege un fișier</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button></div>
    <div class="modal-body p-0"><iframe id="adm-picker-frame" class="adm-picker__frame" title="Fișiere"></iframe></div>
</div></div></div>
{% endblock %}
{% block scripts %}<script src="{{ base }}/assets/vendor/quill/quill.min.js"></script>{% endblock %}
```

- [ ] **Step 5: JS + CSS**

Run: `mkdir -p assets/vendor/sortable && curl -sL -o assets/vendor/sortable/Sortable.min.js https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js`

În `assets/js/admin.js` adaugă:

```js
// --- Arbore meniu: drag & drop imbricat, salvare automată ---
(function () {
  var root = document.getElementById('adm-arbore');
  if (!root || !window.Sortable) return;
  var stare = document.getElementById('adm-arbore-stare');
  function citeste(ol) {
    return Array.prototype.filter.call(ol.children, function (li) { return li.classList.contains('adm-nod'); })
      .map(function (li) { return { id: parseInt(li.dataset.id, 10), copii: citeste(li.querySelector(':scope > ol')) }; });
  }
  function salveaza() {
    stare.textContent = 'Se salvează…';
    fetch(root.dataset.url, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': window.CSRF },
      body: JSON.stringify({ sectiune_id: parseInt(root.dataset.sectiuneId, 10), arbore: citeste(root.querySelector(':scope > ol')) })
    }).then(function (r) { return r.json(); }).then(function (j) {
      stare.textContent = j.ok ? 'Ordinea a fost salvată.' : ('Eroare: ' + (j.eroare || 'necunoscută'));
    }).catch(function () { stare.textContent = 'Eroare de rețea. Reîncarcă pagina.'; });
  }
  root.querySelectorAll('ol.adm-arbore').forEach(function (ol) {
    new Sortable(ol, { group: 'meniu', handle: '.adm-nod__grip', animation: 150, fallbackOnBody: true, swapThreshold: 0.65, onEnd: salveaza });
  });
})();

// --- Formular intrare: câmpuri după tip + picker + Quill ---
(function () {
  var f = document.getElementById('f-intrare');
  if (!f) return;
  var tip = document.getElementById('f-tip');
  function arata() {
    f.querySelectorAll('.adm-camp').forEach(function (c) { c.hidden = c.dataset.pentru !== tip.value; });
  }
  tip.addEventListener('change', arata); arata();

  var modalEl = document.getElementById('adm-picker'), frame = document.getElementById('adm-picker-frame');
  var modal = modalEl ? new bootstrap.Modal(modalEl) : null, tinta = null;
  document.querySelectorAll('[data-picker]').forEach(function (b) {
    b.addEventListener('click', function () {
      tinta = b.dataset.picker;
      frame.src = window.ADMIN_BASE + '/fisiere?picker=1' + (tinta === 'imagine' ? '&imagini=1' : '');
      modal.show();
    });
  });
  window.addEventListener('message', function (ev) {
    if (!ev.data || ev.data.tip !== 'fisier') return;
    if (tinta === 'fisier') {
      document.getElementById('f-fisier-id').value = ev.data.id;
      document.getElementById('f-fisier-nume').textContent = ev.data.nume;
    } else if (tinta === 'imagine' && window._quill) {
      var range = window._quill.getSelection(true);
      window._quill.insertEmbed(range ? range.index : 0, 'image', ev.data.url, 'user');
    } else if (tinta === 'galerie' && window._galerieAdauga) {
      window._galerieAdauga(ev.data);
    }
    modal.hide();
  });

  var ed = document.getElementById('f-editor');
  if (ed && window.Quill) {
    var q = new Quill(ed, { theme: 'snow', modules: { toolbar: {
      container: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link', 'image'], ['clean']],
      handlers: { image: function () { tinta = 'imagine'; frame.src = window.ADMIN_BASE + '/fisiere?picker=1&imagini=1'; modal.show(); } }
    } } });
    window._quill = q;
    f.addEventListener('submit', function () { document.getElementById('f-continut').value = q.root.innerHTML; });
  }
})();
```

În `assets/css/admin.css` adaugă:

```css
.adm-arbore { margin: 0; padding-left: 0; }
.adm-arbore .adm-arbore { padding-left: 1.75rem; min-height: 6px; }
.adm-nod__rand { display: flex; align-items: center; gap: .5rem; background: #fff; border: 1px solid #e3e8ee; border-radius: 6px; padding: .4rem .6rem; margin: .25rem 0; }
.adm-nod__rand--ascuns { opacity: .6; }
.adm-nod__grip { cursor: grab; color: #9aa5b1; user-select: none; }
.adm-nod__titlu { flex: 1; color: #1b2430; text-decoration: none; }
.adm-nod__actiuni { display: flex; gap: .25rem; }
.sortable-ghost { opacity: .4; }
.adm-picker__frame { width: 100%; height: 70vh; border: 0; }
#f-editor { min-height: 320px; }
```

- [ ] **Step 6: Rulează testul + verifică în browser**

Run: `$PHP tests/admin_meniu_test.php` — Expected: toate PASS.
Browser: creează un dosar „Noutăți”, un document sub el (prin picker), trage documentul la nivelul principal; reîncarcă pagina: ordinea persistă.

- [ ] **Step 7: Commit**

```bash
git add src/Admin/MeniuController.php src/Routes.php templates/admin assets tests/admin_meniu_test.php
git commit -m "M1: ecran Meniu — arbore pe sectiune, formular intrare, stergere, reordonare drag&drop"
```

### Task 8: Pagină cu WYSIWYG (Quill vendorat, curățare HTML) și Galerie (imagini, ordine, legende)

**Files:**
- Create: `assets/vendor/quill/quill.min.js`, `assets/vendor/quill/quill.snow.css` (copiate din `C:/laragon/www/motociclete/assets/vendor/quill/`), `src/Support/Html.php`, `src/Meniu/GalerieRepository.php`, `templates/admin/_galerie_camp.twig`, `tests/admin_pagina_galerie_test.php`
- Modify: `src/Admin/MeniuController.php` (`formular()` încarcă galeria; `salveaza()` curăță HTML-ul; `dupaSalvare()` scrie galeria), `src/Bootstrap.php` (`$container['galerie'] = new Meniu\GalerieRepository($container['db']);`), `assets/js/admin.js`

**Interfaces:**
- Produces:
  - `App\Support\Html::curata(string $html): string` — păstrează doar `p, br, h2, h3, strong, b, em, i, u, ul, ol, li, a[href,target,rel], img[src,alt], blockquote, iframe[src,width,height,allowfullscreen] (doar youtube.com / youtube-nocookie.com)`; scoate `script/style/on*`, `javascript:`; adaugă `rel="noopener"` la `target=_blank`.
  - `GalerieRepository::imagini(int $meniuId): array` (`{id, fisier_id, ordine, legenda, cale, nume_afisat}` ordonat), `GalerieRepository::seteaza(int $meniuId, array $imagini): void` — `imagini = [{fisier_id, legenda}]` în ordinea dorită; înlocuiește setul (DELETE + INSERT în tranzacție); ignoră `fisier_id` care nu e imagine.
  - Formularul trimite `galerie_fisier_id[]` și `galerie_legenda[]` paralele.

- [ ] **Step 1: Test**

`tests/admin_pagina_galerie_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Support\Html;

ok('Html: scoate script', !str_contains(Html::curata('<p>a</p><script>alert(1)</script>'), 'script'));
ok('Html: scoate onclick', !str_contains(Html::curata('<p onclick="x()">a</p>'), 'onclick'));
ok('Html: scoate javascript:', !str_contains(Html::curata('<a href="javascript:alert(1)">x</a>'), 'javascript'));
ok('Html: păstrează p/strong/a', Html::curata('<p><strong>b</strong> <a href="https://x.ro">l</a></p>') === '<p><strong>b</strong> <a href="https://x.ro">l</a></p>');
ok('Html: target=_blank primește rel=noopener', str_contains(Html::curata('<a href="https://x.ro" target="_blank">l</a>'), 'rel="noopener"'));
ok('Html: iframe youtube păstrat', str_contains(Html::curata('<iframe src="https://www.youtube.com/embed/abc" allowfullscreen></iframe>'), '<iframe'));
ok('Html: iframe alt domeniu scos', !str_contains(Html::curata('<iframe src="https://evil.com/x"></iframe>'), '<iframe'));
ok('Html: diacritice intacte', Html::curata('<p>Șirna și Păulești</p>') === '<p>Șirna și Păulești</p>');

$pdo = pdo();
$uid = logheaza_test();
$sid = (int) $pdo->query("SELECT id FROM sectiuni WHERE slug='2014-2020'")->fetchColumn();
$marca = bin2hex(random_bytes(3));
$fids = []; $mids = [];
try {
    foreach (['a.png' => 'image/png', 'b.png' => 'image/png', 'c.pdf' => 'application/pdf'] as $n => $m) {
        $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES (:n, :c, :m, 1)')->execute(['n' => $n, 'c' => "2026/01/$marca-$n", 'm' => $m]);
        $fids[$n] = (int) $pdo->lastInsertId();
    }
    // pagina: HTML curățat la salvare
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => '', 'titlu' => "Pagina $marca", 'tip' => 'pagina', 'sablon' => 'contact', 'vizibil' => '1',
        'continut_html' => '<p>Text</p><script>x()</script><img src="/fisiere/2026/01/a.png" onerror="x()">']);
    $p = $pdo->query('SELECT * FROM meniu WHERE titlu = ' . $pdo->quote("Pagina $marca"))->fetch();
    $mids[] = (int) $p['id'];
    ok('pagina salvată cu sablon contact', $p['sablon'] === 'contact');
    ok('  HTML curățat (fără script/onerror, cu img)', !str_contains($p['continut_html'], 'script') && !str_contains($p['continut_html'], 'onerror') && str_contains($p['continut_html'], '<img'));

    // galerie: 2 imagini + 1 pdf (ignorat), ordine + legende
    $r = cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'sectiune_id' => $sid, 'parent_id' => '', 'titlu' => "Galerie $marca", 'tip' => 'galerie', 'vizibil' => '1',
        'galerie_fisier_id' => [$fids['b.png'], $fids['c.pdf'], $fids['a.png']], 'galerie_legenda' => ['Doi', 'x', 'Unu']]);
    $g = $pdo->query('SELECT * FROM meniu WHERE titlu = ' . $pdo->quote("Galerie $marca"))->fetch();
    $mids[] = (int) $g['id'];
    $gal = (new App\Meniu\GalerieRepository(new App\Database(settings()['db'])))->imagini((int) $g['id']);
    ok('galerie: 2 imagini (pdf ignorat)', count($gal) === 2);
    ok('  ordinea b, a', $gal[0]['fisier_id'] == $fids['b.png'] && $gal[1]['fisier_id'] == $fids['a.png']);
    ok('  legendele', $gal[0]['legenda'] === 'Doi' && $gal[1]['legenda'] === 'Unu');
    ok('  cale din fisiere', str_ends_with($gal[0]['cale'], 'b.png'));

    $r = cerere('GET', '/admin/meniu/' . $g['id']);
    ok('formularul galeriei listează imaginile', substr_count(corp($r), 'name="galerie_fisier_id[]"') === 2);

    // re-salvare cu o singură imagine înlocuiește setul
    cerere('POST', '/admin/meniu/salveaza', ['_csrf' => 'abc', 'id' => $g['id'], 'sectiune_id' => $sid, 'parent_id' => '', 'titlu' => "Galerie $marca", 'tip' => 'galerie', 'vizibil' => '1',
        'galerie_fisier_id' => [$fids['a.png']], 'galerie_legenda' => ['']]);
    ok('re-salvarea înlocuiește setul', (int) $pdo->query('SELECT COUNT(*) FROM galerie_imagini WHERE meniu_id = ' . (int) $g['id'])->fetchColumn() === 1);
} finally {
    foreach ($mids as $id) { $pdo->exec("DELETE FROM meniu WHERE id = $id"); }
    foreach ($fids as $id) { $pdo->exec("DELETE FROM fisiere WHERE id = $id"); }
    $pdo->exec("DELETE FROM utilizatori WHERE id = $uid");
}
final_test();
```

- [ ] **Step 2: Rulează, pică** — `Class "App\Support\Html" not found`.

- [ ] **Step 3: Html::curata**

`src/Support/Html.php`:

```php
<?php
declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;

final class Html
{
    private const PERMISE = [
        'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'a' => ['href', 'target', 'rel'], 'img' => ['src', 'alt'],
        'iframe' => ['src', 'width', 'height', 'allowfullscreen'],
    ];

    public static function curata(string $html): string
    {
        if (trim($html) === '') { return ''; }
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="radacina">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $rad = $dom->getElementById('radacina');
        if ($rad === null) { return ''; }
        self::parcurge($rad);
        $out = '';
        foreach ($rad->childNodes as $c) { $out .= $dom->saveHTML($c); }
        return trim($out);
    }

    private static function parcurge(DOMElement $el): void
    {
        for ($i = $el->childNodes->length - 1; $i >= 0; $i--) {
            $c = $el->childNodes->item($i);
            if (!$c instanceof DOMElement) {
                if ($c->nodeType === XML_COMMENT_NODE) { $el->removeChild($c); }
                continue;
            }
            $tag = strtolower($c->tagName);
            if (in_array($tag, ['script', 'style', 'object', 'embed'], true)) {
                $el->removeChild($c);
                continue;
            }
            if (!isset(self::PERMISE[$tag])) {
                // Tag necunoscut (div, span, font…): păstrăm doar copiii.
                self::parcurge($c);
                while ($c->firstChild) { $el->insertBefore($c->firstChild, $c); }
                $el->removeChild($c);
                continue;
            }
            foreach (iterator_to_array($c->attributes) as $atr) {
                $n = strtolower($atr->name);
                $v = trim($atr->value);
                $ok = in_array($n, self::PERMISE[$tag], true)
                    && !str_starts_with($n, 'on')
                    && !preg_match('/^\s*(javascript|data|vbscript):/i', $v);
                if ($ok && $tag === 'iframe' && $n === 'src' && !preg_match('#^https://(www\.)?(youtube\.com|youtube-nocookie\.com)/embed/#', $v)) { $ok = false; }
                if (!$ok) { $c->removeAttribute($atr->name); }
            }
            if ($tag === 'iframe' && !$c->hasAttribute('src')) { $el->removeChild($c); continue; }
            if ($tag === 'a' && strtolower($c->getAttribute('target')) === '_blank') { $c->setAttribute('rel', 'noopener'); }
            self::parcurge($c);
        }
    }
}
```

- [ ] **Step 4: GalerieRepository + integrare în controller + template**

`src/Meniu/GalerieRepository.php`:

```php
<?php
declare(strict_types=1);

namespace App\Meniu;

use App\Database;
use PDO;

final class GalerieRepository
{
    private PDO $pdo;

    public function __construct(Database $db) { $this->pdo = $db->pdo(); }

    public function imagini(int $meniuId): array
    {
        $st = $this->pdo->prepare('SELECT g.id, g.fisier_id, g.ordine, g.legenda, f.cale, f.nume_afisat
            FROM galerie_imagini g JOIN fisiere f ON f.id = g.fisier_id WHERE g.meniu_id = :m ORDER BY g.ordine, g.id');
        $st->execute(['m' => $meniuId]);
        return $st->fetchAll();
    }

    /** @param array<int, array{fisier_id:int, legenda:string}> $imagini */
    public function seteaza(int $meniuId, array $imagini): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM galerie_imagini WHERE meniu_id = :m')->execute(['m' => $meniuId]);
            $verif = $this->pdo->prepare("SELECT id FROM fisiere WHERE id = :id AND mime IN ('image/jpeg','image/png','image/webp')");
            $ins = $this->pdo->prepare('INSERT INTO galerie_imagini (meniu_id, fisier_id, ordine, legenda) VALUES (:m, :f, :o, :l)');
            $o = 0;
            foreach ($imagini as $img) {
                $verif->execute(['id' => (int) $img['fisier_id']]);
                if (!$verif->fetch()) { continue; }
                $ins->execute(['m' => $meniuId, 'f' => (int) $img['fisier_id'], 'o' => $o++, 'l' => mb_substr(trim((string) ($img['legenda'] ?? '')), 0, 255)]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
```

În `MeniuController`: proprietate `private GalerieRepository $galerie;` (`$container['galerie']`); în `formular()` → `'galerie' => isset($intrare['id']) ? $this->galerie->imagini((int) $intrare['id']) : []`; în `salveaza()` înainte de creare: `if ($date['tip'] === 'pagina') { $date['continut_html'] = \App\Support\Html::curata($date['continut_html']); }`; în `dupaSalvare()`:

```php
private function dupaSalvare(int $id, Request $request): void
{
    $in = (array) $request->getParsedBody();
    if (($in['tip'] ?? '') !== 'galerie') { return; }
    $ids = (array) ($in['galerie_fisier_id'] ?? []);
    $leg = (array) ($in['galerie_legenda'] ?? []);
    $set = [];
    foreach (array_values($ids) as $i => $fid) {
        $set[] = ['fisier_id' => (int) $fid, 'legenda' => (string) ($leg[$i] ?? '')];
    }
    $this->galerie->seteaza($id, $set);
}
```

`templates/admin/_galerie_camp.twig`:

```twig
<label class="form-label">Imagini</label>
<p class="form-text">Adaugă imagini din Fișiere (le poți încărca acolo, mai multe deodată). Trage-le ca să schimbi ordinea.</p>
<button class="btn btn-outline-primary mb-2" type="button" data-picker="galerie">+ Adaugă imagine</button>
<ol class="adm-galerie list-unstyled" id="adm-galerie">
    {% for g in galerie %}
    <li class="adm-galerie__item" data-fisier-id="{{ g.fisier_id }}">
        <span class="adm-nod__grip">⋮⋮</span>
        <img src="{{ base }}{{ fisiere_url }}/{{ g.cale }}" alt="">
        <input type="hidden" name="galerie_fisier_id[]" value="{{ g.fisier_id }}">
        <input class="form-control form-control-sm" name="galerie_legenda[]" value="{{ g.legenda }}" placeholder="Legendă (opțional)">
        <button class="btn btn-sm btn-outline-danger" type="button" data-scoate>Scoate</button>
    </li>
    {% endfor %}
</ol>
<template id="adm-galerie-sablon">
    <li class="adm-galerie__item">
        <span class="adm-nod__grip">⋮⋮</span>
        <img src="" alt="">
        <input type="hidden" name="galerie_fisier_id[]" value="">
        <input class="form-control form-control-sm" name="galerie_legenda[]" value="" placeholder="Legendă (opțional)">
        <button class="btn btn-sm btn-outline-danger" type="button" data-scoate>Scoate</button>
    </li>
</template>
```

Scoate `ignore missing` din include-ul din `intrare.twig` (fișierul există acum). În `admin.js` adaugă (în IIFE-ul formularului, după picker):

```js
  var gal = document.getElementById('adm-galerie');
  if (gal) {
    if (window.Sortable) new Sortable(gal, { handle: '.adm-nod__grip', animation: 150 });
    window._galerieAdauga = function (d) {
      if (gal.querySelector('[data-fisier-id="' + d.id + '"]')) return;
      var li = document.getElementById('adm-galerie-sablon').content.firstElementChild.cloneNode(true);
      li.dataset.fisierId = d.id; li.querySelector('img').src = d.url; li.querySelector('input[type=hidden]').value = d.id;
      gal.appendChild(li);
    };
    gal.addEventListener('click', function (ev) { var b = ev.target.closest('[data-scoate]'); if (b) b.closest('li').remove(); });
  }
```

Și în `meniu.twig`/`intrare.twig` scripturile Sortable trebuie încărcate ÎNAINTE de `admin.js` — mută `<script src=".../Sortable.min.js">` și `quill.min.js` în `layout.twig` înaintea lui `admin.js` (sunt mici, se încarcă pe toate paginile admin) și șterge blocurile `scripts` din cele două template-uri.

CSS: `.adm-galerie__item{display:flex;align-items:center;gap:.5rem;background:#fff;border:1px solid #e3e8ee;border-radius:6px;padding:.4rem;margin:.25rem 0}.adm-galerie__item img{width:64px;height:48px;object-fit:cover;border-radius:4px}`

- [ ] **Step 5: Rulează testele (toate de până acum)**

Run: `for t in tests/*_test.php; do $PHP "$t" || echo "FAIL: $t"; done`
Expected: fiecare se încheie cu `OK — toate testele trec.`
Browser: creează o pagină cu titlu, listă, o imagine inserată din editor și un iframe YouTube lipit în HTML; salvează; redeschide: conținutul e intact. Creează o galerie cu 3 imagini, reordonează, salvează.

- [ ] **Step 6: Commit**

```bash
git add assets src templates tests
git commit -m "M1: pagina cu Quill + curatare HTML, galerie cu ordine si legende"
```

### Task 9: Setări, Utilizatori (invitație + parolă uitată prin email), Mesaje

**Files:**
- Create: `src/Setari/Repository.php`, `src/Mail/Mailer.php` (copiat din `C:/laragon/www/pestelocal/src/Mail/Mailer.php`, prefix log `[flagprahova]`), `src/Admin/UtilizatoriRepository.php`, `src/Admin/PasswordTokenRepository.php` (copiat din pestelocal, tabela `parola_tokens`, coloana `utilizator_id`, TTL 7 zile), `src/Admin/UtilizatoriController.php`, `src/Admin/SetariController.php`, `src/Admin/MesajeController.php`, `templates/admin/setari.twig`, `templates/admin/utilizatori.twig`, `templates/admin/parola_uitata.twig`, `templates/admin/parola.twig`, `templates/admin/mesaje.twig`, `templates/mail/parola.twig`, `tests/admin_setari_utilizatori_test.php`
- Modify: `src/Bootstrap.php` (`$container['setari']`, `$container['mailer']`, `$container['utilizatori']`, `$container['parola_tokens']`, `$container['parola_throttle'] = new Admin\LoginThrottle($container['db'], 'parola')`), `src/Routes.php`, `src/Admin/LoginController.php`

**Interfaces:**
- `Setari\Repository::get(string $cheie, string $implicit = ''): string`, `set(string $cheie, string $valoare): void`, `toate(): array`. Chei editabile: `contact_email_destinatar`, `landing_titlu`, `landing_text`, `footer_text`.
- `UtilizatoriRepository::toti(): array`, `gaseste(int): ?array`, `gasesteDupaEmail(string): ?array`, `creeaza(string $email, string $nume): int` (cu `parola_hash = ''`), `seteazaParola(int $id, string $parola): void`, `sterge(int $id): bool` (refuză când e ultimul utilizator), `numara(): int`.
- `PasswordTokenRepository::issue(int $utilizatorId): ?string` (token brut), `consume(string $raw): ?int` (id utilizator, o singură dată, neexpirat), `TTL_ZILE = 7`.
- Rute:
  - `GET/POST {admin}/setari` — formular cu cele 4 chei.
  - `GET {admin}/utilizatori`, `POST {admin}/utilizatori/adauga` (email + nume → creează + trimite link de setare), `POST {admin}/utilizatori/{id}/trimite-link`, `POST {admin}/utilizatori/{id}/sterge` (nu pe sine, nu ultimul).
  - În afara grupului (fără sesiune): `GET/POST {admin}/parola-uitata` (email → dacă există, trimite link; mesaj identic indiferent; throttle scope `parola`), `GET/POST {admin}/parola/{token}` (formular parolă ≥ 10 caractere + confirmare → `consume` + `seteazaParola` → redirect la login cu `?ok=parola`).
  - `GET {admin}/mesaje` — tabel `mesaje_contact` descrescător, 100 pe pagină (`?p=`).
- Link-ul din email: `{app.url}{base_path}{admin}/parola/{token}`; template `templates/mail/parola.twig` cu variabilele `nume`, `link`, `zile`.

- [ ] **Step 1: Test**

`tests/admin_setari_utilizatori_test.php`:

```php
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
```

- [ ] **Step 2: Rulează, pică** — 404 pe `/admin/setari`.

- [ ] **Step 3: Repository-uri**

`src/Setari/Repository.php`:

```php
<?php
declare(strict_types=1);

namespace App\Setari;

use App\Database;
use PDO;

final class Repository
{
    public const CHEI = ['contact_email_destinatar', 'landing_titlu', 'landing_text', 'footer_text'];
    private PDO $pdo;
    private ?array $cache = null;

    public function __construct(Database $db) { $this->pdo = $db->pdo(); }

    public function toate(): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            foreach ($this->pdo->query('SELECT cheie, valoare FROM setari')->fetchAll() as $r) {
                $this->cache[$r['cheie']] = (string) $r['valoare'];
            }
        }
        return $this->cache;
    }

    public function get(string $cheie, string $implicit = ''): string
    {
        return $this->toate()[$cheie] ?? $implicit;
    }

    public function set(string $cheie, string $valoare): void
    {
        $this->pdo->prepare('INSERT INTO setari (cheie, valoare) VALUES (:c, :v) ON DUPLICATE KEY UPDATE valoare = VALUES(valoare)')
            ->execute(['c' => $cheie, 'v' => $valoare]);
        $this->cache = null;
    }
}
```

`src/Admin/UtilizatoriRepository.php`:

```php
<?php
declare(strict_types=1);

namespace App\Admin;

use App\Database;
use PDO;

final class UtilizatoriRepository
{
    private PDO $pdo;

    public function __construct(Database $db) { $this->pdo = $db->pdo(); }

    public function toti(): array
    {
        return $this->pdo->query('SELECT id, email, nume, ultimul_login, creat_la, parola_hash <> "" AS are_parola FROM utilizatori ORDER BY nume, email')->fetchAll();
    }

    public function numara(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM utilizatori')->fetchColumn();
    }

    public function gaseste(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM utilizatori WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function gasesteDupaEmail(string $email): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM utilizatori WHERE email = :e');
        $st->execute(['e' => $email]);
        return $st->fetch() ?: null;
    }

    public function creeaza(string $email, string $nume): int
    {
        $this->pdo->prepare('INSERT INTO utilizatori (email, nume, parola_hash) VALUES (:e, :n, "")')->execute(['e' => $email, 'n' => $nume]);
        return (int) $this->pdo->lastInsertId();
    }

    public function seteazaParola(int $id, string $parola): void
    {
        $this->pdo->prepare('UPDATE utilizatori SET parola_hash = :h WHERE id = :id')->execute(['h' => password_hash($parola, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public function sterge(int $id): bool
    {
        if ($this->numara() <= 1) { return false; }
        return $this->pdo->prepare('DELETE FROM utilizatori WHERE id = :id')->execute(['id' => $id]);
    }
}
```

`src/Admin/PasswordTokenRepository.php` — copiat din pestelocal; adaptări: tabela `parola_tokens`, coloanele `utilizator_id, token_hash, expira_la, folosit_la`; `issue(int $utilizatorId): ?string` invalidează tokenurile anterioare (`UPDATE parola_tokens SET folosit_la = NOW() WHERE utilizator_id = :u AND folosit_la IS NULL`), inserează `sha256(raw)` cu `expira_la = NOW() + INTERVAL 7 DAY`; `consume(string $raw): ?int` face `UPDATE ... SET folosit_la = NOW() WHERE token_hash = :h AND folosit_la IS NULL AND expira_la > NOW()` și, dacă `rowCount() === 1`, întoarce `utilizator_id`.

- [ ] **Step 4: Controllere**

`src/Admin/SetariController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Admin;

use App\Setari\Repository as Setari;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class SetariController
{
    use Helpers;
    private Auth $auth; private array $settings; private Setari $setari;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth = $container['auth']; $this->settings = $container['settings']; $this->setari = $container['setari'];
    }

    public function form(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/setari.twig', ['valori' => $this->setari->toate(), 'chei' => Setari::CHEI]);
    }

    public function salveaza(Request $request, Response $response): Response
    {
        if ($this->csrfOk($request)) {
            $in = (array) $request->getParsedBody();
            foreach (Setari::CHEI as $c) {
                if (array_key_exists($c, $in)) { $this->setari->set($c, trim((string) $in[$c])); }
            }
            $this->flash('ok', 'Setări salvate.');
        }
        return $this->redirect($response, '/setari');
    }
}
```

`src/Admin/UtilizatoriController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Admin;

use App\Mail\Mailer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class UtilizatoriController
{
    use Helpers;
    private Auth $auth; private array $settings; private UtilizatoriRepository $utilizatori;
    private PasswordTokenRepository $tokens; private Mailer $mailer; private LoginThrottle $throttle;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth = $container['auth']; $this->settings = $container['settings'];
        $this->utilizatori = $container['utilizatori']; $this->tokens = $container['parola_tokens'];
        $this->mailer = $container['mailer']; $this->throttle = $container['parola_throttle'];
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/utilizatori.twig', ['lista' => $this->utilizatori->toti()]);
    }

    public function adauga(Request $request, Response $response): Response
    {
        $in = (array) $request->getParsedBody();
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $nume  = trim((string) ($in['nume'] ?? ''));
        if (!$this->csrfOk($request) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('eroare', 'Adresa de email nu e validă.');
            return $this->redirect($response, '/utilizatori');
        }
        if ($this->utilizatori->gasesteDupaEmail($email) !== null) {
            $this->flash('eroare', 'Există deja un cont cu această adresă.');
            return $this->redirect($response, '/utilizatori');
        }
        $id = $this->utilizatori->creeaza($email, $nume);
        $trimis = $this->trimiteLink($id);
        $this->flash($trimis ? 'ok' : 'eroare', $trimis ? "Cont creat. Linkul de setare a parolei a fost trimis la $email." : 'Cont creat, dar emailul nu a putut fi trimis. Folosește „Trimite link”.');
        return $this->redirect($response, '/utilizatori');
    }

    public function trimiteLinkActiune(Request $request, Response $response, array $args): Response
    {
        if ($this->csrfOk($request) && $this->utilizatori->gaseste((int) $args['id']) !== null) {
            $ok = $this->trimiteLink((int) $args['id']);
            $this->flash($ok ? 'ok' : 'eroare', $ok ? 'Link trimis.' : 'Emailul nu a putut fi trimis.');
        }
        return $this->redirect($response, '/utilizatori');
    }

    public function sterge(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->csrfOk($request)) { return $this->redirect($response, '/utilizatori'); }
        if ($id === (int) ($this->auth->user()['id'] ?? 0)) {
            $this->flash('eroare', 'Nu îți poți șterge propriul cont.');
        } elseif (!$this->utilizatori->sterge($id)) {
            $this->flash('eroare', 'Nu poți șterge ultimul cont.');
        } else {
            $this->flash('ok', 'Cont șters.');
        }
        return $this->redirect($response, '/utilizatori');
    }

    /** Rută publică: formularul „parolă uitată”. */
    public function parolaUitata(Request $request, Response $response): Response
    {
        if ($request->getMethod() === 'GET') {
            return $this->render($response, 'admin/parola_uitata.twig', ['trimis' => false, 'utilizator' => null]);
        }
        $ip = ip_hash($request->getServerParams()['REMOTE_ADDR'] ?? null);
        if ($this->csrfOk($request) && !$this->throttle->tooMany($ip)) {
            $this->throttle->record($ip);
            $email = strtolower(trim((string) (((array) $request->getParsedBody())['email'] ?? '')));
            $u = $this->utilizatori->gasesteDupaEmail($email);
            if ($u !== null) { $this->trimiteLink((int) $u['id']); }
        }
        return $this->render($response, 'admin/parola_uitata.twig', ['trimis' => true, 'utilizator' => null]);
    }

    /** Rută publică: setarea parolei din link. */
    public function parola(Request $request, Response $response, array $args): Response
    {
        $token = (string) $args['token'];
        $st = $this->tokens->esteValabil($token);
        if (!$st) {
            $this->flash('eroare', 'Linkul a expirat sau a fost folosit. Cere unul nou.');
            return $this->redirect($response, '/parola-uitata');
        }
        if ($request->getMethod() === 'GET') {
            return $this->render($response, 'admin/parola.twig', ['eroare' => null, 'token' => $token, 'utilizator' => null]);
        }
        $in = (array) $request->getParsedBody();
        $p1 = (string) ($in['parola'] ?? ''); $p2 = (string) ($in['parola2'] ?? '');
        if (!$this->csrfOk($request)) {
            return $this->render($response, 'admin/parola.twig', ['eroare' => 'Sesiunea a expirat.', 'token' => $token, 'utilizator' => null]);
        }
        if (mb_strlen($p1) < 10 || $p1 !== $p2) {
            return $this->render($response, 'admin/parola.twig', ['eroare' => 'Parola trebuie să aibă cel puțin 10 caractere și să coincidă.', 'token' => $token, 'utilizator' => null]);
        }
        $uid = $this->tokens->consume($token);
        if ($uid === null) {
            return $this->redirect($response, '/parola-uitata');
        }
        $this->utilizatori->seteazaParola($uid, $p1);
        return $this->redirect($response, '/login?ok=parola');
    }

    private function trimiteLink(int $uid): bool
    {
        $u = $this->utilizatori->gaseste($uid);
        $raw = $this->tokens->issue($uid);
        if ($u === null || $raw === null) { return false; }
        $link = $this->settings['app']['url'] . $this->settings['app']['base_path'] . $this->adminPath() . '/parola/' . $raw;
        $html = $this->twig->fetch('mail/parola.twig', ['nume' => $u['nume'], 'link' => $link, 'zile' => PasswordTokenRepository::TTL_ZILE]);
        return $this->mailer->send((string) $u['email'], 'Setarea parolei — administrare FLAG Prahova', $html);
    }
}
```

Adaugă în `PasswordTokenRepository` metoda `esteValabil(string $raw): bool` (`SELECT 1 ... WHERE token_hash = :h AND folosit_la IS NULL AND expira_la > NOW()`). În `LoginController::form()` citește `?ok=parola` și pasează `'ok' => 'parola'` ca template-ul de login să afișeze „Parola a fost setată. Autentifică-te.”.

`src/Admin/MesajeController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Admin;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class MesajeController
{
    use Helpers;
    private Auth $auth; private array $settings; private Database $db;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth = $container['auth']; $this->settings = $container['settings']; $this->db = $container['db'];
    }

    public function index(Request $request, Response $response): Response
    {
        $p = max(1, (int) ($request->getQueryParams()['p'] ?? 1));
        $pdo = $this->db->pdo();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn();
        $rows = $pdo->query('SELECT m.*, s.slug AS sectiune FROM mesaje_contact m LEFT JOIN sectiuni s ON s.id = m.sectiune_id ORDER BY m.trimis_la DESC, m.id DESC LIMIT 100 OFFSET ' . (($p - 1) * 100))->fetchAll();
        return $this->render($response, 'admin/mesaje.twig', ['mesaje' => $rows, 'pagina' => $p, 'pagini' => (int) ceil($total / 100)]);
    }
}
```

Rute: în grup — `GET/POST /setari`, `GET /utilizatori`, `POST /utilizatori/adauga`, `POST /utilizatori/{id}/trimite-link`, `POST /utilizatori/{id}/sterge`, `GET /mesaje`; în afara grupului — `$app->map(['GET','POST'], $adminPath . '/parola-uitata', …parolaUitata)` și `$app->map(['GET','POST'], $adminPath . '/parola/{token:[a-f0-9]{64}}', …parola)`.

- [ ] **Step 5: Template-uri**

`templates/admin/setari.twig` — formular cu 4 câmpuri (`contact_email_destinatar` type email, `landing_titlu` text, `landing_text` textarea 3 rânduri, `footer_text` textarea 3 rânduri), etichete: „Emailul care primește mesajele din formularul de contact”, „Titlul paginii de intrare”, „Textul de sub titlu”, „Textul din subsol (disclaimer)”; buton Salvează.

`templates/admin/utilizatori.twig` — card „Adaugă utilizator” (email, nume, buton „Creează și trimite link”) + tabel: nume, email, stare (`are_parola` ? „Parolă setată” : „Fără parolă — așteaptă linkul”), ultimul login, acțiuni: „Trimite link de parolă”, „Șterge” (confirm; ascuns pentru contul curent).

`templates/admin/parola_uitata.twig` — extends layout, fără sidebar (`utilizator` null): dacă `trimis` → „Dacă adresa există în sistem, ai primit un email cu linkul de resetare (valabil 7 zile).”; altfel formular email + buton „Trimite link”.

`templates/admin/parola.twig` — formular `parola` + `parola2` (`minlength="10"`), eroare dacă e, buton „Setează parola”; `action="{{ base }}{{ admin_path }}/parola/{{ token }}"`.

`templates/admin/mesaje.twig` — tabel: dată, secțiune, nume, email (mailto), mesaj (`nl2br`), „email trimis” ✓/✗; paginare simplă `?p=`.

`templates/mail/parola.twig`:

```twig
<p>Bună{% if nume %}, {{ nume }}{% endif %},</p>
<p>Ai primit acest mesaj pentru că un cont de administrare pe situl FLAG Prahova are nevoie de o parolă.</p>
<p><a href="{{ link }}">Setează parola</a> (linkul e valabil {{ zile }} zile și poate fi folosit o singură dată).</p>
<p>Dacă nu ai cerut asta, ignoră mesajul.</p>
```

- [ ] **Step 6: Rulează toate testele**

Run: `for t in tests/*_test.php; do $PHP "$t" || echo "FAIL: $t"; done`
Expected: toate `OK`.
Browser: Utilizatori → adaugă o adresă; deschide `storage/logs/mail.log`, copiază linkul, setează parola într-o fereastră incognito, autentifică-te.

- [ ] **Step 7: Commit**

```bash
git add src templates tests
git commit -m "M1: Setari, Utilizatori (invitatie + parola uitata prin email), Mesaje"
```

### Task 10: Documentație de proiect, capturi de verificare, .cpanel.yml

**Files:**
- Create: `CLAUDE.md`, `README.md`, `.cpanel.yml`, `tests/capturi.mjs`, `storage/shots/.gitkeep`
- Modify: `.gitignore` (adaugă `/storage/shots/*`, `!/storage/shots/.gitkeep`)

- [ ] **Step 1: CLAUDE.md** (scurt, doar ce nu se vede din cod)

```markdown
# FLAG Prahova — notes for Claude

Slim 4 + Twig + PDO, PHP ≥ 8.1, fără build step; Bootstrap/Quill/SortableJS vendorate în `assets/vendor/`.
Spec: `docs/superpowers/specs/2026-09-18-flagprahova-site-nou-design.md`. Planuri: `docs/superpowers/plans/`.

## Comenzi
- PHP CLI: `C:/laragon/bin/php/php-8.3.31-nts-Win32-vs16-x64/php.exe` (`php` din PATH e tot 8.3, dar ținem calea explicită pentru extensii).
- MySQL: `C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe -u root --default-character-set=utf8mb4 flagprahova`. NU pasa diacritice pe linia de comandă.
- Migrare/seed: `php database/migrate.php && php database/seed.php` (idempotente). Cont: `php database/create_admin.php email nume parola`.
- Teste: `for t in tests/*_test.php; do php "$t" || echo "FAIL: $t"; done` — rulează în proces pe baza REALĂ `flagprahova`; fiecare test își șterge datele în `finally`.
- Capturi: `node tests/capturi.mjs` → `storage/shots/`.

## Convenții
- Intrarea de meniu e unitatea de conținut (`meniu.tip`: pagina/document/dosar/link/galerie). Slug unic pe secțiune.
- Prepared statements native: placeholdere distincte, LIMIT inline `(int)`.
- Toate POST-urile de admin cer `_csrf` (sau header `X-CSRF` la JSON) — `Admin\Helpers::csrfOk()`.
- `render()` din `Admin\Helpers` pune automat `utilizator` + `flash`; picker-ul de fișiere pasează `utilizator => null` ca să nu randeze sidebar-ul.
- Uploads: `/fisiere/AAAA/LL/`, MIME din conținut (`Fisiere\Upload`), PHP blocat prin `fisiere/.htaccess`. Ștergerea e refuzată dacă fișierul e referit (`Meniu\Repository::fisierFolosit`).
- HTML din editor trece prin `Support\Html::curata()` la salvare, nu la afișare.
- Mail: `SMTP_HOST` gol → `storage/logs/mail.log` (dev). Pe prod trebuie completat.
```

- [ ] **Step 2: README.md** — 15 rânduri: ce e proiectul, instalare locală (composer install, .env, migrate, seed, create_admin), URL admin, unde e specul.

- [ ] **Step 3: .cpanel.yml** — copiat din pestelocal cu `REPO=/home/flagprah/repositories/flagprahova`, `DEPLOYPATH=/home/flagprah/public_html/nou` (staging; la lansare se schimbă în `public_html`), `PHP=/opt/cpanel/ea-php83/root/usr/bin/php` (de confirmat pe server), listele de copiere: `src templates config assets vendor` + `index.php`; `mkdir -p $DEPLOYPATH/storage/cache/twig $DEPLOYPATH/storage/logs $DEPLOYPATH/fisiere`; NU se copiază `.htaccess`, `.env`, `fisiere/`.

- [ ] **Step 4: Capturi headless**

`tests/capturi.mjs` (folosește Chrome-ul instalat; fără dependențe npm):

```js
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
const chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const base = process.env.APP_URL || 'http://flagprahova.test';
mkdirSync('storage/shots', { recursive: true });
const pagini = [['admin-login', '/admin/login', 1366], ['admin-login-mobil', '/admin/login', 390]];
for (const [nume, cale, latime] of pagini) {
  execFileSync(chrome, ['--headless=new', '--disable-gpu', `--window-size=${latime},900`, `--screenshot=storage/shots/${nume}.png`, base + cale], { stdio: 'ignore' });
  console.log('ok', nume);
}
```

Paginile autentificate se capturează manual (sesiunea nu se poate injecta în Chrome headless fără profil): deschide în browser Meniu, Intrare (pagină cu editor), Fișiere, Utilizatori și salvează capturile în `storage/shots/` cu numele `admin-meniu.png`, `admin-intrare.png`, `admin-fisiere.png`, `admin-utilizatori.png`; verifică vizual că nimic nu derulează lateral la 390px.

Run: `node tests/capturi.mjs` — Expected: `ok admin-login`, `ok admin-login-mobil`, fișierele există.

- [ ] **Step 5: Commit**

```bash
git add CLAUDE.md README.md .cpanel.yml tests/capturi.mjs storage/shots/.gitkeep .gitignore
git commit -m "M1: documentatie proiect, .cpanel.yml staging, capturi de verificare"
```

---

## Auto-verificare (făcută la scrierea planului)

- **Acoperire spec (secțiunile 2, 3, 5, 8 pentru admin):** stack/structură → T1; model de date complet → T2; login/throttle/CSRF → T3; arbore + tipuri + slug → T4/T7; fișiere cu validare MIME, listă an/lună, căutare, upload multiplu, copiază link, redenumire, ștergere protejată, picker → T5/T6; Quill cu imagine din picker, curățare HTML, șablon contact, galerie → T8; setări, utilizatori fără roluri cu link pe email, parolă uitată, mesaje → T9; `.cpanel.yml`, docs → T10. Secțiunile 4, 6, 7, 9 (public), 7 (migrare) și 10 (lansare) sunt în Plan 2 și Plan 3.
- **Consistență de nume:** `Meniu\Repository::{sectiuni, sectiuneDupaSlug, arbore, gaseste, gasesteDupaSlug, copii, slugUnic, creeaza, actualizeaza, sterge, numaraDescendenti, reordoneaza, fisierFolosit}` folosite identic în T4, T6, T7. `Fisiere\Upload::{salveaza, numeSigur, esteImagine, EXTENSII}` și `Fisiere\Repository::{inregistreaza, gaseste, gasesteDupaCale, lista, aniLuni, redenumeste, sterge}` identice în T5, T6, T7, T8. Chei container: `settings, db, auth, login_throttle, meniu, fisiere, upload, galerie, setari, mailer, utilizatori, parola_tokens, parola_throttle`. Sesiune: `admin_user`, `csrf`, `flash`. Header JSON: `X-CSRF`.
- **Placeholder-e:** niciun TBD; singurele „copiat din pestelocal” sunt fișiere existente cu cale absolută și adaptările enumerate explicit.
