# FLAG Prahova — notes for Claude

Slim 4 + Twig + PDO, PHP ≥ 8.1, fără build step; Bootstrap/Quill/SortableJS vendorate în `assets/vendor/`.
Spec: `docs/superpowers/specs/2026-09-18-flagprahova-site-nou-design.md`. Planuri: `docs/superpowers/plans/`.
Stadiu: Plan 1 (admin) și Plan 2 (migrare WP) mergeuite în `main` (2026-09-18). Plan 3 (sit public) mergeuit în `main` (2026-09-18); Plan 4: task-urile 1–4 mergeuite în `main` (2026-09-19); STAGING funcțional pe `https://flagprahova.ro/nou/` din 2026-09-19 (deploy prin cPanel Git, baza `flagprah_nou`, cont admin daniel.mirea@gmail.com); urmează validarea clientului și lansarea (runbook §C, `docs/runbook-lansare.md`).
Reguli generale pentru orice proiect web (Bootstrap, Open Graph, pretty URL, SEO) sunt în `~/.claude/CLAUDE.md`.

## Server / deploy
- Hosting CloudLinux/cPanel cu CageFS: `/opt/cpanel/ea-php83/.../php` NU e vizibil din `.cpanel.yml` (composer pică) → `vendor/` se urcă ca `vendor.zip` (construit local: `composer install --no-dev` pe o copie a `composer.json`+`lock`, apoi Compress-Archive) și se extrage în `~/repositories/flagprahova/`; PHP-ul din docroot e „inherits the PHP package" (PHP Selector), fără bloc handler în `.htaccess`.
- Hosting cPanel FĂRĂ SSH (nu se poate activa pe planul curent): deploy DOAR prin cPanel Git Version Control (`.cpanel.yml`), baza se exportă local (`mysqldump`) și se importă prin phpMyAdmin, `fisiere/` (3 GB) se urcă prin FTP/File Manager; `DB_WP_NAME` gol pe server; PHP 8.3 la lansare.
- cPanel Git Version Control: refuză URL-uri HTTPS cu credențiale (`https://<token>@github.com/…`), SSH de ieșire spre GitHub e blocat pe host, iar generatorul de chei din cPanel cere obligatoriu parolă (inutilizabil la deploy) → repo-ul GitHub e PUBLIC și clonat prin HTTPS simplu. Clone-ul rulează în fundal (lista nu se reîmprospătează; F5); un clone eșuat dispare tăcut și lasă `~/repositories/<nume>` gol.
- Jurnalele de deploy: `~/.cpanel/logs/vc_<timestamp>_git_deploy.log` (File Manager, fișiere ascunse); „Information about the most recent deployment is unavailable" după un deploy = a picat. Local, Daniel le salvează în `materiale/erori/` (gitignored).
- `vendor.zip` se construiește local: `cp composer.json composer.lock $TEMP/fp-vendor/ && php C:/laragon/bin/composer/composer.phar install --no-dev --optimize-autoloader` acolo (wrapper-ul `composer` din PATH nu merge din Git Bash), apoi `Compress-Archive` → `storage/migrare/vendor.zip` (~0,7 MB); se extrage în `~/repositories/flagprahova/` (rezultă `vendor/autoload.php` direct, nu `vendor/vendor/`).
- Verificarea staging-ului după deploy: `curl -sI https://flagprahova.ro/nou/{,robots.txt,health,admin/login,2014-2020/cooperare,templates/}` + eșantion de 25 de `fisiere.cale` din baza locală (`mysql -N` scoate CRLF → `c=${c%$'\r'}` înainte de a compune URL-ul).
- Date de conectare (cPanel, admin vechi) în `materiale/dateconectare.txt` (gitignored) — nu le lipi în transcript.

