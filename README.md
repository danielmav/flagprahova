# FLAG Prahova

Situl Asociației FLAG Prahova (două perioade de programare) — Slim 4 + Twig + PDO, admin minimal, fără build step JS.

## Instalare locală

```bash
composer install
cp .env.example .env   # completează DB_* și IP_SALT
php database/migrate.php
php database/seed.php
php database/create_admin.php email@exemplu.ro "Nume Prenume" parola
```

Servește cu Laragon / Apache pe `http://flagprahova.test`, sau cu serverul built-in PHP (folosind `router.php`, copiat separat — vezi `CLAUDE.md`).

## Sit public

Rutele publice (fără `?id=`, fără `.php`; totul trece prin front controller):

- `/` — pagina de start, cu alegerea perioadei de programare.
- `/{perioada}/` — acasă de secțiune (`2021-2027`, `2014-2020`). `/{perioada}`, fără slash, face redirect 301
  spre forma canonică cu slash.
- `/{perioada}/{slug}` — intrarea de meniu: pagină, dosar (listă de documente și subdosare) sau galerie.
  Intrările de tip `document` și `link` fac redirect spre fișier, respectiv spre URL-ul extern.
- `POST /{perioada}/{slug}` — trimiterea formularului de contact (`_csrf`, honeypot și prag de timp).
- `/fisiere/AAAA/LL/...` — fișierele încărcate, servite static de Apache.
- `/fisiere/mini/480/...` și `/fisiere/mini/1600/...` — miniaturi WebP generate la cerere din imaginile
  galeriilor (blocate din `robots.txt`).
- `/sitemap.xml` — paginile, dosarele și galeriile vizibile, cu `lastmod`; fără documente și linkuri.
- `/robots.txt` — `Disallow` pe `/admin` și `/fisiere/mini/`, plus linkul către sitemap.
- Orice alt URL — 404 real (status 404, `noindex`), cu legături spre cele două secțiuni.

Capturile de ecran ale paginilor publice se fac cu `node tests/capturi.mjs` (rezultatele în `storage/shots/`,
ignorat de git).

## Admin

`http://flagprahova.test/admin/login` (calea e configurabilă din `ADMIN_PATH` în `.env`).

## Staging / producție

Găzduirea (cPanel) nu are acces SSH. Procedura completă e în `docs/runbook-lansare.md`; pe scurt:
export local al bazei cu `scripts/export_baza.php` → import prin phpMyAdmin; `fisiere/` urcat prin
FTP; codul prin cPanel → Git™ Version Control („Deploy HEAD Commit”), după rețeta din `.cpanel.yml`;
`composer.phar` sau `vendor.zip` urcate manual prin File Manager, dacă pasul composer din
`.cpanel.yml` pică.

Deploy-ul se face din cPanel → Git™ Version Control („Deploy HEAD Commit”), după rețeta din `.cpanel.yml`:
repo-ul stă în `~/repositories/flagprahova`, în afara docroot-ului, iar în docroot se copiază doar
`src/`, `templates/`, `config/`, `assets/`, `vendor/`, `index.php` și `fisiere/.htaccess`.

Ce NU face deploy-ul și trebuie făcut manual — checklist la prima instalare:

- [ ] **`.htaccess` din docroot** — copiat o singură dată, manual, din repo. Nu se rescrie la deploy:
      MultiPHP Manager scrie în el blocul de handler PHP (`ea-php83`), iar o suprascriere l-ar șterge
      și situl ar cădea pe versiunea implicită de PHP. Dacă se schimbă regulile de rutare din repo,
      se re-aplică manual doar diferența, păstrând blocul MultiPHP.
- [ ] **`.env`** — creat direct în docroot (nu e în repo și nu se atinge la deploy), pornind de la
      `.env.example`, cu:
  - `APP_ENV=prod`, `APP_DEBUG=false` — altfel erorile se afișează vizitatorilor;
  - `TWIG_CACHE=true` — șabloanele compilate în `storage/cache/twig`;
  - `DB_*` — utilizator MySQL dedicat sitului, nu cel de cPanel;
  - `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_SECURE` completate. Cu `SMTP_HOST` gol,
    emailurile (formularul de contact, linkurile de setare a parolei) se scriu doar în
    `storage/logs/mail.log` și nu pleacă nicăieri;
  - `IP_SALT` — șir lung, aleator, generat pe loc (ex. `openssl rand -hex 32`). Fără el, throttle-ul
    de login se dezactivează (fail-open);
  - `APP_URL` — adresa reală, cu schema corectă (`https://…`). Intră direct în linkurile din emailuri:
    o valoare greșită trimite oamenii la un link care nu funcționează;
  - `ADMIN_PATH`, `BASE_PATH` — potrivite cu locul real al instalării.
