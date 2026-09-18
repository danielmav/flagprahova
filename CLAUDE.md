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
- `Meniu\Repository::arbore()` întoarce noduri cu `copii` (subarborele complet, direcți) și `descendenti` (întreg — numărul total de descendenți, calculat recursiv) — nu confunda cele două chei.
- `Support\Html::curata()` are listă albă de scheme URL (ex. `http`, `https`, `mailto`) și respinge explicit `//` (protocol-relative); păstrează doar text și elemente permise, restul e eliminat.
- Twig 3.28 nu are `{% for … if %}` (sintaxă veche Twig 1); pentru filtrare în buclă folosește `|filter(...)` pe array înainte de `{% for %}`.
- În teste, helper-ul `cerere()` acceptă și o listă de fișiere per câmp (upload multiplu), nu doar un fișier singular.
- `PasswordTokenRepository::issue($uid, $invalideazaVechi)` + `pastreazaDoar()`: la re-invitare/parolă-uitată, invitația/tokenul vechi rămâne valabil până când emailul nou chiar pleacă (nu se invalidează prematur dacă trimiterea eșuează).
- „Parolă uitată” are prag fix de 1500 ms pe răspuns, indiferent dacă emailul există, ca să nu scurgă prin timing dacă un cont e înregistrat.
- Hook-ul gitleaks are `.gitleaksignore` pentru `assets/vendor/quill/quill.min.js` (fals pozitiv, cod minificat).
- `router.php` e gitignored (necesar doar pentru PHP built-in server local); la un clone nou, copiază-l din `pestelocal`.