## Comenzi
- Cont admin local: `admin@flagprahova.ro` / `parola-locala`. Baza locală e deja MIGRATĂ (255 intrări, 991 fișiere); `php database/verifica_migrare.php` trebuie să dea exit 0.
- `materiale/dateconectare.txt` are linii FĂRĂ etichetă (parola e pe rândul de sub cont) → nu-l citi cu cat/grep/awk; `.env`-ul de staging se generează cu `php scripts/gen_env_staging.php` (citește fișierul, scrie `storage/migrare/env-staging.txt`, afișează doar host/port/user/lungimi).
- Fără `python3`, fără parser YAML local (js-yaml/PyYAML/ext-yaml) → `.cpanel.yml` se verifică vizual; blob-ul git e LF chiar dacă working copy e CRLF.
- `scripts/export_baza.php` refuză să ruleze fără un cont în `utilizatori` în afară de `admin@flagprahova.ro` (intenționat); pe server utilizatorul MySQL e `flagprah_daniel` (nu `flagprah_nou` cum zice runbook-ul A.5/B.1).
- PHP CLI: `C:/laragon/bin/php/php-8.3.31-nts-Win32-vs16-x64/php.exe` (`php` din PATH e tot 8.3, dar ținem calea explicită pentru extensii).
- MySQL: `C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe -u root --default-character-set=utf8mb4 flagprahova`. NU pasa diacritice pe linia de comandă.
- Migrare/seed: `php database/migrate.php && php database/seed.php` (idempotente). Cont: `php database/create_admin.php email nume parola`.
- Teste: `for t in tests/*_test.php; do php "$t" || echo "FAIL: $t"; done` — rulează în proces pe baza REALĂ `flagprahova`, indiferent de conținutul ei (excepție: `tests/export_baza_test.php` presupune baza migrată — meniu cu `cooperare`, dump > 100 KB); fiecare test își șterge/restaurează datele în `finally`. Rulează suita de DOUĂ ORI la rând ca dovadă că nu lasă urme (apoi `verifica_migrare.php`).
- Capturi: `node tests/capturi.mjs` → `storage/shots/` (landing, acasă 2021/2014, pagină, dosar, contact, 404, login admin — desktop 1366 și „mobil” 390). Chrome headless are lățime minimă ~500 px → captura „mobil” la 390 px iese tăiată (nu e bug CSS); paginile autentificate se capturează cu Puppeteer (nu există încă).

