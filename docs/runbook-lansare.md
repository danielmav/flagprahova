# Runbook de lansare — FLAG Prahova (cPanel fără SSH)

Scris pentru Daniel, nu pentru client. Presupune cunoașterea codului; nu explică de ce, doar ce
se face și în ce ordine. Nicio parolă sau token nu apare aici — toate sunt în
`materiale/dateconectare.txt` (gitignored) sau generate pe loc și notate acolo.

Context: hosting cPanel **fără SSH** (nu se poate activa pe planul curent). Deploy DOAR prin
cPanel Git™ Version Control (`.cpanel.yml`); baza se exportă local și se importă prin phpMyAdmin;
`fisiere/` (~3 GB) se urcă prin FTP/File Manager.

Repo-ul e **public** pe GitHub (`https://github.com/danielmav/flagprahova.git`) — vezi nota din
secțiunea B.3. Stadiul curent al lansării (ce e deja făcut) e în `CLAUDE.md` → „Stadiu" și în
ledger-ul SDD, nu aici — acest fișier e o procedură reutilizabilă, nu un instantaneu.

---

## A. Pregătire locală (o dată)

- [ ] 1. `git checkout main && git pull`; rulează suita
      (`for t in tests/*_test.php; do php "$t" || echo "FAIL: $t"; done`, de două ori la rând) —
      verde; `php database/verifica_migrare.php` — exit 0.
- [ ] 2. Contul clientului, local:
      `$PHP database/create_admin.php <email-client> "<Nume>" "<parolă generată>"`.
      Parola se generează cu `openssl rand -base64 18`, se notează în `materiale/dateconectare.txt`
      și se comunică clientului **separat**, nu prin email în clar (telefon, sau un mesaj care se
      șterge).
- [ ] 3. `$PHP scripts/export_baza.php` → `storage/migrare/flagprahova-server.sql` (dump filtrat,
      fără sesiuni/tokenuri, gata pentru import prin phpMyAdmin).
- [ ] 4. `vendor/` de producție, doar ca plasă de siguranță dacă pasul composer din `.cpanel.yml`
      pică pe server: într-un director temporar, `composer install --no-dev --optimize-autoloader`
      pe o copie a `composer.json`/`composer.lock`, apoi arhivează `vendor/` ca `vendor.zip`
      (~4 MB). Se urcă DOAR dacă e nevoie (vezi B.4).
- [ ] 5. `.env` de staging — deja generat local ca `storage/migrare/env-staging.txt` (gitignored),
      pornind din `materiale/dateconectare.txt`: `APP_ENV=prod`, `APP_DEBUG=false`,
      `APP_URL=https://flagprahova.ro/nou`, `BASE_PATH=/nou`, `APP_INDEXABLE=false`,
      `ADMIN_PATH=admin`, `DB_*` pentru baza `flagprah_nou` + utilizatorul creat în cPanel,
      `DB_WP_NAME=` gol, `MAIL_*`/`SMTP_*` din cPanel → Email Accounts (contul
      `noreply@flagprahova.ro`, host `mail.flagprahova.ro`, port 465 `SMTP_SECURE=ssl` sau 587
      `tls`), `IP_SALT=` generat cu `openssl rand -hex 32`, `TWIG_CACHE=true`. Nu lista valorile
      aici — fișierul e gitignored, conținutul se copiază direct în File Manager la pasul B.6.
      `contact_email_destinatar` **nu** intră în `.env`: se setează din admin → Setări, după import
      (vezi B.9).

## B. Staging (`public_html/nou`)

- [ ] 1. MySQL® Databases: baza `flagprah_nou`, utilizator `flagprah_nou`, toate privilegiile.
- [ ] 2. phpMyAdmin → `flagprah_nou` → Import → `flagprahova-server.sql` (dump-ul e ~1–2 MB, sub
      limita obișnuită de upload de 50 MB — nu e nevoie de `.gz`). Verifică:
      `SELECT COUNT(*) FROM meniu` = 255, `fisiere` = 991, `utilizatori` = 1 (clientul).
