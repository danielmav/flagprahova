# FLAG Prahova — Plan 4: staging și lansare (server fără SSH)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Task-urile 1–4 sunt cod/documentație (subagenți). Task-urile 5–6 sunt PROCEDURI MANUALE pe cPanel, executate de Daniel împreună cu Claude (Claude dă pașii și verifică prin `curl`; Daniel face click-urile). Nu se dispecerizează subagenți pentru 5–6.

**Goal:** Situl nou pus pe staging (`public_html/nou`, `https://flagprahova.ro/nou/`) și apoi lansat în locul WordPress-ului, pe un cPanel FĂRĂ SSH, cu baza și fișierele urcate manual, cu un manual scurt pentru client.

**Architecture:** Codul rămâne cel din `main`; se adaugă doar ce lipsește pentru un server fără shell: (1) un comutator `APP_INDEXABLE` ca staging-ul să fie invizibil pentru motoare, (2) o gardă de megapixeli la miniaturi, (3) un script local care produce dump-ul SQL filtrat (fără contul local, fără tokenuri/încercări/mesaje), (4) `.cpanel.yml` tolerant (composer opțional, `vendor/` poate veni din arhivă), (5) runbook-uri pas cu pas pentru staging și lansare, (6) manualul clientului. Deploy-ul codului e prin cPanel Git Version Control; baza prin phpMyAdmin; `fisiere/` prin FTP.

**Tech Stack:** PHP 8.1+ (server ea-php83), MySQL (phpMyAdmin), cPanel Git™ Version Control, FTP (FileZilla), `mysqldump.exe` din Laragon, harness `tests/_bootstrap.php`.

**Spec:** `docs/superpowers/specs/2026-09-18-flagprahova-site-nou-design.md` §10 (Deploy și lansare) și §11 (M5), corectată de decizia „hosting fără SSH" din `CLAUDE.md` (secțiunea Server / deploy). Restanțele din revizia finală M3 sunt în `CLAUDE.md` → Convenții → „Restanțe pentru Plan 4".

## Global Constraints

