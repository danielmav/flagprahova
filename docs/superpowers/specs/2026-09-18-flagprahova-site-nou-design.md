# FLAG Prahova — sit nou (design aprobat)

Data: 2026-09-18. Client: Asociația FLAG Prahova (https://www.flagprahova.ro). Brief: `materiale/lista-taskuri.txt`.
Model de referință (layout „similar, nu identic"): https://www.flagbratulmacin.ro/.

## 1. Scop

Înlocuim situl WordPress cu un sit propriu, împărțit în două secțiuni: **FLAG Prahova 2021-2027** și
**FLAG Prahova 2014-2020**. Conținutul 2014-2020 se migrează din situl vechi; 2021-2027 pornește cu
documentele din brief. Adminul trebuie să fie minimal: clientul încarcă fișiere și le agață în meniu,
rareori scrie pagini.

Decizii luate cu clientul/dezvoltatorul (2026-09-18):

| Subiect | Decizie |
|---|---|
| Structura | Landing cu două carduri; fiecare perioadă are meniul ei propriu, header/footer comune, comutator de perioadă în header |
| Migrare | Automată, tot arborele de meniu + toate fișierele din arhivă (documente și galerii) |
| URL-uri vechi | **Nu se păstrează**; fără redirecturi legacy |
| Lansare | Staging în subfolder pe același cPanel, apoi mutare în `public_html` |
| Imagini | Fotografii cu licență liberă (pești, peisaje Prahova) + grafică SVG proprie |
| Formular contact | Se păstrează, trimite pe adresa din setări prin SMTP cPanel |
| Model admin | „Meniul este conținutul": o singură entitate, intrarea de meniu, cu tip |
| Conturi | Mai mulți utilizatori, fără roluri, resetare parolă prin email |
| Noutăți | Doar submeniu (dropdown cu documente), fără pagină-listă |

## 2. Stack și structură

Stackul casei, ca la `pestelocal`: PHP 8.1+ (versiunea reală se confirmă pe server), **Slim 4**,
**Twig**, **PDO/MySQL** (SQL scris de mână), phpdotenv, PHPMailer. Frontend: **Bootstrap 5.3** vendorat
în `assets/vendor/bootstrap/`, **Quill** (WYSIWYG) și **SortableJS** vendorate, JS vanilla, fără build step.

```
index.php            front controller
src/Bootstrap.php    env, settings, Slim, Twig, container, erori
src/Routes.php       tabel de rute
src/Public/          controllere publice (Landing, Sectiune, Pagina, Contact, Seo)
src/Admin/           Auth, AuthMiddleware, LoginThrottle, Csrf, controllere admin
src/Meniu/           Repository (arbore, slug, CRUD)
src/Fisiere/         Repository + Upload (validare, nume, foldere an/lună)
src/Support/         helpers.php, Mailer, TimeToken
config/settings.php  citește .env
templates/           layout.twig, landing.twig, sectiune/, pagina/, admin/
assets/{css,js,img,vendor}/
fisiere/             uploads (gitignored), PHP blocat prin .htaccess
storage/{cache,logs}/
database/            schema.sql, migrate_wp.php, import_fisiere.php, seed.php
tests/               scripturi PHP de test (convenția pestelocal: `*_test.php`)
docs/superpowers/    specs + planuri
```

Se copiază și se adaptează din `pestelocal`: `Admin/Auth`, `Admin/AuthMiddleware`, `Admin/LoginThrottle`,
`Admin/PasswordTokenRepository`, `Support/DocumentUpload`, `Support/ImageUpload`, `Form/TimeToken`,
`Mail/Mailer`. Din `motociclete`: integrarea Quill din `templates/admin/layout.twig`.

## 3. Model de date (MySQL, utf8mb4)

- **`sectiuni`** — cele două perioade. `id`, `slug` (`2021-2027`, `2014-2020`), `titlu`, `subtitlu`,
  `acasa_html` (text acasă), `hero_imagine`, `ordine`.
- **`meniu`** — intrarea de meniu = unitatea de conținut.
  `id`, `sectiune_id`, `parent_id` (NULL = nivel 1), `ordine`, `titlu`, `slug` (unic pe secțiune),
  `tip` ENUM(`pagina`,`document`,`dosar`,`link`,`galerie`), `continut_html` (pagină),
  `fisier_id` (document), `url` (link), `sablon` ENUM(`standard`,`contact`) (doar pagină),
  `vizibil` TINYINT, `creat_la`, `modificat_la`. Arbore pe oricâte niveluri (situl vechi are 3).
- **`fisiere`** — registrul managerului de fișiere. `id`, `nume_afisat`, `cale` (relativ la `/fisiere/`,
  ex. `2026/08/comunicat-sdl.pdf`), `mime`, `marime`, `incarcat_la`, `incarcat_de`, `legacy_url`
  (URL-ul din situl vechi, doar informativ pentru migrare).
- **`galerie_imagini`** — `id`, `meniu_id`, `fisier_id`, `ordine`, `legenda`.
- **`utilizatori`** — `id`, `email` (unic), `nume`, `parola_hash`, `ultimul_login`, `creat_la`.
- **`parola_tokens`** — token hash, `utilizator_id`, `expira_la`, `folosit_la`.
- **`login_incercari`** — `ip_hash`, `scope`, `la` (throttle 5/15 min, ca la pestelocal).
- **`setari`** — cheie/valoare: `contact_email_destinatar`, `landing_titlu`, `landing_text`,
  `footer_text`, `logo_uri` (JSON cu logo-urile partenere).
- **`mesaje_contact`** — `id`, `sectiune_id`, `nume`, `email`, `mesaj`, `ip_hash`, `trimis_la`, `email_trimis` TINYINT.

Reguli: ștergerea unei intrări de meniu șterge și copiii (confirmare în admin, cu numărul lor).
Un fișier se poate șterge doar dacă nu e referit din `meniu.fisier_id`, `galerie_imagini` sau din
`continut_html` (căutare text pe cale). Slug-ul se generează din titlu la creare și se poate edita.

## 4. URL-uri publice

| Rută | Ce randează |
|---|---|
| `/` | Landing: două carduri (titlu, subtitlu, „Detalii"), logo-uri, footer |
| `/{perioada}/` | Acasă secțiune: hero, `acasa_html`, carduri către intrările de nivel 1 |
| `/{perioada}/{slug}` | `pagina` → conținut; `dosar` → lista copiilor (plasă de siguranță la click pe părinte); `galerie` → grilă de imagini cu lightbox Bootstrap |
| `/{perioada}/contact` | Pagină cu `sablon=contact`: conținut + formular POST |
| `/fisiere/...` | Fișier static; intrările `document` linkează direct aici; `link` → URL extern |
| `/sitemap.xml`, `/robots.txt` | Generate din DB |
| 404 | Pagină cu header/meniu al secțiunii curente (sau landing) |

Nu există redirecturi de la URL-urile WordPress. `/wp-content/...` va da 404 după lansare.

## 5. Admin (`/admin`)

Login (email + parolă), „Parolă uitată" (link pe email, 30 min), throttle. Bara laterală cu exact:
**Meniu**, **Fișiere**, **Setări**, **Utilizatori**, **Mesaje**, „Vezi situl", Ieșire.

- **Meniu**: tab pe secțiune; arbore cu drag & drop (SortableJS, nested) care salvează `parent_id` +
  `ordine` prin POST JSON; pe fiecare rând: tip (iconiță), vizibil, Editează, Adaugă sub, Șterge.
- **Editare intrare**: titlu, tip, părinte (select din arbore), vizibil, slug. Câmpuri după tip:
  `pagina` → Quill (imagine inserabilă prin managerul de fișiere), șablon; `document` → alege din
  fișiere (modal) sau încarcă acum; `link` → URL; `galerie` → încărcare multiplă, reordonare, legende;
  `dosar` → nimic în plus.
- **Fișiere**: listă pe an/lună, căutare după nume, încărcare multiplă (drag & drop), „Copiază link",
  redenumire nume afișat, ștergere (refuzată dacă e folosit, cu lista locurilor). Același ecran se deschide
  ca modal de alegere din editor (`?picker=1`).
- **Setări**: email destinatar formular, texte landing, footer, logo-uri.
- **Utilizatori**: listă, adaugă (trimite link de setare parolă), trimite link de resetare, șterge
  (nu pe sine, nu ultimul).
- **Mesaje**: lista formularelor primite, cu marcaj dacă emailul a plecat.

Fără statistici, SEO, roluri, revizii.

## 6. Frontend

- Paletă: alb, albastru primar `#0B4F9C`, albastru închis `#083A72` (hover/footer), fundal deschis
  `#E8F1FB`, accent apă `#2E8BC0`, text `#1B2430`. Font Google Manrope (self-hosted în `assets/fonts/`).
- Header: sigla FLAG Prahova + logo-urile obligatorii de finanțare (UE, Guvern, program; fișierele vin de
  la client sau se preiau din tema veche), comutator de perioadă (două pastile), meniu Bootstrap cu
  dropdown pe nivelul 1 și submeniuri pe nivelurile 2–3 (CSS propriu, deschidere pe hover/click);
  pe mobil offcanvas cu acordeon.
- Hero pe acasă de secțiune: fotografie (pești/lacuri Prahova, licență liberă, creditată în footer)
  + val SVG. Landing: fundal foto, două carduri albe cu umbră.
- Pagini: coloană de conținut (max 900px) + bloc lateral „Contact rapid". Documentele afișate ca rânduri cu iconiță după extensie + mărime.
- Footer: adresă/contact, logo-uri, disclaimer UE, link la cealaltă perioadă, „© Asociația FLAG Prahova".
- Server-rendered; JS doar pentru meniu mobil, lightbox și formular.

## 7. Migrare din WordPress

Sursa: `materiale/arhiva/flagprah_wp25.sql` (importat local în `flagprahova_wp_old`, prefix `wpt9_`) și
`materiale/arhiva/public_html-26august2026.zip` (`wp-content/uploads/`, ~3,1 GB, ~460 MB documente).

- `database/import_fisiere.php` — dezarhivează `wp-content/uploads/AAAA/LL/*` în `/fisiere/AAAA/LL/`,
  normalizează numele (ASCII, fără spații; numele original devine `nume_afisat`), sare peste
  miniaturile WordPress (`-300x200.jpg` etc.) și peste `js_composer`, `wc-logs`, `wp-less`; scrie
  rândurile în `fisiere` cu `legacy_url`.
- `database/migrate_wp.php` — citește meniul `Meniu FLAG` (231 intrări): reconstruiește arborele în
  secțiunea 2014-2020 păstrând ordinea; tipul devine `document` dacă URL-ul e un fișier găsit în `fisiere`,
  `dosar` dacă URL-ul e `#`, gol, un folder sau un placeholder (`http://ab`), `pagina` dacă e o pagină WP,
  `link` dacă e extern. Mută în 2021-2027 intrările din brief: „Comunicat SDL 2021-2027", anunțurile de
  angajare 2026 (Noutăți) și „SDL 2021-2027" (Strategie). Creează în 2021-2027 și meniul gol din brief
  (Acasă, Noutăți, Strategie, Acțiuni, Apel lansare, Arhivă, Proceduri operaționale FLAG, Media →
  Comunicate de presă / Animări / Galerie, Utile, Contact).
- Pagini cu conținut (Acasă, Cooperare, Măsura 1/2, Utile, Contact, comunicatele 2023/2024): HTML curățat
  de shortcode-uri `[vc_*]`, de textul spam injectat și de clasele WP; linkurile la uploads rescrise la
  `/fisiere/...`; galeriile din Cooperare devin intrări `galerie` copii ale paginii. Videoclipul YouTube
  rămâne ca iframe.
- Contact 2014-2020: ambele persoane; Contact 2021-2027: doar „Manager — Laura-Mădălina Manolache —
  Tel: 0762 609 685". Acasă 2021-2027: text scurt extras din `SDL a zonei de pescuit si avacultura.pdf`.
- Se ignoră: categoriile spam, WooCommerce, revizii, formularele CF7.
- Scripturile sunt idempotente (cheie = `legacy_id` pe `meniu` și `legacy_url` pe `fisiere`) și
  tipăresc un raport: intrări migrate pe tip, fișiere lipsă din arhivă, linkuri rupte rămase.

## 8. Securitate și erori

- CSRF token pe toate formularele de admin și pe formularul de contact; sesiune `httponly`, `samesite=Lax`.
- Upload: listă albă de extensii (`pdf doc docx xls xlsx ppt pptx odt jpg jpeg png webp zip`), verificare
  MIME reală, limită 50 MB, nume normalizat, `.htaccess` în `/fisiere/` cu `php_flag engine off` +
  `RemoveHandler`. Imaginile inserate în editor trec prin același upload.
- Contact: honeypot + `TimeToken`; mesajul se salvează în DB înainte de trimitere; eșecul SMTP se
  loghează în `storage/logs/mail.log` și utilizatorul primește oricum confirmare (mesajul e în admin).
- Parole `password_hash`, throttle pe login și pe „parolă uitată".
- Erori: handler propriu 404/500 cu layout-ul sitului; în `prod` fără detalii.

## 9. Testare

Scripturi în `tests/` (convenția pestelocal, rulate cu PHP-ul Laragon): rute publice 200 pentru toate
intrările vizibile, randarea meniului pe 3 niveluri, `dosar` fără copii, validarea upload-ului
(extensie, MIME, nume), login + throttle + CSRF, migrarea (numărul de intrări pe tip față de dump, zero
documente fără fișier), sitemap fără intrări invizibile. Verificare vizuală prin capturi headless
(desktop + mobil) pentru landing, acasă, pagină, admin meniu.

## 10. Deploy și lansare

- Repo `https://github.com/danielmav/flagprahova`, `.cpanel.yml` decuplat ca la pestelocal: repo în
  `~/repositories/flagprahova`, `composer install` în repo, copiere doar runtime în docroot.
- Staging: `public_html/nou/` cu bază `flagprah_nou`, `.env` propriu; `/fisiere/` urcat o dată prin SSH.
- Lansare: backup WP (fișiere + DB), mutare `public_html` → `~/wp-vechi/`, deploy în `public_html`,
  import DB, `.htaccess` (rutare + handler PHP scris de MultiPHP), verificare, ștergerea WP după 30 zile.
- SSH: cheia `~/.ssh/id_ed25519_flagprahova` (generată), de importat și autorizat în cPanel → Manage SSH Keys.

## 11. Milestone-uri

M0 fundație (schelet, DB, auth, layout admin) → M1 admin (meniu, editor, fișiere, setări, utilizatori) →
M2 public (landing, secțiuni, meniu, pagini, galerie, contact) → M3 migrare → M4 design final, SEO,
teste, capturi → M5 staging, lansare, predare (manual scurt pentru client).