## Convenții
- Fluxul de lucru: brainstorming → spec → plan (`docs/superpowers/plans/`) → execuție cu subagenți (`superpowers:subagent-driven-development`), ledger în `.superpowers/sdd/<plan>/progress.md` (gitignored). Modele: sonnet pentru task-uri mecanice, opus pentru integrare, fable doar la revizia finală.
- `fisiere.legacy_url` are collation case-insensitive → `gasesteDupaLegacy` folosește `WHERE BINARY` (există `altul.pdf` și `altul.PDF` reale).
- `Html::curata()` permite și `tel:`; `Curata` (migrare) aplică wpautop DUPĂ `Html::curata`, pe noduri, și convertește `h4-h6 → h3`, `h1 → h2`.
- Intrarea de meniu e unitatea de conținut (`meniu.tip`: pagina/document/dosar/link/galerie). Slug unic pe secțiune.
- Prepared statements native: placeholdere distincte, LIMIT inline `(int)`.
- Toate POST-urile de admin cer `_csrf` (sau header `X-CSRF` la JSON) — `Admin\Helpers::csrfOk()`.
- `render()` din `Admin\Helpers` pune automat `utilizator` + `flash`; picker-ul de fișiere pasează `utilizator => null` ca să nu randeze sidebar-ul.
- Uploads: `/fisiere/AAAA/LL/`, MIME din conținut (`Fisiere\Upload`), PHP blocat prin `fisiere/.htaccess`. Ștergerea e refuzată dacă fișierul e referit (`Meniu\Repository::fisierFolosit`).
- HTML din editor trece prin `Support\Html::curata()` la salvare, nu la afișare.
- Mail: `SMTP_HOST` gol → `storage/logs/mail.log` (dev). Pe prod trebuie completat.
- `Meniu\Repository::arbore()` întoarce noduri cu `copii` (subarborele complet, direcți) și `descendenti` (întreg — numărul total de descendenți, calculat recursiv) — nu confunda cele două chei.
- `Support\Html::curata()` are listă albă de scheme URL (ex. `http`, `https`, `mailto`) și respinge explicit `//` (protocol-relative); păstrează doar text și elemente permise, restul e eliminat.
- Twig 3.28 nu are `{% for … if %}` (sintaxă veche Twig 1); pentru filtrare în buclă folosește `|filter(...)` pe array înainte de `{% for %}`.
- În teste, helper-ul `cerere()` acceptă și o listă de fișiere per câmp (upload multiplu), nu doar un fișier singular.
- `PasswordTokenRepository::issue($uid, $invalideazaVechi)` + `pastreazaDoar()`: la re-invitare/parolă-uitată, invitația/tokenul vechi rămâne valabil până când emailul nou chiar pleacă (nu se invalidează prematur dacă trimiterea eșuează).
- „Parolă uitată” are prag fix de 1500 ms pe răspuns, indiferent dacă emailul există, ca să nu scurgă prin timing dacă un cont e înregistrat.
- Hook-ul gitleaks are `.gitleaksignore` pentru `assets/vendor/quill/quill.min.js` (fals pozitiv, cod minificat).
- Rutele publice stau la finalul lui `src/Routes.php` (`/`, `/{perioada}/`, `/{perioada}/{slug}`, contact POST, `/fisiere/mini/...`, `/sitemap.xml`, `/robots.txt`); datele comune de layout vin din `Public\Context` (`sectiuni/sectiune/arbore/gaseste/href/variabile/base`), o dată per cerere.
- Fonturile (DM Serif Display, DM Sans) sunt self-hostate în `assets/fonts/` + `assets/css/fonts.css`; se (re)descarcă cu `php scripts/descarca_fonturi.php`.
- Setările de contact folosite public: `contact_adresa`, `contact_telefon`, `contact_email_public` (separate de `contact_email_destinatar`, unde ajung mesajele din formular).
- `router.php` e gitignored (necesar doar pentru PHP built-in server local); la un clone nou, copiază-l din `pestelocal`.
- `APP_URL` INCLUDE `BASE_PATH` (staging: `APP_URL=https://flagprahova.ro/nou`, `BASE_PATH=/nou`), iar href-urile din `Context` poartă deja baza → URL-urile absolute (canonical, OG, JSON-LD, sitemap) se compun DOAR cu `Context::urlPublic()` / funcția Twig `url_public()`, care scot baza din cale înainte de `app.url`; nu concatena `app.url ~ …` în șabloane.
- Dropdown-urile de desktop se plafonează cu `:has()` (Firefox ≥ 121 / Safari ≥ 15.4; în browserele vechi se pierde doar plafonul de derulare, nu și flyout-urile), iar listele de nivel 2 cu peste 12 intrări își randează nivelul 3 INLINE (`fp-submenu--inline`, li cu `fp-has-sub-inline`), nu ca flyout lateral — altfel `overflow` ar tăia submeniul.
- Formularul de contact are throttle de 5 mesaje/oră/IP (`mesaje_contact.ip_hash`, `trimis_la`); peste prag răspunde cu același 302 „succes" tăcut ca la bot, fără salvare și fără mail. Fără `IP_SALT`, `ip_hash()` e null și throttle-ul se sare.
- Miniaturile galeriilor se generează la cerere în `fisiere/mini/{480|1600}/…` (`Fisiere\Miniatura`), gitignored ca tot `fisiere/`; `reset_continut.php` le șterge.
- JSON-LD: construiește obiectul ca hash Twig și emite-l o singură dată cu `{{ ld|json_encode(constant('JSON_HEX_TAG'))|raw }}`; NU interpola valori în `<script>` (autoescape-ul HTML strică JSON-ul). În teste, extrage blocul `ld+json` și `json_decode`-l, nu căuta substringuri.
- `Setari\Repository::toate()` cache-uiește per proces la prima citire → în teste setează valorile (`set()`) ÎNAINTE de prima `cerere()` și restaurează-le în `finally`.
- Chrome headless ad-hoc: `chrome.exe --headless=new --disable-gpu --user-data-dir="$TEMP/<profil>" --window-size=W,H --screenshot=<png> <url>` — fără `--user-data-dir` dedicat nu scrie nimic dacă mai e un Chrome deschis; Read pe PNG ca să judeci layoutul.
- `og_image` e VARIABILĂ Twig (implicit `/assets/img/og-default.png`), pasată din controller — nu bloc. Namespace-ul `App\Public` e valid (PHP ≥ 8.0 acceptă cuvinte rezervate în nume calificate).
- `scripts/descarca_fonturi.php` dedupează pe conținut: Google servește un singur woff2 variabil pentru toate greutățile DM Sans → 4 fișiere în `assets/fonts/`, 8 reguli `@font-face` (500/700 refolosesc `dm-sans-400-*.woff2`; e corect).
- La dispatch de subagenți pune explicit linia `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>` în prompt, altfel folosesc atribuirea din reminderul lor.
- Restanțe pentru Plan 4 (din revizia finală M3): gardă de megapixeli la `Fisiere\Miniatura` (PNG uriaș → OOM), HTML-ul public nu e cache-abil (sesiune pornită pe orice cerere), `X-Forwarded-For` la throttle dacă e proxy, `IP_SALT` și `SMTP_HOST` reale pe server.
- `APP_INDEXABLE=false` (staging) → noindex pe tot situl public + `robots.txt` `Disallow: /`; `Fisiere\Miniatura::MAX_PIXELI` (40 MP) refuză sursele uriașe cu 404 înainte de decodare.

## Migrare din WordPress

