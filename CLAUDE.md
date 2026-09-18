# FLAG Prahova — notes for Claude

Slim 4 + Twig + PDO, PHP ≥ 8.1, fără build step; Bootstrap/Quill/SortableJS vendorate în `assets/vendor/`.
Spec: `docs/superpowers/specs/2026-09-18-flagprahova-site-nou-design.md`. Planuri: `docs/superpowers/plans/`.
Stadiu: Plan 1 (admin) și Plan 2 (migrare WP) mergeuite în `main` (2026-09-18). Plan 3 (sit public) mergeuit în `main` (2026-09-18); urmează Plan 4 = staging/lansare (deploy planificat 2026-09-19).
Reguli generale pentru orice proiect web (Bootstrap, Open Graph, pretty URL, SEO) sunt în `~/.claude/CLAUDE.md`.

## Server / deploy
- Hosting cPanel FĂRĂ SSH (nu se poate activa pe planul curent): deploy DOAR prin cPanel Git Version Control (`.cpanel.yml`), baza se exportă local (`mysqldump`) și se importă prin phpMyAdmin, `fisiere/` (3 GB) se urcă prin FTP/File Manager; `DB_WP_NAME` gol pe server; PHP 8.3 la lansare.
- Date de conectare (cPanel, admin vechi) în `materiale/dateconectare.txt` (gitignored) — nu le lipi în transcript.

## Comenzi
- Cont admin local: `admin@flagprahova.ro` / `parola-locala`. Baza locală e deja MIGRATĂ (255 intrări, 991 fișiere); `php database/verifica_migrare.php` trebuie să dea exit 0.
- PHP CLI: `C:/laragon/bin/php/php-8.3.31-nts-Win32-vs16-x64/php.exe` (`php` din PATH e tot 8.3, dar ținem calea explicită pentru extensii).
- MySQL: `C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe -u root --default-character-set=utf8mb4 flagprahova`. NU pasa diacritice pe linia de comandă.
- Migrare/seed: `php database/migrate.php && php database/seed.php` (idempotente). Cont: `php database/create_admin.php email nume parola`.
- Teste: `for t in tests/*_test.php; do php "$t" || echo "FAIL: $t"; done` — rulează în proces pe baza REALĂ `flagprahova`, indiferent de conținutul ei; fiecare test își șterge/restaurează datele în `finally`. Rulează suita de DOUĂ ORI la rând ca dovadă că nu lasă urme (apoi `verifica_migrare.php`).
- Capturi: `node tests/capturi.mjs` → `storage/shots/` (landing, acasă 2021/2014, pagină, dosar, contact, 404, login admin — desktop 1366 și „mobil” 390). Chrome headless are lățime minimă ~500 px → captura „mobil” la 390 px iese tăiată (nu e bug CSS); paginile autentificate se capturează cu Puppeteer (nu există încă).

## Convenții
- Fluxul de lucru: brainstorming → spec → plan (`docs/superpowers/plans/`) → execuție cu subagenți (`superpowers:subagent-driven-development`), ledger în `.superpowers/sdd/<plan>/progress.md` (gitignored). Modele: sonnet pentru task-uri mecanice, opus pentru integrare, fable doar la revizia finală.
- `scripts/review-package` din skill pică pe `tests/fixtures/rau.php.pdf` (driver git de diff pentru PDF) → construiește diff-ul manual cu `git diff … -- . ':!tests/fixtures' ':!assets/vendor'`.
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