- **Fără SSH** pe server: nimic din plan nu presupune shell. Ce rulează pe server rulează doar prin `.cpanel.yml` (executat de cPanel la „Deploy HEAD Commit", ca utilizatorul contului) sau prin browser (PHP web).
- `APP_URL` INCLUDE `BASE_PATH` (staging: `APP_URL=https://flagprahova.ro/nou`, `BASE_PATH=/nou`; producție: `APP_URL=https://flagprahova.ro`, `BASE_PATH=` gol).
- Staging-ul NU trebuie indexat: `APP_INDEXABLE=false` → `<meta name="robots" content="noindex,nofollow">` pe toate paginile publice și `robots.txt` cu `Disallow: /`. Producția: `APP_INDEXABLE=true` (implicit când lipsește).
- Datele de conectare (cPanel, DB, SMTP, admin) stau DOAR în `materiale/dateconectare.txt` (gitignored) și în `.env` de pe server — nu apar în plan, în commituri, în rapoarte, în transcript.
- Dump-ul SQL pentru server NU conține: contul local `admin@flagprahova.ro`, rândurile din `parola_tokens`, `login_incercari`, `mesaje_contact` (doar structura). Conține TOATE rândurile din `sectiuni`, `meniu`, `fisiere`, `galerie_imagini`, `setari` și contul clientului creat local înainte de export.
- `fisiere/` de pe server trebuie să fie identic cu cel local (991 fișiere, ~3 GB) — verificat prin numărare, nu presupus. `fisiere/mini/` NU se urcă (se regenerează).
- Nu se modifică `migrate_wp.php` / migrarea; conținutul migrat e cel din baza locală de azi (255 intrări).
- Prepared statements, teste `tests/*_test.php` pe baza reală, self-cleaning, suita de două ori; `verifica_migrare.php` exit 0 la final.
- Comenzi: `PHP="C:/laragon/bin/php/php-8.3.31-nts-Win32-vs16-x64/php.exe"`; `MYSQLDUMP="C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe"`; fără diacritice pe linia de comandă mysql.
- Commit după fiecare task, prefix `M4:`; branch `plan-4-lansare`; fără push până nu decide Daniel.

## Structura de fișiere

| Fișier | Responsabilitate |
|---|---|
| `config/settings.php`, `.env.example` | `app.indexable` din `APP_INDEXABLE` |
| `templates/layout.twig`, `src/Public/SeoController.php` | noindex + `Disallow: /` când nu e indexabil |
| `src/Fisiere/Miniatura.php` | gardă de megapixeli înainte de decodare |
| `scripts/export_baza.php` | dump SQL filtrat → `storage/migrare/flagprahova-server.sql` |
| `.cpanel.yml` | deploy tolerant la lipsa composer; `DEPLOYPATH` comutabil staging/prod |
| `README.md` | secțiunea „Deploy fără SSH" (runbook scurt) |
| `docs/runbook-lansare.md` | procedura completă staging → lansare → după lansare (fără secrete) |
| `docs/manual-admin.md` | manualul clientului (admin) |
| `tests/public_indexable_test.php`, `tests/miniatura_garda_test.php`, `tests/export_baza_test.php` | teste |

---

### Task 1: `APP_INDEXABLE` (staging invizibil) + gardă de megapixeli la miniaturi

**Files:**
- Modify: `config/settings.php` (`app.indexable`), `.env.example`, `templates/layout.twig` (blocul `meta_robots`), `src/Public/SeoController.php::robots()`, `src/Fisiere/Miniatura.php::asigura()`, `CLAUDE.md` (o linie la Convenții)
- Create: `tests/public_indexable_test.php`, `tests/miniatura_garda_test.php`

**Interfaces:**
- Produces `settings['app']['indexable']` (bool; `filter_var($_ENV['APP_INDEXABLE'] ?? true, FILTER_VALIDATE_BOOL)`), expus în Twig prin globalul existent `app` (`app.indexable`).
- Produces `Miniatura::MAX_PIXELI = 40_000_000` (40 MP); `asigura()` întoarce `null` (→ 404) pentru surse peste prag, fără să le decodeze.

- [ ] **Step 1: Testele**

`tests/public_indexable_test.php` — pornește app-ul cu `APP_INDEXABLE=false` setat în `$_ENV` ÎNAINTE de prima `cerere()` (harness-ul citește `.env` prin `safeLoad`, care NU suprascrie valorile deja existente în `$_ENV`):

```php
<?php
declare(strict_types=1);
$_ENV['APP_INDEXABLE'] = 'false';
require __DIR__ . '/_bootstrap.php';

$r = cerere('GET', '/');
ok('APP_INDEXABLE=false: landing are noindex', str_contains(corp($r), '<meta name="robots" content="noindex,nofollow">'));
$r = cerere('GET', '/2021-2027/');
ok('  acasă de secțiune are noindex', str_contains(corp($r), 'content="noindex,nofollow"'));
$r = cerere('GET', '/robots.txt'); $c = corp($r);
ok('  robots.txt blochează tot', str_contains($c, "User-agent: *\nDisallow: /\n") && !str_contains($c, 'Sitemap:'));
ok('  sitemap.xml rămâne accesibil (doar nu e anunțat)', cerere('GET', '/sitemap.xml')->getStatusCode() === 200);
final_test();
```

Testul existent `tests/public_seo_test.php` (fără variabila setată → indexabil) acoperă cazul implicit; adaugă acolo o aserțiune: `ok('implicit indexabil', str_contains(corp(cerere('GET', '/')), 'content="index,follow'));`.

`tests/miniatura_garda_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Fisiere\Miniatura;

$dir = settings()['upload']['dir'];
$m = bin2hex(random_bytes(3));
$rel = "2026/09/garda-$m.png";
@mkdir("$dir/2026/09", 0775, true);
// PNG „bombă": 8000x6000 = 48 MP, dar fișierul e mic (imagine uniformă, compresie maximă).
$im = imagecreatetruecolor(8000, 6000); imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255)); imagepng($im, "$dir/$rel", 9); imagedestroy($im);
try {
    ok('MAX_PIXELI = 40 MP', Miniatura::MAX_PIXELI === 40_000_000);
    $t0 = microtime(true);
    $rez = (new Miniatura($dir))->asigura($rel, 480);
    ok('sursa de 48 MP => null, fără decodare', $rez === null);
    ok('  a răspuns repede (< 1 s, deci n-a decodat)', microtime(true) - $t0 < 1.0);
    ok('  nu a lăsat miniatură pe disc', !is_file("$dir/mini/480/2026/09/garda-$m.webp"));
    $r = cerere('GET', "/fisiere/mini/480/2026/09/garda-$m.webp");
    ok('  HTTP => 404', $r->getStatusCode() === 404);
} finally {
    @unlink("$dir/$rel");
    @unlink("$dir/mini/480/2026/09/garda-$m.webp");
}
final_test();
```

- [ ] **Step 2: Rulează, pică** — `$PHP tests/public_indexable_test.php` (noindex lipsește), `$PHP tests/miniatura_garda_test.php` (constanta lipsește).

- [ ] **Step 3: Implementarea**

`config/settings.php`, în `'app'`: `'indexable' => filter_var($_ENV['APP_INDEXABLE'] ?? true, FILTER_VALIDATE_BOOL),`. `.env.example`: după `BASE_PATH=` adaugă `# false pe staging: noindex pe toate paginile + robots.txt Disallow: /` și `APP_INDEXABLE=true`.

`templates/layout.twig`, linia cu robots:

```twig
<meta name="robots" content="{% if not app.indexable %}noindex,nofollow{% else %}{% block meta_robots %}index,follow,max-image-preview:large{% endblock %}{% endif %}">
```

`SeoController::robots()`, la început:

```php
        if (!(bool) $this->container['settings']['app']['indexable']) {
            $response->getBody()->write("User-agent: *\nDisallow: /\n");
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }
```

`Miniatura`: `public const MAX_PIXELI = 40_000_000;` și în `asigura()`, după `if (!is_file($sursa)) { return null; }`:

```php
        $dim = @getimagesize($sursa);
        if ($dim === false || ($dim[0] * $dim[1]) > self::MAX_PIXELI) { return null; }
```

(`getimagesize` citește doar antetul — nu decodează pixelii.)

`CLAUDE.md` → Convenții: „`APP_INDEXABLE=false` (staging) → noindex pe tot situl public + `robots.txt` `Disallow: /`; `Fisiere\Miniatura::MAX_PIXELI` (40 MP) refuză sursele uriașe cu 404 înainte de decodare."

- [ ] **Step 4: Rulează** cele două teste + `public_seo_test.php` + `public_miniatura_test.php` → OK. Apoi suita completă o dată.

- [ ] **Step 5: Commit** — `M4: APP_INDEXABLE pentru staging + garda de megapixeli la miniaturi`.

---

### Task 2: `scripts/export_baza.php` — dump-ul SQL pentru server

**Files:**
- Create: `scripts/export_baza.php`, `tests/export_baza_test.php`
- Modify: `.gitignore` (nimic nou: `storage/migrare/` e deja ignorat — verifică), `README.md` (o linie în „Migrarea conținutului vechi" → „Server fără acces SSH": comanda de export)

**Interfaces:**
- CLI: `$PHP scripts/export_baza.php [--out=storage/migrare/flagprahova-server.sql] [--mysqldump=C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe]`. Citește `DB_*` din `.env`. Exit 0 și tipărește calea + numărul de rânduri per tabel; exit 1 cu mesaj dacă `mysqldump` lipsește sau dacă în `utilizatori` nu există NICIUN cont în afară de `admin@flagprahova.ro` (adică nu s-a creat încă contul clientului — vezi Task 5).
- Conținutul dump-ului: `SET NAMES utf8mb4;` la început; structura tuturor celor 9 tabele cu `DROP TABLE IF EXISTS` + `CREATE TABLE`; datele complete pentru `sectiuni`, `meniu`, `fisiere`, `galerie_imagini`, `setari`; `utilizatori` fără rândul cu email `admin@flagprahova.ro`; `parola_tokens`, `login_incercari`, `mesaje_contact` doar structură. Ordinea: tabelele-părinte înaintea copiilor (`sectiuni` → `fisiere` → `meniu` → `galerie_imagini`; `utilizatori` → `parola_tokens`), cu `SET FOREIGN_KEY_CHECKS=0;` la început și `=1` la final, ca importul prin phpMyAdmin să nu depindă de ordine.

- [ ] **Step 1: Testul** `tests/export_baza_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$root = dirname(__DIR__);
$PHP  = 'C:/laragon/bin/php/php-8.3.31-nts-Win32-vs16-x64/php.exe';
$pdo  = pdo();
$m    = bin2hex(random_bytes(3));
$out  = "$root/storage/migrare/test-export-$m.sql";
$uid  = null;
try {
    // fără cont de client => refuz
    $are = (int) $pdo->query("SELECT COUNT(*) FROM utilizatori WHERE email <> 'admin@flagprahova.ro'")->fetchColumn();
    if ($are === 0) {
        exec("\"$PHP\" \"$root/scripts/export_baza.php\" --out=\"$out\" 2>&1", $o1, $c1);
        ok('fără cont de client => exit 1 cu mesaj', $c1 === 1 && str_contains(implode("\n", $o1), 'contul clientului'));
    }
    // cont temporar de client + un token + o încercare + un mesaj, care NU trebuie să ajungă în dump
    $pdo->prepare('INSERT INTO utilizatori (email, nume, parola_hash) VALUES (:e, "Client Test", "x")')->execute(['e' => "client-$m@example.com"]);
    $uid = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO parola_tokens (utilizator_id, token_hash, expira_la) VALUES (:u, :h, NOW())')->execute(['u' => $uid, 'h' => str_repeat('a', 60) . $m . '0']);
    $pdo->prepare('INSERT INTO login_incercari (ip_hash, scope) VALUES (:h, "admin")')->execute(['h' => str_repeat('b', 58) . $m]);
    $pdo->prepare('INSERT INTO mesaje_contact (nume, email, mesaj) VALUES ("T", :e, "secret")')->execute(['e' => "mesaj-$m@example.com"]);

    exec("\"$PHP\" \"$root/scripts/export_baza.php\" --out=\"$out\" 2>&1", $o2, $c2);
    ok('export => exit 0', $c2 === 0);
    ok('  fișierul există și e mare', is_file($out) && filesize($out) > 100000);
    $sql = (string) file_get_contents($out);
    ok('  utf8mb4 + FK checks', str_contains($sql, 'SET NAMES utf8mb4') && str_contains($sql, 'FOREIGN_KEY_CHECKS=0') && str_contains($sql, 'FOREIGN_KEY_CHECKS=1'));
    foreach (['sectiuni', 'fisiere', 'meniu', 'galerie_imagini', 'setari', 'utilizatori', 'parola_tokens', 'login_incercari', 'mesaje_contact'] as $t) {
        ok("  CREATE TABLE `$t`", str_contains($sql, "CREATE TABLE `$t`"));
    }
    ok('  contul local NU e în dump', !str_contains($sql, 'admin@flagprahova.ro'));
    ok('  contul clientului E în dump', str_contains($sql, "client-$m@example.com"));
    ok('  tokenurile/încercările/mesajele NU sunt', !str_contains($sql, $m . '0') && !str_contains($sql, str_repeat('b', 58) . $m) && !str_contains($sql, "mesaj-$m@example.com"));
    ok('  meniul e complet', substr_count($sql, 'INSERT INTO `meniu`') >= 1 && str_contains($sql, 'cooperare'));
    ok('  ordinea: sectiuni înainte de meniu', strpos($sql, 'CREATE TABLE `sectiuni`') < strpos($sql, 'CREATE TABLE `meniu`'));
} finally {
    if ($uid) {
        $pdo->exec("DELETE FROM parola_tokens WHERE utilizator_id = $uid");
        $pdo->exec("DELETE FROM utilizatori WHERE id = $uid");
    }
    $pdo->prepare('DELETE FROM login_incercari WHERE ip_hash = :h')->execute(['h' => str_repeat('b', 58) . $m]);
    $pdo->prepare('DELETE FROM mesaje_contact WHERE email = :e')->execute(['e' => "mesaj-$m@example.com"]);
    @unlink($out);
}
final_test();
```

- [ ] **Step 2: Rulează, pică** (scriptul lipsește).

- [ ] **Step 3: Scriptul** `scripts/export_baza.php`:

```php
<?php
// Dump SQL pentru server (import prin phpMyAdmin, fără SSH): tot conținutul,
// fără contul local de dezvoltare, fără tokenuri/încercări/mesaje.
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
if (is_file($root . '/.env')) { Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
$s  = (require $root . '/config/settings.php')['db'];
$opt = getopt('', ['out::', 'mysqldump::']);
$out = $opt['out'] ?? $root . '/storage/migrare/flagprahova-server.sql';
$dump = $opt['mysqldump'] ?? 'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe';
if (!is_file($dump)) { fwrite(STDERR, "mysqldump lipsește: $dump\n"); exit(1); }

$pdo = (new App\Database($s))->pdo();
$clienti = (int) $pdo->query("SELECT COUNT(*) FROM utilizatori WHERE email <> 'admin@flagprahova.ro'")->fetchColumn();
if ($clienti === 0) { fwrite(STDERR, "Nu exista contul clientului in `utilizatori` (doar admin@flagprahova.ro). Creeaza-l cu database/create_admin.php inainte de export.\n"); exit(1); }

$baza = ['--host=' . $s['host'], '--port=' . $s['port'], '--user=' . $s['user'], '--default-character-set=utf8mb4', '--skip-comments', '--single-transaction', '--add-drop-table'];
if ($s['pass'] !== '') { $baza[] = '--password=' . $s['pass']; }
$ruleaza = function (array $args) use ($dump, $baza, $s): string {
    $cmd = escapeshellarg($dump) . ' ' . implode(' ', array_map('escapeshellarg', [...$baza, ...$args, $s['name']]));
    exec($cmd . ' 2>&1', $o, $c);
    if ($c !== 0) { fwrite(STDERR, "mysqldump a esuat: " . implode("\n", $o) . "\n"); exit(1); }
    return implode("\n", $o) . "\n";
};

$sql  = "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
$sql .= $ruleaza(['sectiuni', 'fisiere', 'meniu', 'galerie_imagini', 'setari']);
$sql .= $ruleaza(['--where=email <> \'admin@flagprahova.ro\'', 'utilizatori']);
$sql .= $ruleaza(['--no-data', 'parola_tokens', 'login_incercari', 'mesaje_contact']);
$sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";
@mkdir(dirname($out), 0775, true);
file_put_contents($out, $sql);

foreach (['sectiuni', 'meniu', 'fisiere', 'galerie_imagini', 'setari', 'utilizatori'] as $t) {
    $n = $t === 'utilizatori' ? $clienti : (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
    echo str_pad($t, 16) . $n . "\n";
}
echo "scris $out (" . round(filesize($out) / 1024) . " KB)\n";
```

Notă Windows: `escapeshellarg` pune ghilimele duble și dublează `"`; argumentul `--where=email <> 'admin@…'` are doar apostrofuri, deci trece corect la `mysqldump.exe`. Dacă ieșirea vine cu CRLF, e ok pentru phpMyAdmin.

- [ ] **Step 4: Rulează** testul → OK (pe baza locală există doar contul local, deci se testează și refuzul). Apoi rulează scriptul „pe bune" DUPĂ ce creezi contul clientului (Task 5, pasul 1) — nu acum.

- [ ] **Step 5: README** — în secțiunea „Server fără acces SSH", înlocuiește pasul de export cu: „`php scripts/export_baza.php` → `storage/migrare/flagprahova-server.sql` (fără contul local, fără tokenuri/mesaje); importă-l în phpMyAdmin în baza goală a sitului."

- [ ] **Step 6: Commit** — `M4: scripts/export_baza.php — dump filtrat pentru phpMyAdmin`.

---

### Task 3: `.cpanel.yml` tolerant + runbook-ul de lansare (documentație)

**Files:**
- Modify: `.cpanel.yml`, `README.md` (secțiunea deploy: fără SSH), `.htaccess` (comentariu: pe server MultiPHP scrie blocul handler; regula HTTPS exceptează `flagprahova.test`)
- Create: `docs/runbook-lansare.md`

**Interfaces:** niciun cod nou. `.cpanel.yml` rămâne valid YAML (verifică cu `node -e "require('js-yaml')"` dacă e disponibil, altfel cu `python -c "import yaml"`; dacă nu există niciunul, verifică vizual indentarea — două spații, liste cu `- `).

- [ ] **Step 1: `.cpanel.yml`** — păstrează structura existentă; schimbă doar:

```yaml
    # composer.phar se urcă O DATĂ prin File Manager în /home/flagprah/ (descărcat de pe getcomposer.org).
    # Dacă lipsește (sau pică), deploy-ul NU se oprește: se folosește vendor/ deja existent în repo
    # (urcat ca arhivă prin File Manager în ~/repositories/flagprahova/vendor — vezi docs/runbook-lansare.md).
    - if [ -f /home/flagprah/composer.phar ]; then cd $REPO && $PHP /home/flagprah/composer.phar install --no-dev --optimize-autoloader --no-interaction || echo "composer a esuat, folosesc vendor/ existent"; fi
    - test -f $REPO/vendor/autoload.php || { echo "LIPSESTE vendor/autoload.php in repo"; exit 1; }
```

și comentariul de sus: `DEPLOYPATH=/home/flagprah/public_html/nou` pe staging; la lansare se schimbă în `/home/flagprah/public_html` (o linie, commit `M4: lansare — DEPLOYPATH public_html`).

- [ ] **Step 2: `docs/runbook-lansare.md`** — scris pentru Daniel (nu pentru client), în română, fără secrete (referă `materiale/dateconectare.txt`). Secțiuni, cu casete de bifat:

  **A. Pregătire locală (o dată)**
  1. `git checkout main && git pull`; suita verde; `verifica_migrare.php` exit 0.
  2. Contul clientului local: `$PHP database/create_admin.php <email-client> "<Nume>" "<parolă generată>"` — parola generată cu `openssl rand -base64 18`, notată în `materiale/dateconectare.txt`, comunicată clientului separat (nu prin email în clar — telefon sau mesaj care se șterge).
  3. `$PHP scripts/export_baza.php` → `storage/migrare/flagprahova-server.sql`.
  4. `vendor/` de producție: într-un director temporar, `composer install --no-dev --optimize-autoloader` pe o copie a `composer.json`/`composer.lock`, apoi arhivează `vendor/` ca `vendor.zip` (~4 MB). Se urcă doar dacă pasul composer din `.cpanel.yml` pică.
  5. `.env` de staging pregătit local ca `storage/migrare/env-staging.txt` (gitignored): `APP_ENV=prod`, `APP_DEBUG=false`, `APP_URL=https://flagprahova.ro/nou`, `BASE_PATH=/nou`, `APP_INDEXABLE=false`, `ADMIN_PATH=admin`, `DB_*` = baza `flagprah_nou` + utilizatorul creat în cPanel, `DB_WP_NAME=` gol, `MAIL_*`/`SMTP_*` din cPanel → Email Accounts (contul `noreply@flagprahova.ro`, host `mail.flagprahova.ro`, port 465 `SMTP_SECURE=ssl` sau 587 `tls`), `IP_SALT=` `openssl rand -hex 32`, `TWIG_CACHE=true`.

  **B. Staging (`public_html/nou`)** — cPanel:
  1. MySQL® Databases: baza `flagprah_nou`, utilizator `flagprah_nou`, toate privilegiile.
  2. phpMyAdmin → `flagprah_nou` → Import → `flagprahova-server.sql` (dacă e peste limita de upload a phpMyAdmin — de regulă 50 MB — comprimă-l `.gz`; dump-ul e ~1–2 MB, deci nu e cazul). Verifică: `SELECT COUNT(*) FROM meniu` = 255, `fisiere` = 991, `utilizatori` = 1 (clientul).
  3. Git™ Version Control → Create: URL `https://github.com/danielmav/flagprahova.git`, path `/home/flagprah/repositories/flagprahova`, branch `main`. (Repo-ul e public? Dacă e privat: token GitHub cu scop `repo` în URL, `https://<token>@github.com/...` — tokenul se revocă după lansare.)
  4. File Manager: urcă `composer.phar` în `/home/flagprah/` (dacă nu e permis `.phar`, urcă `vendor.zip` în `repositories/flagprahova/` și extrage-l acolo).
  5. Git Version Control → Manage → Pull or Deploy → „Deploy HEAD Commit". Citește jurnalul: pasul composer/vendor, copierea în `public_html/nou`.
  6. File Manager → `public_html/nou`: creează `.env` (conținutul din `env-staging.txt`); copiază `.htaccess` din repo (`repositories/flagprahova/.htaccess`) în `public_html/nou/.htaccess`; MultiPHP Manager → setează PHP 8.3 pe domeniu (scrie blocul handler în `.htaccess`-ul din `public_html`, nu în `nou/` — verifică dacă `nou/` moștenește; dacă nu, adaugă în `nou/.htaccess` blocul `<IfModule mime_module> AddHandler application/x-httpd-ea-php83 …` copiat din `public_html/.htaccess`).
  7. Drepturi: `storage/`, `storage/cache/twig`, `storage/logs`, `fisiere/` scriibile (755 e suficient pe cPanel cu suPHP/LSAPI; NU 777).
  8. FTP (FileZilla, SFTP nu e disponibil → FTP/FTPS pe portul 21, cont din `dateconectare.txt`): urcă `fisiere/AAAA/` (toți anii, fără `mini/`) în `public_html/nou/fisiere/`. ~3 GB — pornește-l primul, durează ore. Verificare la final: în File Manager, „Select All" în `fisiere/2017` etc. și compară numărul cu local (`find fisiere -type f -not -path 'fisiere/mini/*' | wc -l` = 991 + `.htaccess`).
  9. Verificări (Claude, prin `curl`): `https://flagprahova.ro/nou/` 200 + `noindex`; `/nou/2014-2020/cooperare` 200 + miniaturi 200 (`/nou/fisiere/mini/480/2022/10/instruire-01-busteni.webp`); `/nou/robots.txt` = `Disallow: /`; `/nou/templates/` 403; `/nou/fisiere/x.php` 403; `/nou/admin/login` 200; `/nou/health` `{"ok":true}`; login cu contul clientului; un upload; un mesaj de contact ajuns pe `contact_email_destinatar`; „parolă uitată" primit.
  10. Daniel + clientul validează conținutul pe staging (textul Acasă 2021-2027, linkul stricat din „Măsura 1", logo-urile, meniurile).

  **C. Lansare (`public_html`)**
  1. Backup WordPress: cPanel → Backup → „Download a Home Directory Backup" + „Download a MySQL Database Backup" pentru baza WP (ambele în `materiale/arhiva/`, gitignored).
  2. File Manager: redenumește `public_html` → `wp-vechi` (site-ul vechi cade ~10 minute), creează `public_html` gol, mută `public_html/wp-vechi/nou/*` → `public_html/` (inclusiv `.env`, `.htaccess`, `fisiere/`, `storage/`; mutarea în același filesystem e instantanee).
  3. Editează `.env`: `APP_URL=https://flagprahova.ro`, `BASE_PATH=` gol, `APP_INDEXABLE=true`.
  4. Local: în `.cpanel.yml` `DEPLOYPATH=/home/flagprah/public_html`; commit `M4: lansare — DEPLOYPATH public_html`, push. cPanel → Git → Pull → Deploy HEAD Commit.
  5. MultiPHP Manager → confirmă PHP 8.3 pe `flagprahova.ro`; deschide `.htaccess` din `public_html` și verifică blocul handler + regulile din repo.
  6. SSL: cPanel → SSL/TLS Status → AutoSSL activ pe domeniu; regula HTTPS din `.htaccess` forțează `https://`.
  7. Verificări (Claude): aceleași ca la B.9 pe `https://flagprahova.ro/` (fără `/nou`), plus `robots.txt` cu `Sitemap:` și `/sitemap.xml` cu `<loc>https://flagprahova.ro/...`, un URL WP vechi (`/wp-content/uploads/2017/06/Organigrama.pdf`) → 404, `www.flagprahova.ro` → redirect la fără `www` (dacă nu, regulă în `.htaccess`).
  8. Google Search Console: proprietatea `flagprahova.ro` (verificare prin DNS TXT sau fișier HTML urcat în `public_html`), trimite `https://flagprahova.ro/sitemap.xml`.
  9. Baza WP veche și `wp-vechi/` rămân 30 de zile; ștergere programată: **2026-10-19**.

  **D. Predare**
  1. `docs/manual-admin.md` (Task 4) exportat ca PDF (Chrome „Print to PDF" pe `manual-admin.html` randat din markdown, sau trimis ca `.md` + link) și trimis clientului cu contul și instrucțiunile de resetare a parolei.
  2. `CLAUDE.md`: stadiu „Lansat 2026-09-19", `docs/runbook-lansare.md` referit la Server / deploy.

- [ ] **Step 3: README** — în secțiunea deploy: două paragrafe: „Fără SSH: procedura completă e în `docs/runbook-lansare.md`; pe scurt: export cu `scripts/export_baza.php` → phpMyAdmin; `fisiere/` prin FTP; cod prin Git Version Control; `composer.phar` sau `vendor.zip` urcat prin File Manager." Elimină comenzile `cd ~/repositories/... php database/migrate.php` (nu se pot rula) — înlocuiește cu „schema vine din dump".

- [ ] **Step 4: Verifică** că suita încă trece (nimic din cod nu s-a schimbat, dar `.cpanel.yml` e citit de nimeni local — doar sintaxa). Commit — `M4: .cpanel.yml tolerant la composer, runbook de lansare fara SSH`.

---

### Task 4: Manualul clientului — `docs/manual-admin.md`

**Files:**
- Create: `docs/manual-admin.md`
- Modify: `README.md` (link la manual)

**Interfaces:** document, nu cod. Scris pentru client (angajații FLAG Prahova, nu tehnici), în română, cu diacritice, ton direct, capturi NU (linkuri către ecrane: `https://flagprahova.ro/admin/...`). Maxim ~3 pagini A4.

- [ ] **Step 1: Cuprinsul** (fix; conținutul se scrie din codul admin real — citește `templates/admin/*.twig` și `src/Admin/*Controller.php` pentru etichete și comportamente exacte, nu inventa):
  1. **Intrarea în administrare** — `https://flagprahova.ro/admin`, email + parolă; „Parolă uitată" trimite un link valabil 30 de minute; după 5 încercări greșite se așteaptă 15 minute.
  2. **Cum e organizat situl** — două perioade (2021-2027, 2014-2020), fiecare cu meniul ei; „meniul este conținutul": fiecare intrare din meniu e o pagină, un document, un dosar (grupă de documente), un link extern sau o galerie foto. Intrările invizibile nu apar pe sit, dar rămân în admin.
  3. **Adaug un document nou (cel mai des)** — Meniu → alege perioada → „Adaugă intrare" (sau „Adaugă sub" la un dosar, ex. Noutăți) → titlu → tip „Document" → „Încarcă acum" sau „Alege din fișiere" → Salvează. Ce fișiere se acceptă (lista din `Fisiere\Upload`) și limita de 50 MB. Unde apare (dropdown-ul din meniu + pe pagina dosarului + în „Noutăți" pe acasă dacă e în Noutăți).
  4. **Ordinea și mutarea** — tragere cu mouse-ul în arbore; se salvează automat.
  5. **Pagini cu text** — tip „Pagină", editorul (titluri, liste, linkuri, imagini din managerul de fișiere), șablonul „Contact" (formular).
  6. **Galerii foto** — tip „Galerie", încărcare multiplă, ordonare, legende; unde se afișează (pagina galeriei; sub conținut, dacă e sub o pagină).
  7. **Fișiere** — lista pe an/lună, căutare, „Copiază link", redenumire; de ce nu se poate șterge un fișier folosit.
  8. **Setări** — email-ul care primește mesajele, textele de pe prima pagină, subsolul, adresa/telefonul/emailul public.
  9. **Utilizatori** — adaugă coleg (primește link de setare a parolei), trimite link de resetare, șterge.
  10. **Mesaje** — mesajele din formular, cu semn dacă emailul a plecat.
  11. **Ce NU face situl** — nu are statistici, nu are versiuni/istoric, nu are roluri; ce s-a șters e șters (dosarele șterse iau cu ele și copiii — confirmare cu numărul lor).
  12. **Probleme frecvente** — „nu văd modificarea": verifică bifa Vizibil și perioada; „fișierul nu se încarcă": tip/mărime; „nu primesc emailurile": scrie la Daniel (SMTP).

- [ ] **Step 2: Scrie manualul** urmând cuprinsul; fiecare secțiune ≤ 12 rânduri; pași numerotați; termenii exact ca în admin (verifică etichetele butoanelor în `templates/admin/`).

- [ ] **Step 3: Verificare** — un cititor care nu știe proiectul poate adăuga un document urmând §3 fără altă informație? Dă manualul unei citiri „cu ochii clientului" (self-review), corectează. Commit — `M4: manualul clientului (docs/manual-admin.md)`.

---

### Task 5: Staging pe server (MANUAL, Daniel + Claude) — după runbook §A–B

- [ ] **Step 1:** A.1–A.5 local (Claude rulează comenzile; Daniel confirmă că parola clientului e notată în `materiale/dateconectare.txt`).
- [ ] **Step 2:** B.1–B.8 în cPanel (Daniel); Claude verifică după fiecare pas prin `curl -sI https://flagprahova.ro/nou/…` ce se poate verifica (după B.5: 500/403/200; după B.6: 200).
- [ ] **Step 3:** B.9 verificările; orice eroare → `storage/logs/` pe server (File Manager → View) — Claude diagnostichează.
- [ ] **Step 4:** B.10 validarea conținutului de către client. Dacă apar corecturi de cod: branch, fix, test, merge, push, „Deploy HEAD Commit".
- [ ] **Step 5:** Ledger: în `CLAUDE.md` → Stadiu: „Staging pe `https://flagprahova.ro/nou/` din 2026-09-19".

---

### Task 6: Lansarea (MANUAL, Daniel + Claude) — după runbook §C–D

- [ ] **Step 1:** C.1 backup WP (Daniel) — fișierele în `materiale/arhiva/`.
- [ ] **Step 2:** C.2–C.6 (Daniel), C.4 commit+push (Claude, cu acordul explicit al lui Daniel pentru push).
- [ ] **Step 3:** C.7 verificările (Claude) + C.8 Search Console (Daniel).
- [ ] **Step 4:** D.1 manualul la client; D.2 `CLAUDE.md` stadiu „Lansat"; memorie proiect actualizată; ștergerea WP programată **2026-10-19**.

---

## Self-review

- Spec §10: repo + `.cpanel.yml` decuplat (T3), staging în subfolder cu bază proprie (T5/B), lansare cu backup + mutare + import + `.htaccess` + verificare + ștergere după 30 zile (T6/C), manual client (T4 = M5). Linia „SSH" din spec e depășită de decizia „fără SSH" din CLAUDE.md — planul o înlocuiește cu phpMyAdmin/FTP/File Manager și cu `scripts/export_baza.php`.
- Restanțe din M3: gardă megapixeli (T1), `IP_SALT`/`SMTP_HOST` reale (runbook A.5), `X-Forwarded-For` — cPanel fără proxy, nu e nevoie (notat aici, nu se implementează), HTML necache-abil — acceptat, neschimbat.
- Riscuri notate: `composer.phar` poate să nu ruleze din `.cpanel.yml` → fallback `vendor.zip` (T3); phpMyAdmin limită de upload → `.gz`; FTP 3 GB → durată; MultiPHP handler în subfolder → verificare B.6; repo privat → token temporar.
- Nume consistente: `APP_INDEXABLE` / `app.indexable`, `Miniatura::MAX_PIXELI`, `scripts/export_baza.php`, `storage/migrare/flagprahova-server.sql`, `docs/runbook-lansare.md`, `docs/manual-admin.md`.
