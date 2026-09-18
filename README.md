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

## Admin

`http://flagprahova.test/admin/login` (calea e configurabilă din `ADMIN_PATH` în `.env`).

## Staging / producție

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
- [ ] **Baza de date** — rulate o singură dată, din repo, cu PHP 8.3
      (`/opt/cpanel/ea-php83/root/usr/bin/php`), nu din docroot:

  ```bash
  cd ~/repositories/flagprahova
  /opt/cpanel/ea-php83/root/usr/bin/php database/migrate.php
  /opt/cpanel/ea-php83/root/usr/bin/php database/seed.php
  /opt/cpanel/ea-php83/root/usr/bin/php database/create_admin.php email@exemplu.ro "Nume Prenume" parola
  ```

  Scripturile citesc `.env`-ul din repo, deci acolo trebuie să fie aceleași `DB_*` ca în docroot.
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

## Documentație

Spec: `docs/superpowers/specs/2026-09-18-flagprahova-site-nou-design.md`
Planuri: `docs/superpowers/plans/`
Note de implementare pentru Claude: `CLAUDE.md`