- Se rulează O SINGURĂ DATĂ înainte de predare: re-rularea `migrate_wp.php` suprascrie titlurile/conținutul/vizibilitatea editate de client din admin (doar slug-ul e păstrat).
- `Legacy::pagina($id, $preferaBuilder)` + `Harta::PAGINI_BUILDER = [277]`: pagini cu `post_content` spam dar conținut real în `_variant_page_builder_html`. Paginile 277/288/307 NU sunt goale (au builder html).
- De validat de client: `database/data/acasa-2021-2027.html` (scris din PDF-ul SDL; abrevierea „PAP”); pagina „Măsura 1” are un link cu text stricat din sursă.
- Sursa e baza WP veche `flagprahova_wp_old` (`DB_WP_*` în `.env`, read-only, doar pentru scripturile de migrare).
- Ordine: `import_fisiere.php --zip=...` (atașamente → `fisiere/AAAA/LL/`, sare miniaturile WP și pluginurile) →
  `migrate_wp.php` (funcția `migreaza()`: arborele 2014-2020, meniul fix 2021-2027, galeriile Cooperare, Acasă) →
  `verifica_migrare.php` (funcția `verifica()`: documente fără fișier, pagini goale, galerii fără imagini, exit 1 dacă lipsesc fișiere, exit 2 dacă există alte probleme).
- Idempotență pe `legacy_id` (`meniu`) / `legacy_url` (`fisiere`): re-rularea oricărui script nu duplică rânduri; `migrate_wp.php` actualizează, nu re-creează.
- `Harta::SET_2021` decide ce intrări vechi trec în secțiunea 2021-2027; schimbarea ei mută automat intrările la următoarea migrare.
- `reset_continut.php --da` rulează DOAR cu `APP_ENV=dev`: șterge galerii/meniu/fișiere și reface `setari` din `seed.php`. Fișierele de pe disc NU se șterg — `import_fisiere.php` le (re)înregistrează după reset fără să le rescrie (verifică potrivirea pe conținut, nu doar pe nume, ca să nu creeze dubluri „-2”).
- URL-urile WP pot conține spații DUBLE (`%20%20`, ex. `2019/06/anunt prelungire  apel M1.pdf`). `Legacy::normalizeazaUrl()` face doar `trim` + `rawurldecode`, NU colapsează spațiile: altfel potrivirea cu `fisiere.legacy_url` cade și documentele (334, 423) se pierd tăcut.
- `tests/migrare_wp_test.php` rulează `migreaza()` pe baza REALĂ (merge și pe baza migrată): șterge doar rândurile apărute în timpul testului și restaurează slug-ul/galeria/legacy_url-urile pe care le modifică.
- Hero-urile publice au fotografii de fundal (`assets/img/hero/*.webp`, 1920 + 960 px, generate cu `php scripts/genereaza_hero.php` din `materiale/imagini/`, gitignored); landing-ul are slider CSS-only (3 imagini, `@keyframes fp-slide`, oprit la `prefers-reduced-motion`); secțiunile își aleg imaginea după slug (`hero/{slug}-*.webp`). Ilustrația SVG nu se mai folosește în hero.
- Conținutul din editor se randează cu `|cu_baza|raw`: filtrul prefixează `BASE_PATH` la `src`/`href` absolute (`/fisiere/...`), ca pe staging (`/nou`) imaginile din pagini să nu cadă pe situl vechi. HTML-ul din bază rămâne fără bază (portabil).
- `meniu.publicat_la` (DATE, opțional, editabil în admin): `_document_rand.twig` afișează data prin `data_publicare()` — zi lună an dacă e setată, altfel luna+anul din calea fișierului (`AAAA/LL/`). `database/completeaza_publicat_la.php` o umple din datele paginilor WP doar pentru copiii lui `noutati` și scrie `storage/migrare/publicat_la.sql` pentru staging (acolo trebuie rulat și `migrate.php`-ul echivalent: `ALTER TABLE meniu ADD COLUMN publicat_la DATE NULL AFTER vizibil`). `migrate.php` adaugă coloanele noi idempotent (INFORMATION_SCHEMA).
- Feedback client 2026-09-22 (adresă Băicoi, program 08–18, Arhivă înaintea lui Contact, 7 noutăți 2014-2020 mutate în Arhiva 2021-2027 = `Harta::ARHIVA_2021_COPII`, Utile → dosar cu Documente (9081, gol) + Rețete (9082, fostul conținut), documentele se deschid în filă nouă via `fila_noua` din `Context::arbore`): local e aplicat; pe STAGING trebuie rulat `storage/migrare/feedback-client-2026-09-22.sql` (gitignored; folosește `legacy_id`, nu id-uri) prin phpMyAdmin — migrarea NU se re-rulează acolo.