- [ ] 3. Git™ Version Control → Create: URL `https://github.com/danielmav/flagprahova.git`,
      path `/home/flagprah/repositories/flagprahova`, branch `main`.

      **Notă (stabilită azi, 2026-09-19):** cPanel Git Version Control respinge URL-uri HTTPS cu
      credențiale înglobate (`https://<token>@github.com/...` → eroare „not a valid URL"). Soluția
      aleasă: repo **public** pe GitHub, clonat prin HTTPS simplu, fără token.

      Dacă repo-ul ar deveni privat: singura variantă e o cheie SSH de deploy generată **în afara**
      cPanel-ului, fără passphrase (generatorul din cPanel forțează o passphrase, pe care Git
      Version Control nu o poate folosi), importată din SSH Access → Import Key ca `id_ed25519`,
      cu cheile de host ale GitHub deja în `~/.ssh/known_hosts`. Pe acest host SSH-ul de ieșire
      pare blocat (netestat complet) — deci varianta funcțională rămâne public + HTTPS.
- [ ] 4. File Manager: `composer.phar` deja urcat în `/home/flagprah/` (descărcat de pe
      getcomposer.org). Dacă lipsește sau pasul composer din `.cpanel.yml` pică (vezi jurnalul de
      deploy de la pasul 5), urcă `vendor.zip` (din A.4) în `repositories/flagprahova/` și
      extrage-l acolo, ca `vendor/`.
- [ ] 5. Git Version Control → Manage → Pull or Deploy → „Deploy HEAD Commit". Citește jurnalul:
      pasul composer (reușit sau căzut cu fallback pe `vendor/` existent — `.cpanel.yml` nu se
      oprește din cauza asta, dar verifică `test -f vendor/autoload.php` din jurnal), copierea în
      `public_html/nou`.
- [ ] 6. File Manager → `public_html/nou`: creează `.env` (conținutul din `env-staging.txt`,
      copiat manual — fișierul nu e în repo); copiază `.htaccess` din repo
      (`repositories/flagprahova/.htaccess`) în `public_html/nou/.htaccess`. MultiPHP Manager →
      PHP 8.3 deja setat pe domeniu; verifică dacă blocul de handler scris în `.htaccess`-ul din
      `public_html` e moștenit de `nou/` — dacă nu, adaugă în `nou/.htaccess` blocul
      `<IfModule mime_module> AddHandler application/x-httpd-ea-php83 …` copiat din
      `public_html/.htaccess`.
- [ ] 7. Drepturi: `storage/`, `storage/cache/twig`, `storage/logs`, `fisiere/` scriibile (755 e
      suficient pe cPanel cu suPHP/LSAPI; **nu** 777).
- [ ] 8. `fisiere/` prin **FTP** (FileZilla, portul 21, cont din `dateconectare.txt`; SFTP nu e
      disponibil): urcă toți anii `fisiere/AAAA/` (fără `mini/`) în `public_html/nou/fisiere/`.
      ~3 GB — pornește-l primul, durează ore; limitează la maximum 2 transferuri simultane.
      Verificare: în File Manager, „Select All" în `fisiere/2017` etc. și compară numărul cu local
      (`find fisiere -type f -not -path 'fisiere/mini/*' | wc -l` = 991 + `.htaccess`). Dacă
      numerele diferă, rerulează transferul cu opțiunea FileZilla „Overwrite if different size" —
      se retrimit doar fișierele lipsă sau incomplete.

      (Deja urcat la 2026-09-19 — la o reluare, verifică doar numerele de mai sus.)
- [ ] 9. Verificări (Claude, prin `curl`):
      - `https://flagprahova.ro/nou/` → 200 + `noindex`
      - `/nou/2014-2020/cooperare` → 200 + miniaturi 200
        (`/nou/fisiere/mini/480/2022/10/instruire-01-busteni.webp`)
      - `/nou/robots.txt` = `Disallow: /`
      - `/nou/templates/` → 403; `/nou/fisiere/x.php` → 403
      - `/nou/admin/login` → 200; `/nou/health` → `{"ok":true}`
      - login cu contul clientului
      - un upload
      - un mesaj de contact ajuns pe `contact_email_destinatar` (setat din admin → Setări, nu în
        `.env` — vezi A.5)
      - un „parolă uitată" primit
- [ ] 10. Daniel + clientul validează conținutul pe staging (textul Acasă 2021-2027, linkul
      stricat din „Măsura 1", logo-urile, meniurile).

## C. Lansare (`public_html`)

- [ ] 1. Backup WordPress: cPanel → Backup → „Download a Home Directory Backup" +
      „Download a MySQL Database Backup" pentru baza WP (ambele în `materiale/arhiva/`,
      gitignored).
- [ ] 2. File Manager: redenumește `public_html` → `wp-vechi` (situl vechi cade ~10 minute),
      creează `public_html` gol, mută `public_html/wp-vechi/nou/*` → `public_html/` (inclusiv
      `.env`, `.htaccess`, `fisiere/`, `storage/`; mutarea în același filesystem e instantanee).
- [ ] 3. Editează `.env`: `APP_URL=https://flagprahova.ro`, `BASE_PATH=` gol,
      `APP_INDEXABLE=true`.
- [ ] 4. Local: în `.cpanel.yml`, `DEPLOYPATH=/home/flagprah/public_html`; commit
      `M4: lansare — DEPLOYPATH public_html`, push. cPanel → Git → Pull → Deploy HEAD Commit.
- [ ] 5. MultiPHP Manager → confirmă PHP 8.3 pe `flagprahova.ro`; deschide `.htaccess` din
      `public_html` și verifică blocul handler + regulile din repo (regula HTTPS exceptează
      `flagprahova.test` — vezi comentariul din `.htaccess`).
- [ ] 6. SSL: cPanel → SSL/TLS Status → AutoSSL activ pe domeniu; regula HTTPS din `.htaccess`
      forțează `https://`.
- [ ] 7. Verificări (Claude): aceleași ca la B.9, pe `https://flagprahova.ro/` (fără `/nou`), plus:
      - `robots.txt` cu `Sitemap:` și `/sitemap.xml` cu `<loc>https://flagprahova.ro/...`
      - un URL WP vechi (`/wp-content/uploads/2017/06/Organigrama.pdf`) → 404
      - `www.flagprahova.ro` → redirect la fără `www` (dacă nu, adaugă regulă în `.htaccess`)
- [ ] 8. Google Search Console: proprietatea `flagprahova.ro` (verificare prin DNS TXT sau fișier
      HTML urcat în `public_html`), trimite `https://flagprahova.ro/sitemap.xml`.
- [ ] 9. Baza WP veche și `wp-vechi/` rămân 30 de zile; ștergere programată: **2026-10-19**.

## D. Predare

- [ ] 1. `docs/manual-admin.md` (Task 4) exportat ca PDF (Chrome „Print to PDF" pe
      `manual-admin.html` randat din markdown, sau trimis ca `.md` + link) și trimis clientului cu
      contul și instrucțiunile de resetare a parolei.
- [ ] 2. `CLAUDE.md`: stadiu „Lansat 2026-09-19", cu referință la `docs/runbook-lansare.md` în
      secțiunea „Server / deploy".