- [ ] **`storage/`** și **`fisiere/`** — scriibile de PHP (create de deploy, dar verifică drepturile).
- [ ] **Baza de date** — fără SSH nu se pot rula scripturi PHP pe server. Schema și conținutul vin
      dintr-un dump: local, `php scripts/export_baza.php` → `storage/migrare/flagprahova-server.sql`,
      importat în baza goală prin phpMyAdmin. Contul admin al clientului se creează tot local
      (`database/create_admin.php`) înainte de export, nu pe server. Procedura pas cu pas e în
      `docs/runbook-lansare.md`.
- [ ] **Verificare că nu se servește ce nu trebuie** — după deploy, următoarele trebuie să dea **403**:
      `/templates/`, `/config/`, `/storage/`, `/fisiere/x.php`. Dacă vreuna dă 200 sau 404 cu listare,
      `.htaccess`-ul din docroot sau `fisiere/.htaccess` lipsește ori nu e citit (`AllowOverride`).
- [ ] **Verificare funcțională** — login în admin, o încărcare de fișier, un mesaj din formularul de
      contact ajuns pe email, un link „parolă uitată” primit și folosit.

## Migrarea conținutului vechi

Migrarea din baza WordPress veche (`DB_WP_NAME` în `.env`) se rulează o singură dată, în această ordine
(vezi și secțiunea „Migrare din WordPress” din `CLAUDE.md`):

```bash
php database/import_fisiere.php --zip=cale/catre/arhiva-uploads.zip
php database/migrate_wp.php
php database/verifica_migrare.php
php database/migrate_wp.php   # a doua rulare: idempotentă, creat = 0
```

Pe dev, `php database/reset_continut.php --da` șterge tot conținutul migrat (galerii, meniu, fișiere)
și reface `setari` din `seed.php`, ca să poți relua migrarea de la zero.

### Migrarea se rulează O SINGURĂ DATĂ, înainte de predare

`migrate_wp.php` e idempotent pe `legacy_id` (nu duplică rânduri), dar la fiecare rulare
**rescrie titlul, conținutul (`continut_html`), tipul, părintele, ordinea și vizibilitatea**
fiecărei intrări migrate, cu valorile din WordPress. Singurul câmp păstrat este **slug-ul**
(ca să nu se rupă linkurile deja date publicului).

Concret: dacă clientul a editat din admin o pagină migrată, a ascuns o intrare sau a mutat-o,
o nouă rulare a lui `migrate_wp.php` îi anulează modificările. De aceea migrarea se face o
singură dată, înainte de predarea sitului; după predare nu se mai rulează.

### Server fără acces SSH

Pe o găzduire fără SSH (doar FTP / File Manager / phpMyAdmin) scripturile de migrare **nu**
se rulează pe server. Procedura este:

1. Migrarea se rulează **local**, pe o copie a bazei WordPress vechi (`DB_WP_*` în `.env`
   local), până când `verifica_migrare.php` iese cu 0.
2. Baza locală rezultată se exportă cu `php scripts/export_baza.php` →
   `storage/migrare/flagprahova-server.sql` (fără contul local, fără tokenuri/mesaje); importă-l
   în phpMyAdmin în baza goală a sitului.

   (dacă importul prin phpMyAdmin depășește limita de upload, se împarte fișierul sau se
   folosește opțiunea de import din fișier deja urcat prin FTP).
3. Folderul `fisiere/` (cu subfolderele `AAAA/LL/`) se urcă integral prin **FTP / File Manager**,
   împreună cu `fisiere/.htaccess`.
4. Pe server, `DB_WP_NAME` rămâne **gol** în `.env`: aplicația publică nu are nevoie de baza
   WordPress veche, iar scripturile de migrare nu trebuie să poată rula acolo.

Procedura completă de staging și lansare (pas cu pas, cu verificări) e în `docs/runbook-lansare.md`.

## Documentație

Spec: `docs/superpowers/specs/2026-09-18-flagprahova-site-nou-design.md`
Planuri: `docs/superpowers/plans/`
Runbook de lansare: `docs/runbook-lansare.md`
Note de implementare pentru Claude: `CLAUDE.md`
