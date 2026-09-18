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

## Migrare din WordPress

- Sursa e baza WP veche `flagprahova_wp_old` (`DB_WP_*` în `.env`, read-only, doar pentru scripturile de migrare).
- Ordine: `import_fisiere.php --zip=...` (atașamente → `fisiere/AAAA/LL/`, sare miniaturile WP și pluginurile) →
  `migrate_wp.php` (funcția `migreaza()`: arborele 2014-2020, meniul fix 2021-2027, galeriile Cooperare, Acasă) →
  `verifica_migrare.php` (funcția `verifica()`: documente fără fișier, pagini goale, galerii fără imagini, exit 1 dacă lipsesc fișiere, exit 2 dacă există alte probleme).
- Idempotență pe `legacy_id` (`meniu`) / `legacy_url` (`fisiere`): re-rularea oricărui script nu duplică rânduri; `migrate_wp.php` actualizează, nu re-creează.
- `Harta::SET_2021` decide ce intrări vechi trec în secțiunea 2021-2027; schimbarea ei mută automat intrările la următoarea migrare.
- `reset_continut.php --da` rulează DOAR cu `APP_ENV=dev`: șterge galerii/meniu/fișiere și reface `setari` din `seed.php`. Fișierele de pe disc NU se șterg — `import_fisiere.php` le (re)înregistrează după reset fără să le rescrie (verifică potrivirea pe conținut, nu doar pe nume, ca să nu creeze dubluri „-2”).
- URL-urile WP pot conține spații DUBLE (`%20%20`, ex. `2019/06/anunt prelungire  apel M1.pdf`). `Legacy::normalizeazaUrl()` face doar `trim` + `rawurldecode`, NU colapsează spațiile: altfel potrivirea cu `fisiere.legacy_url` cade și documentele (334, 423) se pierd tăcut.
- `tests/migrare_wp_test.php` rulează pe baza REALĂ și șterge doar rândurile `legacy_id` apărute în timpul testului; se rulează fie ÎNAINTE de migrarea reală, fie DUPĂ un `reset_continut.php`.
