# FLAG Prahova — Plan 3: situl public

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Situl public complet (landing, acasă pe secțiune, pagini/dosare/galerii/documente, contact, 404, sitemap/robots), randat server-side pe Bootstrap 5 cu o identitate vizuală proprie inspirată din template-ul cumpărat, peste baza deja migrată.

**Architecture:** Un `Public\Context` construiește o singură dată per cerere variabilele comune ale layout-ului (secțiuni, arborele vizibil decorat cu `href`, setări, nodul curent + strămoșii). `Public\PaginiController` randează landing/acasă/pagină și dispecerizează după `meniu.tip`; `Public\ContactController` primește formularul; `Public\SeoController` produce sitemap/robots; `Public\NotFoundHandler` înlocuiește 404-ul Slim. `Fisiere\Miniatura` generează WebP-uri la cerere în `fisiere/mini/{lățime}/…` (a doua cerere e servită direct de Apache). CSS propriu = un singur fișier `assets/css/site.css` peste Bootstrap, cu tokenuri în `:root`.

**Tech Stack:** PHP 8.1+ (CLI Laragon 8.3), Slim 4, Twig 3.28, PDO/MySQL, GD (WebP), Bootstrap 5.3 vendorat, fonturi DM Sans + DM Serif Display self-hosted, JS vanilla, harness `tests/_bootstrap.php`, capturi Chrome headless (`tests/capturi.mjs`).

**Spec:** `docs/superpowers/specs/2026-09-18-flagprahova-site-nou-design.md` — §4 (URL-uri), §6 (Frontend, revizuit 2026-09-18 cu direcția din template), §8 (contact/securitate), §9 (testare). Reguli generale (Bootstrap, OG, pretty URL, SEO) în `~/.claude/CLAUDE.md`.

## Global Constraints

- PHP ≥ 8.1 în cod; Bootstrap din `assets/vendor/bootstrap/` (nu CDN); fără biblioteci JS noi; fără build step.
- Fonturi self-hosted (`assets/fonts/*.woff2`, subseturi latin + latin-ext). Nicio cerere externă din pagini (nici Google Fonts, nici CDN).
- Template-ul din `materiale/template1` e DOAR inspirație: nu se copiază CSS/JS/imagini din el. Culori: bleumarin `#272B5C`, footer `#1B1F4A`, albastru `#1F6FC5`, bleu `#E6EEF8`, gradient hero `#E9F0FA → #FFFFFF`, accent portocaliu `#EC8C16` (rar), text `#2B2F4A`, estompat `#6F7390`.
- Logo-urile: `materiale/eu-flag.png`, `guvernul-romaniei.png`, `logo-flag-prahova.png`, `2021-2027.png` → `assets/img/logo/`. Textul „Cofinanțat de Uniunea Europeană" e HTML lângă steag.
- Pretty URL: `/`, `/{perioada}/`, `/{perioada}/{slug}`; fără `?id=`; 404 real (status 404) cu layout-ul sitului; `noindex` pe 404 și admin.
- Fiecare pagină: `<title>` + `meta description` unice, canonical, OG (`og:title/description/image/url/type`), `twitter:card`, un singur `h1`, JSON-LD (Organization pe landing, BreadcrumbList pe pagini).
- Se randează DOAR intrări `vizibil = 1` ai căror strămoși sunt toți vizibili. `document` → link direct la `/fisiere/{cale}`; `link` → URL extern (`rel="noopener"`).
- HTML-ul din `continut_html`/`acasa_html` e deja curățat la salvare — se afișează cu `|raw`, fără re-sanitizare.
- Formular contact: `_csrf` + honeypot `website` + `App\Form\TimeToken`; mesajul se salvează în `mesaje_contact` ÎNAINTE de trimitere; eșecul SMTP nu dărâmă răspunsul.
- Prepared statements native, placeholdere distincte, `LIMIT` inline `(int)`.
- Teste: `tests/public_*_test.php`, rulate pe baza REALĂ, independente de conținutul ei (își creează intrările temporare cu o marcă aleatoare și le șterg în `finally`). Suita se rulează de două ori la rând; `php database/verifica_migrare.php` trebuie să dea exit 0 la final.
- Comenzi: `PHP="C:/laragon/bin/php/php-8.3.31-nts-Win32-vs16-x64/php.exe"`; `MYSQL="C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root --default-character-set=utf8mb4` (fără diacritice pe linia de comandă mysql).
- Commit după fiecare task, prefix `M3:`; fără push.

## Structura de fișiere

| Fișier | Responsabilitate |
|---|---|
| `assets/css/fonts.css`, `assets/fonts/*.woff2`, `scripts/descarca_fonturi.php` | fonturile self-hosted + scriptul (dev) care le-a descărcat |
| `assets/css/site.css` | tokenuri, tipografie, header/meniu, hero, carduri, rânduri documente, galerie, footer, formular |
| `assets/js/site.js` | offcanvas mobil (Bootstrap), submeniuri pe touch, lightbox galerie |
| `assets/img/logo/*.png`, `assets/img/ilustratie.svg`, `assets/img/og-default.png` | logo-uri, ilustrația flat, imaginea OG 1200×630 |
| `src/Meniu/Repository.php` | + `arborePublic()` (doar vizibile, cu datele fișierului) |
| `src/Public/Context.php` | variabile comune layout, decorare `href`, căutare nod + strămoși |
| `src/Public/PaginiController.php` | landing, acasă secțiune, pagină (dispecer pe tip) |
| `src/Public/ContactController.php` | POST formular contact |
| `src/Public/SeoController.php` | `sitemap.xml`, `robots.txt` |
| `src/Public/NotFoundHandler.php` | 404/405 cu layout |
| `src/Public/MiniaturaController.php`, `src/Fisiere/Miniatura.php` | miniaturi WebP la cerere |
| `src/Form/TimeToken.php` | copiat din pestelocal |
| `src/Setari/Repository.php`, `database/seed.php`, `templates/admin/setari.twig` | + `contact_adresa`, `contact_telefon`, `contact_email_public` |
| `src/Bootstrap.php`, `src/Routes.php` | Context în container, funcții Twig, handler 404, rute publice |
| `templates/layout.twig`, `_header.twig`, `_footer.twig`, `_meniu_desktop.twig`, `_meniu_mobil.twig`, `_icon.twig`, `_document_rand.twig`, `_lista_intrari.twig`, `_galerie_grila.twig`, `_sidebar.twig` | layout + partialuri |
| `templates/landing.twig`, `sectiune/acasa.twig`, `sectiune/pagina.twig`, `sectiune/dosar.twig`, `sectiune/galerie.twig`, `sectiune/contact.twig`, `eroare.twig`, `sitemap.twig` | pagini |
| `tests/public_assets_test.php`, `public_layout_test.php`, `public_acasa_test.php`, `public_pagina_test.php`, `public_miniatura_test.php`, `public_contact_test.php`, `public_seo_test.php`, `tests/capturi.mjs` | teste |

---

### Task 1: Fundația vizuală — fonturi, logo-uri, ilustrație, `site.css`

**Files:**
- Create: `scripts/descarca_fonturi.php`, `assets/css/fonts.css`, `assets/fonts/*.woff2`, `assets/img/logo/{eu-flag,guvernul-romaniei,flag-prahova,2021-2027}.png`, `assets/img/ilustratie.svg`, `assets/img/og-default.png`, `assets/css/site.css`, `assets/js/site.js`, `tests/public_assets_test.php`
- Modify: `.htaccess` (cache pe `.svg` și `.woff2` — verifică dacă `font/woff2` e deja acolo, e; adaugă `image/svg+xml`)

**Interfaces:**
- Produces: clasele CSS folosite de task-urile următoare (numele sunt contractul): `.fp-topstrip`, `.fp-logos`, `.fp-header`, `.fp-nav`, `.fp-nav__link`, `.fp-nav .dropdown-menu`, `.fp-submenu`, `.fp-switch`, `.fp-hero`, `.fp-hero__ilustratie`, `.fp-eyebrow`, `.fp-btn`, `.fp-btn--outline`, `.fp-section--soft`, `.fp-card`, `.fp-card__icon`, `.fp-doc`, `.fp-doc__icon`, `.fp-doc__meta`, `.fp-galerie`, `.fp-galerie__item`, `.fp-lightbox`, `.fp-sidebar`, `.fp-sidebar__nav`, `.fp-contact-rapid`, `.fp-cta`, `.fp-footer`, `.fp-footer__bottom`, `.fp-prose`, `.fp-breadcrumb`, `.fp-form`.
- Folosește skill-ul `frontend-design:frontend-design` pentru CSS și SVG.

- [ ] **Step 1: Testul**

`tests/public_assets_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$root = dirname(__DIR__);
foreach (['eu-flag', 'guvernul-romaniei', 'flag-prahova', '2021-2027'] as $l) {
    ok("logo $l.png există", is_file("$root/assets/img/logo/$l.png"));
}
ok('ilustratie.svg e XML valid', is_file("$root/assets/img/ilustratie.svg") && simplexml_load_file("$root/assets/img/ilustratie.svg") !== false);
ok('og-default.png e 1200x630', is_file("$root/assets/img/og-default.png") && getimagesize("$root/assets/img/og-default.png")[0] === 1200 && getimagesize("$root/assets/img/og-default.png")[1] === 630);
$fonturi = glob("$root/assets/fonts/*.woff2") ?: [];
ok('cel puțin 4 fonturi woff2 (sans 400/700 + serif, latin + latin-ext)', count($fonturi) >= 4);
$fcss = (string) @file_get_contents("$root/assets/css/fonts.css");
ok('fonts.css declară DM Sans și DM Serif Display cu unicode-range', str_contains($fcss, "'DM Sans'") && str_contains($fcss, "'DM Serif Display'") && str_contains($fcss, 'unicode-range') && !str_contains($fcss, 'fonts.gstatic.com'));
$css = (string) @file_get_contents("$root/assets/css/site.css");
ok('site.css are tokenurile', str_contains($css, '--fp-navy: #272B5C') && str_contains($css, '--fp-blue: #1F6FC5') && str_contains($css, '--fp-accent: #EC8C16'));
foreach (['.fp-header', '.fp-nav', '.fp-hero', '.fp-eyebrow', '.fp-btn', '.fp-doc', '.fp-galerie', '.fp-footer', '.fp-prose', '.fp-sidebar', '.fp-cta'] as $c) {
    ok("site.css definește $c", str_contains($css, $c));
}
ok('site.js există', is_file("$root/assets/js/site.js"));
final_test();
```

- [ ] **Step 2: Rulează, pică** — `$PHP tests/public_assets_test.php` → FAIL pe toate.

- [ ] **Step 3: Fonturile**

`scripts/descarca_fonturi.php` (dev, rulat o singură dată; rezultatul se commit-uie):

```php
<?php
// Descarcă DM Sans (400/500/700) + DM Serif Display (400), subseturile latin și latin-ext,
// în assets/fonts/ și scrie assets/css/fonts.css cu @font-face + unicode-range.
declare(strict_types=1);
$root = dirname(__DIR__);
$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';
$url = 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=DM+Serif+Display&display=swap';
$ctx = stream_context_create(['http' => ['header' => "User-Agent: $ua\r\n"]]);
$css = file_get_contents($url, false, $ctx);
if ($css === false) { fwrite(STDERR, "Nu pot descărca CSS-ul\n"); exit(1); }
@mkdir("$root/assets/fonts", 0775, true);
$out = "/* Fonturi self-hosted (descărcate cu scripts/descarca_fonturi.php). Licență: SIL OFL 1.1 */\n";
preg_match_all('#/\*\s*(latin|latin-ext)\s*\*/\s*@font-face\s*\{(.*?)\}#s', $css, $m, PREG_SET_ORDER);
foreach ($m as [$tot, $subset, $corp]) {
    preg_match("/font-family:\s*'([^']+)'/", $corp, $fam);
    preg_match('/font-weight:\s*(\d+)/', $corp, $w);
    preg_match('/url\(([^)]+)\)/', $corp, $u);
    preg_match('/unicode-range:\s*([^;]+);/', $corp, $ur);
    $nume = strtolower(str_replace(' ', '-', $fam[1])) . "-{$w[1]}-$subset.woff2";
    $bin = file_get_contents($u[1], false, $ctx);
    if ($bin === false) { fwrite(STDERR, "Nu pot descărca $nume\n"); exit(1); }
    file_put_contents("$root/assets/fonts/$nume", $bin);
    $out .= "@font-face{font-family:'{$fam[1]}';font-style:normal;font-weight:{$w[1]};font-display:swap;src:url(../fonts/$nume) format('woff2');unicode-range:{$ur[1]};}\n";
    echo "ok $nume\n";
}
file_put_contents("$root/assets/css/fonts.css", $out);
echo "scris assets/css/fonts.css\n";
```

Rulează: `$PHP scripts/descarca_fonturi.php` → 8 fișiere (4 greutăți × 2 subseturi). Dacă rețeaua pică, oprește-te și raportează (nu inventa fonturi).

- [ ] **Step 4: Logo-uri, ilustrație, OG**

```bash
mkdir -p assets/img/logo
cp materiale/eu-flag.png assets/img/logo/eu-flag.png
cp materiale/guvernul-romaniei.png assets/img/logo/guvernul-romaniei.png
cp materiale/logo-flag-prahova.png assets/img/logo/flag-prahova.png
cp materiale/2021-2027.png assets/img/logo/2021-2027.png
```

`assets/img/ilustratie.svg` — scris de mână, `viewBox="0 0 640 480"`, fără text, fără fonturi, fără `<script>`; `role="img" aria-labelledby="t"` + `<title id="t">Pești și lacuri din Prahova</title>`. Compoziție flat, în paleta proiectului: un cerc mare `fill="#E6EEF8"` (cx 380, cy 230, r 210) ca fundal; două rânduri de dealuri (`#C9D8EE`, `#B3C7E6`) în spate; apă în două straturi de valuri (`#1F6FC5` cu `opacity=".9"`, `#272B5C`) în treimea de jos; trei pești stilizați (corp = `path` cu curbe, coadă triunghiulară, ochi cerc alb) în `#272B5C`, `#EC8C16` și alb cu contur `#1F6FC5`, orientați în direcții diferite; 4–5 cerculețe decorative mici (`stroke="#EC8C16"`/`"#fff"`, `fill="none"`) ca în template. Sub 8 KB.

`assets/img/og-default.png`: creează în scratchpad un `og.html` (1200×630, fundal gradient `#E9F0FA→#fff`, sigla `assets/img/logo/flag-prahova.png` la ~360px lățime, sub ea „Asociația FLAG Prahova" în DM Serif Display 56px `#272B5C` și „Grup de acțiune locală pentru pescuit și acvacultură — Prahova" în DM Sans 26px `#6F7390`, ilustrația în dreapta la 40% lățime), cu `<link>` la `fonts.css` prin cale absolută `file:///C:/laragon/www/flagprahova/assets/css/fonts.css`, apoi:

```bash
"C:/Program Files/Google/Chrome/Application/chrome.exe" --headless=new --disable-gpu --user-data-dir="$TEMP/fp-og-profil" --window-size=1200,630 --screenshot="C:/laragon/www/flagprahova/assets/img/og-default.png" "file:///$SCRATCH/og.html"
```

Verifică dimensiunea cu `$PHP -r 'print_r(getimagesize("assets/img/og-default.png"));'`.

- [ ] **Step 5: `site.css`**

Începe cu tokenurile (exact așa, testul le caută):

```css
:root {
  --fp-navy: #272B5C;
  --fp-navy-deep: #1B1F4A;
  --fp-blue: #1F6FC5;
  --fp-blue-soft: #E6EEF8;
  --fp-sky: #E9F0FA;
  --fp-accent: #EC8C16;
  --fp-text: #2B2F4A;
  --fp-muted: #6F7390;
  --fp-radius: 18px;
  --fp-font-serif: 'DM Serif Display', Georgia, serif;
  --fp-font-sans: 'DM Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
  --bs-primary: var(--fp-blue);
  --bs-link-color: var(--fp-blue);
  --bs-link-hover-color: var(--fp-navy);
  --bs-body-color: var(--fp-text);
  --bs-body-font-family: var(--fp-font-sans);
}
```

Apoi, în ordinea asta, câte un bloc comentat: bază (`body` 17px/1.7, `h1–h3` serif `--fp-navy`, `h1` 2.4–3rem fluid cu `clamp`), `.fp-topstrip` (bandă albă subțire, `.fp-logos` flex cu logo-urile la înălțime 40px, textul „Cofinanțat…" 13px bold `--fp-navy` lângă steag; pe mobil se ascund Guvernul + 2021-2027, rămân steagul + textul), `.fp-header` (alb, `position: sticky; top: 0`, umbră fină `0 2px 12px rgba(39,43,92,.08)`), `.fp-nav` (linkuri 15px 500 uppercase-less, `--fp-navy`, activ/hover `--fp-blue` cu linie 3px `--fp-accent` sub, `border-radius: 999px` pe linkul activ NU — doar linia), `.fp-nav .dropdown-menu` (fără margini, `border-radius: 14px`, umbră, min-width 260px; `.dropdown-item` 15px; deschidere pe `:hover`/`:focus-within` la `≥ 992px` prin `.fp-nav .dropdown:hover > .dropdown-menu { display:block }`), `.fp-submenu` (nivel 2–3: `position:absolute; top:0; left:100%` la `:hover`/`:focus-within`; dacă nu încape, `.fp-submenu--stanga` pune `right:100%`), `.fp-switch` (pastilă `--fp-blue-soft`, două `a`, cea activă `--fp-navy` pe alb, `border-radius: 999px`), `.fp-hero` (gradient `linear-gradient(180deg, var(--fp-sky), #fff)`, două coloane la `≥ 992px`, `.fp-hero__ilustratie` max 520px), `.fp-eyebrow` (inline-block, `background:#fff`, `padding: 6px 14px`, `border-radius: 999px`, 12px/700 uppercase letter-spacing .08em `--fp-blue`; varianta `.fp-eyebrow--soft` pe `--fp-blue-soft`), `.fp-btn` (`--fp-navy` pe alb, `border-radius: 999px`, `padding: .7rem 1.5rem`, hover `--fp-blue`; `.fp-btn--outline` contur `--fp-navy`, hover umplut; `.fp-btn--accent` `--fp-accent`), `.fp-section` (`padding: 4.5rem 0`; `.fp-section--soft` fundal `--fp-blue-soft`), `.fp-card` (alb, `border-radius: var(--fp-radius)`, umbră `0 10px 30px rgba(39,43,92,.08)`, hover `translateY(-3px)`; `.fp-card__icon` cerc 56px `--fp-blue-soft` cu SVG `--fp-blue`), `.fp-doc` (rând: `.fp-doc__icon` pătrat 44px rotunjit cu culoarea după extensie — `data-ext="pdf"` roșu estompat `#F6E4E1`/`#B3261E`, `doc/docx` albastru, `xls/xlsx` verde, altfel `--fp-blue-soft` — titlu 16px 500, `.fp-doc__meta` 13px `--fp-muted`, separatoare 1px `#E8EBF3`), `.fp-galerie` (grid `repeat(auto-fill, minmax(180px,1fr))`, `.fp-galerie__item` `aspect-ratio: 4/3`, `object-fit: cover`, `border-radius: 12px`), `.fp-lightbox` (overlay fix `rgba(27,31,74,.94)`, imagine `max-height: 90vh`, butoane prev/next/close albe rotunde; `[hidden]{display:none}`), `.fp-sidebar` (`position: sticky; top: 96px` la `≥ 992px`; `.fp-sidebar__nav` listă verticală cu activ `--fp-blue` bold, sub-nivelurile indentate; `.fp-contact-rapid` card `--fp-blue-soft`), `.fp-cta` (gradient `--fp-sky→#fff`, centrat, ilustrația mică jos), `.fp-footer` (`--fp-navy-deep`, text `#A8AABE`, linkuri albe, `h4` 16px alb, logo-urile pe fundal alb rotunjit în `.fp-footer__logos`; `.fp-footer__bottom` bordură sus `rgba(255,255,255,.1)`, 13px), `.fp-prose` (conținut editor: `max-width: 900px`, `img { max-width:100%; height:auto; border-radius:12px }`, `iframe { max-width:100%; aspect-ratio:16/9; height:auto }`, `h2/h3` serif, `blockquote` bordură stânga `--fp-accent`, tabele nu există), `.fp-breadcrumb` (13px, `--fp-muted`, separator „›"), `.fp-form` (`.form-control` `border-radius: 12px`, focus `--fp-blue`), acordeon galerii (`.fp-acordeon` cu `details > summary` stilizat ca rând cu chevron). `@media (max-width: 991.98px)`: meniul desktop e ascuns, `.fp-hero` o coloană cu ilustrația sub text la 60% lățime, `.fp-section` padding 3rem. Fără `!important`. Fără `min-width` pe `body`.

- [ ] **Step 6: `site.js`**

```js
// FLAG Prahova — JS public: submeniuri pe touch, lightbox galerie. Bootstrap face offcanvas + dropdown.
(function () {
  'use strict';
  // Pe ecrane cu touch, primul tap pe un părinte cu submeniu îl deschide, al doilea urmează linkul.
  document.querySelectorAll('.fp-nav .fp-has-sub > a').forEach(function (a) {
    a.addEventListener('touchstart', function (ev) {
      var li = a.parentElement;
      if (!li.classList.contains('fp-open')) {
        ev.preventDefault();
        li.parentElement.querySelectorAll('.fp-open').forEach(function (o) { o.classList.remove('fp-open'); });
        li.classList.add('fp-open');
      }
    }, { passive: false });
  });
  // Submeniurile care ar ieși din ecran se deschid spre stânga.
  document.querySelectorAll('.fp-nav .fp-submenu').forEach(function (sub) {
    var r = sub.parentElement.getBoundingClientRect();
    if (r.right + 280 > window.innerWidth) { sub.classList.add('fp-submenu--stanga'); }
  });
  // Lightbox: orice <a class="fp-galerie__item" data-full="..."> dintr-o .fp-galerie.
  var box = document.querySelector('.fp-lightbox');
  if (!box) { return; }
  var img = box.querySelector('img'), cap = box.querySelector('.fp-lightbox__legenda');
  var lista = [], idx = 0;
  function arata(i) {
    idx = (i + lista.length) % lista.length;
    img.src = lista[idx].getAttribute('data-full');
    img.alt = lista[idx].getAttribute('data-legenda') || '';
    cap.textContent = img.alt;
    box.hidden = false; document.body.style.overflow = 'hidden';
  }
  function inchide() { box.hidden = true; document.body.style.overflow = ''; }
  document.querySelectorAll('.fp-galerie').forEach(function (g) {
    var items = Array.prototype.slice.call(g.querySelectorAll('.fp-galerie__item'));
    items.forEach(function (a, i) {
      a.addEventListener('click', function (ev) { ev.preventDefault(); lista = items; arata(i); });
    });
  });
  box.querySelector('[data-inchide]').addEventListener('click', inchide);
  box.querySelector('[data-prev]').addEventListener('click', function () { arata(idx - 1); });
  box.querySelector('[data-next]').addEventListener('click', function () { arata(idx + 1); });
  box.addEventListener('click', function (ev) { if (ev.target === box) { inchide(); } });
  document.addEventListener('keydown', function (ev) {
    if (box.hidden) { return; }
    if (ev.key === 'Escape') { inchide(); }
    if (ev.key === 'ArrowLeft') { arata(idx - 1); }
    if (ev.key === 'ArrowRight') { arata(idx + 1); }
  });
})();
```

- [ ] **Step 7: `.htaccess`** — în blocul `mod_expires` adaugă `ExpiresByType image/svg+xml "access plus 30 days"`.

- [ ] **Step 8: Rulează testul** — `$PHP tests/public_assets_test.php` → OK.

- [ ] **Step 9: Commit**

```bash
git add assets scripts .htaccess tests/public_assets_test.php docs/superpowers/specs/2026-09-18-flagprahova-site-nou-design.md docs/superpowers/plans/2026-09-18-plan-3-sit-public.md
git commit -m "M3: fundatie vizuala — fonturi self-hosted, logo-uri, ilustratie, site.css"
```

---

### Task 2: `arborePublic()`, `Public\Context`, layout + header/meniu/footer, landing, 404

**Files:**
- Create: `src/Public/Context.php`, `src/Public/PaginiController.php` (doar `landing()` acum), `src/Public/NotFoundHandler.php`, `templates/_header.twig`, `templates/_footer.twig`, `templates/_meniu_desktop.twig`, `templates/_meniu_mobil.twig`, `templates/_icon.twig`, `templates/landing.twig`, `templates/eroare.twig`, `tests/public_layout_test.php`
- Modify: `src/Meniu/Repository.php` (+ `arborePublic`), `src/Bootstrap.php` (container `context`, funcții Twig, handler 404/405), `src/Routes.php` (`/`, `/{perioada}` → 301, `/{perioada}/` → `acasa` provizoriu 200 cu layout), `templates/layout.twig` (rescris)
- Delete: `templates/home.twig`

**Interfaces:**
- Produces `App\Meniu\Repository::arborePublic(int $sectiuneId): array` — ca `arbore($id, true)` dar SELECT-ul face `LEFT JOIN fisiere f ON f.id = m.fisier_id` și întoarce în plus `fisier_cale`, `fisier_marime`, `fisier_mime`, `modificat_la`, `sablon`; un nod cu `vizibil=0` nu apare și nici descendenții lui (pentru că părintele lipsește din `$noduri`, copiii ajung… ATENȚIE: în `arbore()` copiii orfani devin rădăcini — aici trebuie EXCLUȘI: construiește arborele din toate rândurile, apoi elimină recursiv nodurile cu `vizibil=0`).
- Produces `App\Public\Context`:
  - `__construct(Meniu\Repository $meniu, Setari\Repository $setari, array $settings)`
  - `sectiune(string $slug): ?array` — rândul din `sectiuni` sau null.
  - `arbore(array $sectiune): array` — `arborePublic` decorat: fiecare nod primește `href` (string) și `extern` (bool). Reguli: `document` cu `fisier_cale` → `{base}/fisiere/{cale}`; `document` fără fișier → `href = ''` (se randează ca text, nu link); `link` → `url`, `extern = true`; altfel `{base}/{sectiune.slug}/{slug}`.
  - `href(array $rand, string $slugSectiune): string` — aceeași regulă pentru un rând brut din `meniu` (are `fisier_id`, nu `fisier_cale`: caută prin `Fisiere\Repository::gaseste`). Ca să nu duplici, `Context` primește și `Fisiere\Repository` în constructor: `__construct(Meniu\Repository $meniu, Fisiere\Repository $fisiere, Setari\Repository $setari, array $settings)`.
  - `gaseste(array $arbore, string $slug): ?array` — `['nod' => nodul, 'stramosi' => [nod nivel 1, …, părintele direct]]` prin căutare recursivă; null dacă nu e în arbore (invizibil sau strămoș invizibil).
  - `variabile(?array $sectiune, string $canonicalPath, array $extra = []): array` — întoarce `$extra + ['sectiuni' => toate secțiunile, 'sectiune' => $sectiune, 'arbore' => $sectiune ? $this->arbore($sectiune) : [], 'cealalta' => cealaltă secțiune (sau null pe landing), 'setari' => $this->setari->toate(), 'canonical_path' => $canonicalPath, 'activ' => [] ]`. `activ` = lista id-urilor de marcat în meniu (nodul curent + strămoșii) — task-ul 4 o umple prin `$extra`.
- Produces funcții Twig (în `Bootstrap::extinde`): `marime(int $bytes): string` („1,2 MB", „340 KB"), `ext(string $cale): string` (extensia lowercase), `mini(string $cale, int $latime): string` (`{base}/fisiere/mini/{latime}/{cale cu extensia înlocuită cu .webp}`), `url_public(string $cale): string` (`app.url` + cale) — folosite în task-urile 3–7.
- Produces `templates/layout.twig` cu blocurile: `title`, `description`, `meta_robots` (implicit `index,follow`), `og_type` (implicit `website`), `og_image` (implicit `/assets/img/og-default.png`), `jsonld` (gol), `content`; variabila `canonical_path` din Context.
- Produces `templates/_icon.twig` cu `{% set nume = nume|default('document') %}` și SVG-uri inline 24×24 (`stroke="currentColor"`, `fill="none"`, `stroke-width="1.8"`) pentru: `dosar` (folder), `pagina` (foaie cu linii), `document` (foaie cu colț îndoit), `galerie` (dreptunghi cu munte+soare), `link` (săgeată în pătrat), `noutati` (ziar), `contact` (plic), `telefon`, `pin` (marker), `chevron` (›), `meniu` (hamburger), `inchide` (×), `stanga`, `dreapta`. Se include cu `{% include '_icon.twig' with {'nume': 'dosar'} only %}`.

- [ ] **Step 1: Testul**

`tests/public_layout_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$s1 = $pdo->query("SELECT * FROM sectiuni WHERE slug='2021-2027'")->fetch();
$marca = 'Lay' . bin2hex(random_bytes(3));
$ids = []; $fid = null;
try {
    // arbore temporar pe 3 niveluri + un nod invizibil cu copil vizibil
    $ins = $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, fisier_id, url, vizibil) VALUES (:s, :p, :o, :t, :sl, :tip, :f, :u, :v)');
    $adauga = function (?int $p, string $t, string $tip, int $viz = 1, ?int $f = null, ?string $u = null) use ($ins, $pdo, $s1, &$ids): int {
        $ins->execute(['s' => $s1['id'], 'p' => $p, 'o' => 999, 't' => $t, 'sl' => slugify($t), 'tip' => $tip, 'f' => $f, 'u' => $u, 'v' => $viz]);
        $id = (int) $pdo->lastInsertId(); $ids[] = $id; return $id;
    };
    $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES ("Raport.pdf", :c, "application/pdf", 123456)')->execute(['c' => "2026/09/raport-$marca.pdf"]);
    $fid = (int) $pdo->lastInsertId();
    $n1 = $adauga(null, "Dosar $marca", 'dosar');
    $n2 = $adauga($n1, "Subdosar $marca", 'dosar');
    $n3 = $adauga($n2, "Raport $marca", 'document', 1, $fid);
    $n4 = $adauga($n1, "Extern $marca", 'link', 1, null, 'https://example.com/x');
    $ascuns = $adauga(null, "Ascuns $marca", 'dosar', 0);
    $orfan  = $adauga($ascuns, "Orfan $marca", 'dosar', 1);

    $r = cerere('GET', '/');
    $c = corp($r);
    ok('GET / => 200', $r->getStatusCode() === 200);
    ok('  ambele perioade', str_contains($c, 'FLAG Prahova 2021-2027') && str_contains($c, 'FLAG Prahova 2014-2020'));
    ok('  linkuri către secțiuni', str_contains($c, 'href="/2021-2027/"') && str_contains($c, 'href="/2014-2020/"'));
    ok('  canonical + OG + twitter', str_contains($c, '<link rel="canonical" href="http://flagprahova.test/">') && str_contains($c, 'property="og:title"') && str_contains($c, 'property="og:image" content="http://flagprahova.test/assets/img/og-default.png"') && str_contains($c, 'name="twitter:card"'));
    ok('  un singur h1', substr_count($c, '<h1') === 1);
    ok('  logo-uri + text cofinanțare', str_contains($c, 'assets/img/logo/eu-flag.png') && str_contains($c, 'Cofinanțat de Uniunea Europeană') && str_contains($c, 'assets/img/logo/flag-prahova.png'));
    ok('  JSON-LD Organization', str_contains($c, '"@type": "Organization"') || str_contains($c, '"@type":"Organization"'));
    ok('  CSS/JS locale, nimic extern', str_contains($c, '/assets/css/site.css') && str_contains($c, '/assets/js/site.js') && !str_contains($c, 'googleapis') && !str_contains($c, 'cdn.'));
    ok('  fără link către admin', !str_contains($c, '/admin'));

    $r = cerere('GET', '/2021-2027');
    ok('GET /2021-2027 (fără slash) => 301 la /2021-2027/', $r->getStatusCode() === 301 && $r->getHeaderLine('Location') === '/2021-2027/');

    $r = cerere('GET', '/2021-2027/');
    $c = corp($r);
    ok('GET /2021-2027/ => 200', $r->getStatusCode() === 200);
    ok('  meniul pe 3 niveluri (desktop)', str_contains($c, "Dosar $marca") && str_contains($c, "Subdosar $marca") && str_contains($c, "Raport $marca"));
    ok('  documentul linkează direct la fișier', str_contains($c, "href=\"/fisiere/2026/09/raport-$marca.pdf\""));
    ok('  linkul extern are noopener', preg_match('#<a[^>]+href="https://example\.com/x"[^>]+rel="noopener[^"]*"#', $c) === 1);
    ok('  invizibilul și orfanul lipsesc', !str_contains($c, "Ascuns $marca") && !str_contains($c, "Orfan $marca"));
    ok('  comutatorul duce la cealaltă perioadă', str_contains($c, 'href="/2014-2020/"'));
    ok('  sigla 2021-2027 în bandă', str_contains($c, 'assets/img/logo/2021-2027.png'));
    ok('  meniul mobil (offcanvas) există', str_contains($c, 'class="offcanvas') && str_contains($c, "<details"));

    $r = cerere('GET', '/2014-2020/');
    ok('GET /2014-2020/ nu arată sigla 2021-2027', $r->getStatusCode() === 200 && !str_contains(corp($r), 'assets/img/logo/2021-2027.png'));

    $r = cerere('GET', '/1999-2000/');
    ok('secțiune inexistentă => 404 cu layout + noindex', $r->getStatusCode() === 404 && str_contains(corp($r), 'fp-header') && str_contains(corp($r), 'noindex'));
    $r = cerere('GET', '/2021-2027/nu-exista-' . strtolower($marca));
    ok('slug inexistent => 404 cu meniul secțiunii', $r->getStatusCode() === 404 && str_contains(corp($r), "Dosar $marca"));
    $r = cerere('GET', '/wp-content/uploads/x.pdf');
    ok('cale WP => 404', $r->getStatusCode() === 404);
} finally {
    if ($ids) { $pdo->exec('DELETE FROM meniu WHERE id IN (' . implode(',', $ids) . ')'); }
    if ($fid) { $pdo->exec("DELETE FROM fisiere WHERE id = $fid"); }
}
final_test();
```

Notă: `slug inexistent` — rutele `/2021-2027/{slug}` vin în Task 4; până atunci Slim dă 404 prin handler-ul nostru, deci testul trece deja dacă handler-ul detectează secțiunea din URL.

- [ ] **Step 2: Rulează, pică.**

- [ ] **Step 3: `arborePublic()`** în `src/Meniu/Repository.php`, după `arbore()`:

```php
    /**
     * Arborele pentru situl public: doar intrări vizibile ai căror strămoși sunt
     * toți vizibili, cu datele fișierului atașat (pentru linkuri directe).
     * Spre deosebire de `arbore()`, copiii unui nod invizibil NU devin rădăcini.
     */
    public function arborePublic(int $sectiuneId): array
    {
        $st = $this->pdo->prepare('SELECT m.id, m.parent_id, m.ordine, m.titlu, m.slug, m.tip, m.url, m.sablon, m.vizibil, m.modificat_la,
                f.cale AS fisier_cale, f.marime AS fisier_marime, f.mime AS fisier_mime
            FROM meniu m LEFT JOIN fisiere f ON f.id = m.fisier_id
            WHERE m.sectiune_id = :s ORDER BY m.ordine, m.id');
        $st->execute(['s' => $sectiuneId]);
        $noduri = [];
        foreach ($st->fetchAll() as $r) { $r['copii'] = []; $noduri[(int) $r['id']] = $r; }
        $radacini = [];
        foreach ($noduri as &$n) {
            $p = $n['parent_id'] === null ? null : (int) $n['parent_id'];
            if ($p !== null && isset($noduri[$p])) { $noduri[$p]['copii'][] = &$n; } else { $radacini[] = &$n; }
        }
        unset($n);
        $curata = function (array $lista) use (&$curata): array {
            $out = [];
            foreach ($lista as $nod) {
                if ((int) $nod['vizibil'] !== 1) { continue; }
                $nod['copii'] = $curata($nod['copii']);
                $out[] = $nod;
            }
            return $out;
        };
        return $curata($radacini);
    }
```

- [ ] **Step 4: `Context`, `NotFoundHandler`, `PaginiController::landing`, Bootstrap, rute**

`src/Public/Context.php`:

```php
<?php
declare(strict_types=1);

namespace App\Public;

use App\Fisiere\Repository as Fisiere;
use App\Meniu\Repository as Meniu;
use App\Setari\Repository as Setari;

/** Datele comune ale layout-ului public, calculate o dată per cerere. */
final class Context
{
    private ?array $sectiuni = null;

    public function __construct(private Meniu $meniu, private Fisiere $fisiere, private Setari $setari, private array $settings) {}

    public function sectiuni(): array
    {
        return $this->sectiuni ??= $this->meniu->sectiuni();
    }

    public function sectiune(string $slug): ?array
    {
        foreach ($this->sectiuni() as $s) { if ($s['slug'] === $slug) { return $s; } }
        return null;
    }

    public function base(): string
    {
        return (string) $this->settings['app']['base_path'];
    }

    /** Arborele vizibil, cu `href` și `extern` pe fiecare nod. */
    public function arbore(array $sectiune): array
    {
        $noduri = $this->meniu->arborePublic((int) $sectiune['id']);
        $decoreaza = function (array $lista) use (&$decoreaza, $sectiune): array {
            foreach ($lista as &$n) {
                $n['extern'] = $n['tip'] === 'link';
                $n['href'] = match ($n['tip']) {
                    'document' => $n['fisier_cale'] !== null ? $this->base() . $this->settings['upload']['url'] . '/' . $n['fisier_cale'] : '',
                    'link'     => (string) $n['url'],
                    default    => $this->base() . '/' . $sectiune['slug'] . '/' . $n['slug'],
                };
                $n['copii'] = $decoreaza($n['copii']);
            }
            unset($n);
            return $lista;
        };
        return $decoreaza($noduri);
    }

    /** Aceeași regulă de link pentru un rând brut din `meniu`. */
    public function href(array $rand, string $slugSectiune): string
    {
        if ($rand['tip'] === 'link') { return (string) $rand['url']; }
        if ($rand['tip'] === 'document') {
            $f = $rand['fisier_id'] ? $this->fisiere->gaseste((int) $rand['fisier_id']) : null;
            return $f ? $this->base() . $this->settings['upload']['url'] . '/' . $f['cale'] : '';
        }
        return $this->base() . '/' . $slugSectiune . '/' . $rand['slug'];
    }

    /** @return array{nod: array, stramosi: array}|null */
    public function gaseste(array $arbore, string $slug, array $stramosi = []): ?array
    {
        foreach ($arbore as $n) {
            if ($n['slug'] === $slug) { return ['nod' => $n, 'stramosi' => $stramosi]; }
            $g = $this->gaseste($n['copii'], $slug, [...$stramosi, $n]);
            if ($g !== null) { return $g; }
        }
        return null;
    }

    public function variabile(?array $sectiune, string $canonicalPath, array $extra = []): array
    {
        $cealalta = null;
        foreach ($this->sectiuni() as $s) {
            if ($sectiune !== null && (int) $s['id'] !== (int) $sectiune['id']) { $cealalta = $s; }
        }
        return $extra + [
            'sectiuni'       => $this->sectiuni(),
            'sectiune'       => $sectiune,
            'arbore'         => $sectiune ? $this->arbore($sectiune) : [],
            'cealalta'       => $cealalta,
            'setari'         => $this->setari->toate(),
            'canonical_path' => $canonicalPath,
            'activ'          => [],
        ];
    }
}
```

`src/Public/PaginiController.php` (versiunea din acest task; Task 3 și 4 adaugă metode):

```php
<?php
declare(strict_types=1);

namespace App\Public;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

final class PaginiController
{
    private Context $ctx;

    public function __construct(private Twig $twig, private array $container)
    {
        $this->ctx = $container['context'];
    }

    public function landing(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'landing.twig', $this->ctx->variabile(null, '/'));
    }

    /** Redirect 301 de la /{perioada} la /{perioada}/ (o singură formă canonică). */
    public function slash(Request $request, Response $response, array $args): Response
    {
        return $response->withHeader('Location', $this->ctx->base() . '/' . $args['perioada'] . '/')->withStatus(301);
    }

    public function acasa(Request $request, Response $response, array $args): Response
    {
        $s = $this->ctx->sectiune($args['perioada']) ?? throw new HttpNotFoundException($request);
        return $this->twig->render($response, 'sectiune/acasa.twig', $this->ctx->variabile($s, '/' . $s['slug'] . '/'));
    }
}
```

`src/Public/NotFoundHandler.php`:

```php
<?php
declare(strict_types=1);

namespace App\Public;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Views\Twig;
use Throwable;

/** 404/405 cu layout-ul sitului; dacă primul segment e o secțiune, cu meniul ei. */
final class NotFoundHandler
{
    public function __construct(private Twig $twig, private Context $ctx, private ResponseFactoryInterface $factory) {}

    public function __invoke(Request $request, Throwable $e, bool $afiseaza, bool $log, bool $logDetalii): Response
    {
        $cale = $request->getUri()->getPath();
        $base = $this->ctx->base();
        if ($base !== '' && str_starts_with($cale, $base)) { $cale = substr($cale, strlen($base)); }
        $seg = explode('/', trim($cale, '/'))[0] ?? '';
        $sectiune = $seg !== '' ? $this->ctx->sectiune($seg) : null;
        $status = $e instanceof HttpMethodNotAllowedException ? 405 : 404;
        $response = $this->factory->createResponse($status);
        return $this->twig->render($response, 'eroare.twig', $this->ctx->variabile($sectiune, $cale, ['status' => $status]));
    }
}
```

În `Bootstrap::extinde()` adaugă după `parola_throttle`:

```php
        $container['context'] = new PublicCtx\Context($container['meniu'], $container['fisiere'], $container['setari'], $container['settings']);
```

(`Public` e cuvânt rezervat în PHP ca nume de namespace? NU — `namespace App\Public` e valid; dar `use App\Public\Context` e valid și el. Folosește direct `new \App\Public\Context(...)` fără alias.)

Funcțiile Twig, tot în `extinde()`:

```php
        $base = (string) $container['settings']['app']['base_path'];
        $env->addFunction(new \Twig\TwigFunction('marime', static function (int|string|null $b): string {
            $b = (int) $b;
            if ($b >= 1048576) { return number_format($b / 1048576, 1, ',', '.') . ' MB'; }
            if ($b >= 1024) { return (string) round($b / 1024) . ' KB'; }
            return $b . ' B';
        }));
        $env->addFunction(new \Twig\TwigFunction('ext', static fn(?string $cale): string => strtolower(pathinfo((string) $cale, PATHINFO_EXTENSION))));
        $env->addFunction(new \Twig\TwigFunction('mini', static fn(string $cale, int $latime): string => $base . '/fisiere/mini/' . $latime . '/' . preg_replace('/\.[^.\/]+$/', '.webp', $cale)));
        $env->addFunction(new \Twig\TwigFunction('url_public', static fn(string $cale): string => $container['settings']['app']['url'] . $cale));
```

În `Bootstrap::create()`, înlocuiește linia `addErrorMiddleware` cu:

```php
        $errors = $app->addErrorMiddleware((bool) $settings['app']['debug'], true, true);
        $notFound = new Public\NotFoundHandler($twig, $container['context'], $app->getResponseFactory());
        $errors->setErrorHandler(\Slim\Exception\HttpNotFoundException::class, $notFound);
        $errors->setErrorHandler(\Slim\Exception\HttpMethodNotAllowedException::class, $notFound);
```

(`$container['context']` există deja: `extinde()` e apelat înainte.)

În `Routes.php`, înlocuiește ruta `/` și adaugă:

```php
    $pc = fn() => new \App\Public\PaginiController($twig, $container);
    $app->get('/', fn($rq, $rs) => $pc()->landing($rq, $rs))->setName('home');
    $app->get('/{perioada:[0-9]{4}-[0-9]{4}}',  fn($rq, $rs, $a) => $pc()->slash($rq, $rs, $a));
    $app->get('/{perioada:[0-9]{4}-[0-9]{4}}/', fn($rq, $rs, $a) => $pc()->acasa($rq, $rs, $a));
```

Pune-le DUPĂ grupul admin (ordinea nu contează pentru FastRoute, dar rămâne lizibil).

- [ ] **Step 5: Template-urile**

`templates/layout.twig`:

```twig
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {% set canonical = app.url ~ canonical_path %}
    {% set og_img = app.url ~ block('og_image') %}
    <title>{% block title %}Asociația FLAG Prahova{% endblock %}</title>
    <meta name="description" content="{% block description %}{{ setari.landing_text|default('') }}{% endblock %}">
    <link rel="canonical" href="{{ canonical }}">
    <meta name="robots" content="{% block meta_robots %}index,follow,max-image-preview:large{% endblock %}">
    <meta name="theme-color" content="#272B5C">
    <meta property="og:type" content="{% block og_type %}website{% endblock %}">
    <meta property="og:site_name" content="Asociația FLAG Prahova">
    <meta property="og:locale" content="ro_RO">
    <meta property="og:title" content="{{ block('title') }}">
    <meta property="og:description" content="{{ block('description') }}">
    <meta property="og:url" content="{{ canonical }}">
    <meta property="og:image" content="{{ og_img }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ block('title') }}">
    <meta name="twitter:description" content="{{ block('description') }}">
    <meta name="twitter:image" content="{{ og_img }}">
    {% block og_image %}/assets/img/og-default.png{% endblock %}{# doar valoarea; nu e afișat #}
    <link rel="icon" href="{{ base }}/assets/img/logo/flag-prahova.png">
    <link rel="stylesheet" href="{{ base }}/assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="{{ base }}/assets/css/fonts.css">
    <link rel="stylesheet" href="{{ base }}/assets/css/site.css">
    {% block jsonld %}{% endblock %}
</head>
<body>
{% include '_header.twig' %}
<main id="continut">
{% block content %}{% endblock %}
</main>
{% include '_footer.twig' %}
<div class="fp-lightbox" hidden>
    <button type="button" class="fp-lightbox__btn fp-lightbox__inchide" data-inchide aria-label="Închide">{% include '_icon.twig' with {'nume': 'inchide'} only %}</button>
    <button type="button" class="fp-lightbox__btn fp-lightbox__prev" data-prev aria-label="Anterioara">{% include '_icon.twig' with {'nume': 'stanga'} only %}</button>
    <img src="" alt="">
    <button type="button" class="fp-lightbox__btn fp-lightbox__next" data-next aria-label="Următoarea">{% include '_icon.twig' with {'nume': 'dreapta'} only %}</button>
    <p class="fp-lightbox__legenda"></p>
</div>
<script src="{{ base }}/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="{{ base }}/assets/js/site.js"></script>
</body>
</html>
```

ATENȚIE la trucul `{% block og_image %}`: în Twig, `block('og_image')` din `<head>` funcționează doar dacă blocul e definit; definiția de mai sus s-ar RANDA și ea în pagină. Corect: definește-l ca bloc gol de conținut vizual și folosește o VARIABILĂ: în layout `{% set og_image = og_image|default('/assets/img/og-default.png') %}` și `og_img = app.url ~ og_image`; paginile care vor altă imagine pasează `og_image` din controller. Elimină linia cu `{% block og_image %}`.

`templates/_header.twig`:

```twig
{% set acasa_href = sectiune ? base ~ '/' ~ sectiune.slug ~ '/' : base ~ '/' %}
<a class="visually-hidden-focusable" href="#continut">Sari la conținut</a>
<div class="fp-topstrip">
    <div class="container fp-logos">
        <span class="fp-logos__eu"><img src="{{ base }}/assets/img/logo/eu-flag.png" alt="Steagul Uniunii Europene" height="40" width="61"><span>Cofinanțat de<br>Uniunea Europeană</span></span>
        <img class="d-none d-sm-block" src="{{ base }}/assets/img/logo/guvernul-romaniei.png" alt="Guvernul României" height="44" width="45">
        {% if sectiune is null or sectiune.slug == '2021-2027' %}
            <img class="d-none d-sm-block ms-auto" src="{{ base }}/assets/img/logo/2021-2027.png" alt="FLAG Prahova 2021-2027 — Susține inițiativa ta!" height="44" width="66">
        {% endif %}
    </div>
</div>
<header class="fp-header">
    <div class="container d-flex align-items-center gap-3">
        <a class="fp-brand" href="{{ acasa_href }}"><img src="{{ base }}/assets/img/logo/flag-prahova.png" alt="Asociația FLAG Prahova" height="52" width="128"></a>
        {% if sectiune %}
            <nav class="fp-nav d-none d-lg-block ms-auto" aria-label="Meniu principal">
                {% include '_meniu_desktop.twig' with {'noduri': arbore, 'nivel': 1, 'activ': activ} only %}
            </nav>
            <div class="fp-switch ms-lg-3 ms-auto" role="group" aria-label="Perioada">
                {% for s in sectiuni %}
                    <a class="fp-switch__opt{% if s.id == sectiune.id %} is-activ{% endif %}" href="{{ base }}/{{ s.slug }}/"{% if s.id == sectiune.id %} aria-current="page"{% endif %}>{{ s.slug }}</a>
                {% endfor %}
            </div>
            <button class="fp-burger d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#fp-mobil" aria-label="Deschide meniul">{% include '_icon.twig' with {'nume': 'meniu'} only %}</button>
        {% else %}
            <nav class="ms-auto d-flex gap-2" aria-label="Perioade">
                {% for s in sectiuni %}<a class="fp-btn fp-btn--outline" href="{{ base }}/{{ s.slug }}/">{{ s.slug }}</a>{% endfor %}
            </nav>
        {% endif %}
    </div>
</header>
{% if sectiune %}
<div class="offcanvas offcanvas-end fp-mobil" tabindex="-1" id="fp-mobil" aria-labelledby="fp-mobil-titlu">
    <div class="offcanvas-header">
        <span class="h5 mb-0" id="fp-mobil-titlu">{{ sectiune.titlu }}</span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Închide"></button>
    </div>
    <div class="offcanvas-body">
        <a class="fp-mobil__link" href="{{ acasa_href }}">Acasă</a>
        {% include '_meniu_mobil.twig' with {'noduri': arbore, 'activ': activ} only %}
        <p class="mt-4 small text-muted">Cealaltă perioadă:</p>
        {% if cealalta %}<a class="fp-btn fp-btn--outline" href="{{ base }}/{{ cealalta.slug }}/">{{ cealalta.titlu }}</a>{% endif %}
    </div>
</div>
{% endif %}
```

`templates/_meniu_desktop.twig` (recursiv; `nivel` 1 = bară, 2 = dropdown, ≥3 = submeniu lateral):

```twig
<ul class="{{ nivel == 1 ? 'fp-nav__lista' : (nivel == 2 ? 'dropdown-menu' : 'dropdown-menu fp-submenu') }}">
{% for n in noduri %}
    {% set are = n.copii|length > 0 %}
    <li class="{{ nivel == 1 ? 'fp-nav__item' : '' }}{% if are %} {{ nivel == 1 ? 'dropdown' : 'fp-has-sub' }}{% endif %}">
        {% if n.href == '' %}
            <span class="{{ nivel == 1 ? 'fp-nav__link' : 'dropdown-item' }} is-inactiv">{{ n.titlu }}</span>
        {% else %}
            <a class="{{ nivel == 1 ? 'fp-nav__link' : 'dropdown-item' }}{% if n.id in activ %} is-activ{% endif %}{% if are %} fp-has-sub__link{% endif %}" href="{{ n.href }}"{% if n.extern %} target="_blank" rel="noopener noreferrer"{% endif %}{% if n.id in activ %} aria-current="page"{% endif %}>{{ n.titlu }}{% if are %}{% include '_icon.twig' with {'nume': 'chevron'} only %}{% endif %}</a>
        {% endif %}
        {% if are %}{% include '_meniu_desktop.twig' with {'noduri': n.copii, 'nivel': nivel + 1, 'activ': activ} only %}{% endif %}
    </li>
{% endfor %}
</ul>
```

Nivelul 1 cu copii NU folosește `data-bs-toggle="dropdown"` (deschiderea e pe hover/focus din CSS, ca linkul părinte să rămână navigabil); pe touch o face `site.js`. Pentru asta, ÎN CSS: `.fp-nav .dropdown:hover > .dropdown-menu, .fp-nav .dropdown:focus-within > .dropdown-menu, .fp-nav .fp-open > .dropdown-menu { display:block }` și la fel pentru `.fp-has-sub`.

`templates/_meniu_mobil.twig`:

```twig
<ul class="fp-mobil__lista">
{% for n in noduri %}
    <li>
    {% if n.copii|length > 0 %}
        <details{% if n.id in activ %} open{% endif %}>
            <summary>{{ n.titlu }}</summary>
            {% if n.href != '' and n.tip != 'dosar' %}<a class="fp-mobil__link fp-mobil__link--parinte" href="{{ n.href }}">Deschide „{{ n.titlu }}"</a>{% endif %}
            {% include '_meniu_mobil.twig' with {'noduri': n.copii, 'activ': activ} only %}
        </details>
    {% elseif n.href == '' %}
        <span class="fp-mobil__link is-inactiv">{{ n.titlu }}</span>
    {% else %}
        <a class="fp-mobil__link{% if n.id in activ %} is-activ{% endif %}" href="{{ n.href }}"{% if n.extern %} target="_blank" rel="noopener noreferrer"{% endif %}>{{ n.titlu }}</a>
    {% endif %}
    </li>
{% endfor %}
</ul>
```

`templates/_footer.twig`:

```twig
<footer class="fp-footer">
    <div class="container">
        <div class="row g-4 py-5">
            <div class="col-lg-4">
                <img src="{{ base }}/assets/img/logo/flag-prahova.png" alt="" height="48" width="118" class="fp-footer__sigla">
                <p class="mt-3">{{ setari.landing_text|default('') }}</p>
            </div>
            <div class="col-6 col-lg-4">
                <h4>{{ sectiune ? sectiune.titlu : 'Perioade' }}</h4>
                <ul class="list-unstyled">
                    {% if sectiune %}
                        {% for n in arbore %}{% if n.href != '' %}<li><a href="{{ n.href }}"{% if n.extern %} rel="noopener noreferrer" target="_blank"{% endif %}>{{ n.titlu }}</a></li>{% endif %}{% endfor %}
                        {% if cealalta %}<li class="mt-2"><a href="{{ base }}/{{ cealalta.slug }}/">→ {{ cealalta.titlu }}</a></li>{% endif %}
                    {% else %}
                        {% for s in sectiuni %}<li><a href="{{ base }}/{{ s.slug }}/">{{ s.titlu }}</a></li>{% endfor %}
                    {% endif %}
                </ul>
            </div>
            <div class="col-6 col-lg-4">
                <h4>Contact</h4>
                <address class="mb-0">
                    {% if setari.contact_adresa|default('') %}<p>{{ setari.contact_adresa }}</p>{% endif %}
                    {% if setari.contact_telefon|default('') %}<p><a href="tel:{{ setari.contact_telefon|replace({' ': ''}) }}">{{ setari.contact_telefon }}</a></p>{% endif %}
                    {% if setari.contact_email_public|default('') %}<p><a href="mailto:{{ setari.contact_email_public }}">{{ setari.contact_email_public }}</a></p>{% endif %}
                </address>
            </div>
        </div>
        <div class="fp-footer__logos">
            <img src="{{ base }}/assets/img/logo/eu-flag.png" alt="Uniunea Europeană" height="40" width="61"><span class="fp-footer__cofin">Cofinanțat de Uniunea Europeană</span>
            <img src="{{ base }}/assets/img/logo/guvernul-romaniei.png" alt="Guvernul României" height="44" width="45">
            <img src="{{ base }}/assets/img/logo/2021-2027.png" alt="FLAG Prahova 2021-2027" height="44" width="66">
        </div>
        <p class="fp-footer__disclaimer small">{{ setari.footer_text|default('') }}</p>
        <div class="fp-footer__bottom d-flex flex-wrap justify-content-between gap-2">
            <span>© {{ 'now'|date('Y') }} Asociația FLAG Prahova</span>
            <span>Grup de acțiune locală pentru pescuit și acvacultură</span>
        </div>
    </div>
</footer>
```

`templates/landing.twig`:

```twig
{% extends 'layout.twig' %}
{% block title %}{{ setari.landing_titlu|default('Asociația FLAG Prahova') }}{% endblock %}
{% block description %}{{ setari.landing_text|default('') }}{% endblock %}
{% block jsonld %}
<script type="application/ld+json">
{"@context": "https://schema.org", "@type": "Organization", "name": "Asociația FLAG Prahova", "url": "{{ app.url }}/", "logo": "{{ app.url }}/assets/img/logo/flag-prahova.png"{% if setari.contact_telefon|default('') %}, "telephone": "{{ setari.contact_telefon }}"{% endif %}{% if setari.contact_email_public|default('') %}, "email": "{{ setari.contact_email_public }}"{% endif %}}
</script>
{% endblock %}
{% block content %}
<section class="fp-hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="fp-eyebrow">Grup de acțiune locală pentru pescuit</span>
                <h1 class="mt-3">{{ setari.landing_titlu|default('Asociația FLAG Prahova') }}</h1>
                <p class="lead">{{ setari.landing_text|default('') }}</p>
            </div>
            <div class="col-lg-6"><img class="fp-hero__ilustratie" src="{{ base }}/assets/img/ilustratie.svg" alt="" width="640" height="480"></div>
        </div>
    </div>
</section>
<section class="fp-section fp-landing-carduri">
    <div class="container">
        <span class="fp-eyebrow fp-eyebrow--soft">Alege perioada de programare</span>
        <div class="row g-4 mt-1">
            {% for s in sectiuni %}
            <div class="col-md-6">
                <a class="fp-card fp-card--perioada d-block h-100" href="{{ base }}/{{ s.slug }}/">
                    <span class="fp-card__an">{{ s.slug }}</span>
                    <span class="h3 d-block mt-2">{{ s.titlu }}</span>
                    <span class="d-block text-muted">{{ s.subtitlu }}</span>
                    <span class="fp-btn mt-3">Intră în secțiune</span>
                </a>
            </div>
            {% endfor %}
        </div>
    </div>
</section>
{% endblock %}
```

`templates/eroare.twig`:

```twig
{% extends 'layout.twig' %}
{% block title %}Pagina nu există · FLAG Prahova{% endblock %}
{% block description %}Pagina căutată nu există sau a fost mutată.{% endblock %}
{% block meta_robots %}noindex,nofollow{% endblock %}
{% block content %}
<section class="fp-section text-center">
    <div class="container" style="max-width:640px">
        <span class="fp-eyebrow fp-eyebrow--soft">Eroare {{ status }}</span>
        <h1 class="mt-3">{{ status == 405 ? 'Metodă nepermisă' : 'Pagina nu există' }}</h1>
        <p class="lead">Linkul poate fi vechi sau greșit. Alege una dintre secțiuni:</p>
        <p>{% for s in sectiuni %}<a class="fp-btn {{ loop.first ? '' : 'fp-btn--outline' }} m-1" href="{{ base }}/{{ s.slug }}/">{{ s.titlu }}</a>{% endfor %}</p>
    </div>
</section>
{% endblock %}
```

`templates/sectiune/acasa.twig` PROVIZORIU (Task 3 îl rescrie): extinde layout, `title` = `sectiune.titlu`, `content` = `<h1>{{ sectiune.titlu }}</h1><div class="fp-prose">{{ sectiune.acasa_html|raw }}</div>`.

Șterge `templates/home.twig`; `tests/schelet_test.php` verifică doar că `/` conține „FLAG Prahova" — rămâne valabil.

- [ ] **Step 6: Rulează** `tests/public_layout_test.php`, `tests/schelet_test.php`, `tests/meniu_repository_test.php`, `tests/admin_meniu_test.php` → toate OK. Verifică și în browser `http://flagprahova.test/2014-2020/` (hover pe „Strategie" → „Proceduri Operaționale AFP" → nivel 3).

- [ ] **Step 7: Commit** — `git add -A src templates tests/public_layout_test.php && git commit -m "M3: layout public, meniu pe 3 niveluri, landing, 404 cu layout"`.

---

### Task 3: Acasă de secțiune

**Files:**
- Modify: `src/Public/PaginiController.php` (`acasa()`), `templates/sectiune/acasa.twig`
- Create: `tests/public_acasa_test.php`

**Interfaces:**
- Consumes: `Context::variabile`, `Context::arbore`, `_icon.twig`, clasele CSS din Task 1.
- Produces: `acasa()` pasează `noutati` (max 3 noduri decorate din copiii nodului cu slug `noutati`, sau `[]`), `nivel1` (rădăcinile arborelui), `contact_href` (href-ul nodului cu slug `contact` sau '').

- [ ] **Step 1: Testul** `tests/public_acasa_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$s = $pdo->query("SELECT * FROM sectiuni WHERE slug='2021-2027'")->fetch();
$marca = 'Aca' . bin2hex(random_bytes(3));
$ids = []; $fid = null; $noutatiTemp = false;
try {
    $nout = $pdo->query("SELECT id FROM meniu WHERE sectiune_id={$s['id']} AND slug='noutati' AND parent_id IS NULL")->fetch();
    if (!$nout) {
        $pdo->exec("INSERT INTO meniu (sectiune_id, ordine, titlu, slug, tip) VALUES ({$s['id']}, 0, 'Noutăți', 'noutati', 'dosar')");
        $nout = ['id' => (int) $pdo->lastInsertId()]; $ids[] = (int) $nout['id']; $noutatiTemp = true;
    }
    $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES ("c.pdf", :c, "application/pdf", 2048)')->execute(['c' => "2026/09/comunicat-$marca.pdf"]);
    $fid = (int) $pdo->lastInsertId();
    // ordine -1 => primul din listă indiferent de ce mai există
    $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, fisier_id) VALUES (:s, :p, 0, :t, :sl, "document", :f)')
        ->execute(['s' => $s['id'], 'p' => $nout['id'], 't' => "Comunicat $marca", 'sl' => "comunicat-" . strtolower($marca), 'f' => $fid]);
    $ids[] = (int) $pdo->lastInsertId();
    $pdo->exec("UPDATE meniu SET ordine = 0 WHERE id = " . end($ids));
    $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip) VALUES (:s, NULL, 998, :t, :sl, "dosar")')
        ->execute(['s' => $s['id'], 't' => "Ramura $marca", 'sl' => "ramura-" . strtolower($marca)]);
    $ids[] = (int) $pdo->lastInsertId();

    $r = cerere('GET', '/2021-2027/');
    $c = corp($r);
    ok('GET /2021-2027/ => 200', $r->getStatusCode() === 200);
    ok('  h1 unic = titlul secțiunii', substr_count($c, '<h1') === 1 && str_contains($c, $s['titlu']));
    ok('  title/description proprii', str_contains($c, '<title>' . $s['titlu']) && str_contains($c, 'name="description" content="' . $s['subtitlu']));
    ok('  canonical', str_contains($c, '<link rel="canonical" href="http://flagprahova.test/2021-2027/">'));
    ok('  textul acasă', str_contains($c, 'id="despre"'));
    ok('  blocul Noutăți cu documentul nostru + mărime', str_contains($c, "Comunicat $marca") && str_contains($c, '2 KB') && str_contains($c, "href=\"/fisiere/2026/09/comunicat-$marca.pdf\""));
    ok('  cardul pentru intrarea de nivel 1', preg_match('#class="fp-card[^"]*"[^>]*href="/2021-2027/ramura-' . strtolower($marca) . '"#', $c) === 1);
    ok('  butonul Contact din hero', str_contains($c, 'href="/2021-2027/contact"') || !str_contains($c, 'fp-hero__btn-contact'));
    ok('  ilustrația', str_contains($c, 'assets/img/ilustratie.svg'));
} finally {
    if ($ids) { $pdo->exec('DELETE FROM meniu WHERE id IN (' . implode(',', array_reverse($ids)) . ')'); }
    if ($fid) { $pdo->exec("DELETE FROM fisiere WHERE id = $fid"); }
}
final_test();
```

- [ ] **Step 2: Rulează, pică** (blocul Noutăți, cardul, description).

- [ ] **Step 3: `acasa()`**

```php
    public function acasa(Request $request, Response $response, array $args): Response
    {
        $s = $this->ctx->sectiune($args['perioada']) ?? throw new HttpNotFoundException($request);
        $vars = $this->ctx->variabile($s, '/' . $s['slug'] . '/');
        $noutati = []; $contact = '';
        foreach ($vars['arbore'] as $n) {
            if ($n['slug'] === 'noutati') { $noutati = array_slice($n['copii'], 0, 3); }
            if ($n['slug'] === 'contact') { $contact = $n['href']; }
        }
        $vars['noutati'] = $noutati;
        $vars['nivel1'] = $vars['arbore'];
        $vars['contact_href'] = $contact;
        return $this->twig->render($response, 'sectiune/acasa.twig', $vars);
    }
```

- [ ] **Step 4: `sectiune/acasa.twig`** (definitiv):

```twig
{% extends 'layout.twig' %}
{% block title %}{{ sectiune.titlu }} · Asociația FLAG Prahova{% endblock %}
{% block description %}{{ sectiune.subtitlu }}{% endblock %}
{% block content %}
<section class="fp-hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="fp-eyebrow">{{ sectiune.slug }}</span>
                <h1 class="mt-3">{{ sectiune.titlu }}</h1>
                <p class="lead">{{ sectiune.subtitlu }}</p>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    {% for n in nivel1 %}{% if n.slug == 'noutati' and n.href != '' %}<a class="fp-btn" href="{{ n.href }}">Noutăți</a>{% endif %}{% endfor %}
                    {% if contact_href %}<a class="fp-btn fp-btn--outline fp-hero__btn-contact" href="{{ contact_href }}">Contact</a>{% endif %}
                </div>
            </div>
            <div class="col-lg-6"><img class="fp-hero__ilustratie" src="{{ base }}/assets/img/ilustratie.svg" alt="" width="640" height="480"></div>
        </div>
    </div>
</section>

<section class="fp-section fp-section--soft" id="despre">
    <div class="container">
        <div class="row g-5 align-items-start">
            <div class="col-lg-7">
                <span class="fp-eyebrow">Despre program</span>
                <div class="fp-prose mt-3">{{ sectiune.acasa_html|raw }}</div>
            </div>
            <div class="col-lg-5">
                {% if noutati %}
                <div class="fp-card fp-card--lista">
                    <h2 class="h4">Noutăți</h2>
                    {% for n in noutati %}{% include '_document_rand.twig' with {'n': n} only %}{% endfor %}
                    {% for n in nivel1 %}{% if n.slug == 'noutati' and n.href != '' %}<a class="fp-btn fp-btn--outline mt-3" href="{{ n.href }}">Toate noutățile</a>{% endif %}{% endfor %}
                </div>
                {% endif %}
            </div>
        </div>
    </div>
</section>

<section class="fp-section">
    <div class="container">
        <span class="fp-eyebrow fp-eyebrow--soft">Ce găsești aici</span>
        <div class="row g-4 mt-1">
            {% for n in nivel1 %}{% if n.href != '' %}
            <div class="col-sm-6 col-lg-3">
                <a class="fp-card d-block h-100" href="{{ n.href }}"{% if n.extern %} target="_blank" rel="noopener noreferrer"{% endif %}>
                    <span class="fp-card__icon">{% include '_icon.twig' with {'nume': n.slug == 'noutati' ? 'noutati' : (n.slug == 'contact' ? 'contact' : n.tip)} only %}</span>
                    <span class="h5 d-block mt-3">{{ n.titlu }}</span>
                    {% if n.copii|length %}<span class="text-muted small">{{ n.copii|length }} {{ n.copii|length == 1 ? 'intrare' : 'intrări' }}</span>{% endif %}
                </a>
            </div>
            {% endif %}{% endfor %}
        </div>
    </div>
</section>

{% if contact_href %}
<section class="fp-cta">
    <div class="container text-center">
        <span class="fp-eyebrow">Contact</span>
        <h2 class="mt-3">Ai o întrebare despre program?</h2>
        <p class="lead">Scrie-ne sau sună-ne — răspundem în zilele lucrătoare.</p>
        <a class="fp-btn" href="{{ contact_href }}">Contactează-ne</a>
    </div>
</section>
{% endif %}
{% endblock %}
```

`templates/_document_rand.twig` (folosit și de Task 4):

```twig
{% set e = n.tip == 'document' ? ext(n.fisier_cale) : n.tip %}
<div class="fp-doc" data-ext="{{ e }}">
    <span class="fp-doc__icon" aria-hidden="true">{% if n.tip == 'document' %}{{ e|upper }}{% else %}{% include '_icon.twig' with {'nume': n.tip} only %}{% endif %}</span>
    <div class="fp-doc__text">
        {% if n.href == '' %}<span class="fp-doc__titlu is-inactiv">{{ n.titlu }}</span>
        {% else %}<a class="fp-doc__titlu" href="{{ n.href }}"{% if n.extern %} target="_blank" rel="noopener noreferrer"{% endif %}>{{ n.titlu }}</a>{% endif %}
        <span class="fp-doc__meta">
            {%- if n.tip == 'document' and n.fisier_cale %}{{ e|upper }} · {{ marime(n.fisier_marime) }}
            {%- elseif n.tip == 'dosar' %}{{ n.copii|length }} {{ n.copii|length == 1 ? 'intrare' : 'intrări' }}
            {%- elseif n.tip == 'galerie' %}Galerie foto
            {%- elseif n.tip == 'link' %}Link extern
            {%- else %}Pagină{% endif -%}
        </span>
    </div>
</div>
```

- [ ] **Step 5: Rulează** testul → OK. Verifică în browser ambele acasă.

- [ ] **Step 6: Commit** — `M3: acasa de sectiune — hero, despre, noutati, carduri, CTA`.

---

### Task 4: Pagini, dosare, galerii, documente, linkuri, sidebar, breadcrumb

**Files:**
- Modify: `src/Public/PaginiController.php` (+ `pagina()`), `src/Routes.php` (+ ruta `/{perioada}/{slug}`)
- Create: `templates/sectiune/pagina.twig`, `templates/sectiune/dosar.twig`, `templates/sectiune/galerie.twig`, `templates/sectiune/contact.twig` (doar conținut + placeholder formular; Task 6 pune formularul), `templates/_lista_intrari.twig`, `templates/_galerie_grila.twig`, `templates/_sidebar.twig`, `templates/_breadcrumb.twig`, `tests/public_pagina_test.php`

**Interfaces:**
- Consumes: `Context::gaseste`, `Context::href`, `Meniu\Repository::gaseste`, `Meniu\GalerieRepository::imagini`, funcțiile Twig `mini`, `marime`, `ext`.
- Produces: `pagina()` → pentru `pagina`: `sectiune/pagina.twig` (sau `sectiune/contact.twig` când `sablon = 'contact'`) cu `nod`, `rand` (rândul complet, cu `continut_html`), `stramosi`, `galerii` (copiii de tip `galerie` ai paginii, fiecare cu `imagini`), `ramura` (nodul de nivel 1 al ramurii curente, pentru sidebar), `activ` (id-urile nod + strămoși); `dosar` → `sectiune/dosar.twig`; `galerie` → `sectiune/galerie.twig` cu `imagini`; `document` → 302 la fișier (404 dacă `href` gol); `link` → 302 la URL. Toate cu `og_image` implicit; galeriile pasează `og_image = mini(prima imagine, 1600)`.
- `templates/_galerie_grila.twig` primește `imagini` (rânduri din `GalerieRepository::imagini`) și randează `<div class="fp-galerie">` cu `<a class="fp-galerie__item" href="{fisiere}/{cale}" data-full="{{ mini(cale, 1600) }}" data-legenda="…"><img src="{{ mini(cale, 480) }}" loading="lazy" width="480" height="360" alt="…"></a>`.

- [ ] **Step 1: Testul** `tests/public_pagina_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$s = $pdo->query("SELECT * FROM sectiuni WHERE slug='2014-2020'")->fetch();
$m = strtolower('Pag' . bin2hex(random_bytes(3)));
$ids = []; $fids = [];
try {
    $insF = $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES (:n, :c, :m, :s)');
    $insF->execute(['n' => 'Ghid.pdf', 'c' => "2026/09/ghid-$m.pdf", 'm' => 'application/pdf', 's' => 1536000]); $fids['pdf'] = (int) $pdo->lastInsertId();
    $insF->execute(['n' => 'Poza.jpg', 'c' => "2026/09/poza-$m.jpg", 'm' => 'image/jpeg', 's' => 90000]); $fids['jpg'] = (int) $pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, continut_html, fisier_id, url, sablon, vizibil) VALUES (:s, :p, :o, :t, :sl, :tip, :h, :f, :u, :sab, :v)');
    $add = function (?int $p, string $t, string $tip, array $x = []) use ($ins, $pdo, $s, &$ids): int {
        $ins->execute(['s' => $s['id'], 'p' => $p, 'o' => $x['o'] ?? 999, 't' => $t, 'sl' => slugify($t), 'tip' => $tip, 'h' => $x['h'] ?? null, 'f' => $x['f'] ?? null, 'u' => $x['u'] ?? null, 'sab' => $x['sab'] ?? 'standard', 'v' => $x['v'] ?? 1]);
        $id = (int) $pdo->lastInsertId(); $ids[] = $id; return $id;
    };
    $dosar  = $add(null, "Arhiva $m", 'dosar');
    $sub    = $add($dosar, "Sesiunea $m", 'dosar', ['o' => 1]);
    $doc    = $add($sub, "Ghid $m", 'document', ['f' => $fids['pdf']]);
    $docGol = $add($sub, "Fara fisier $m", 'document');
    $link   = $add($dosar, "Extern $m", 'link', ['u' => 'https://example.org/p', 'o' => 2]);
    $pag    = $add($dosar, "Pagina $m", 'pagina', ['h' => "<h2>Sub $m</h2><p>Text <strong>bold</strong></p>", 'o' => 0]);
    $gal    = $add($pag, "Galerie $m", 'galerie');
    $pdo->exec("INSERT INTO galerie_imagini (meniu_id, fisier_id, ordine, legenda) VALUES ($gal, {$fids['jpg']}, 0, 'Legenda $m')");
    $ascuns = $add($dosar, "Ascuns $m", 'pagina', ['v' => 0, 'h' => '<p>secret</p>']);
    $subAsc = $add($ascuns, "Copil ascuns $m", 'pagina', ['h' => '<p>secret2</p>']);
    $contact = $add(null, "Contact $m", 'pagina', ['sab' => 'contact', 'h' => "<p>Adresa $m</p>"]);

    $r = cerere('GET', "/2014-2020/pagina-$m"); $c = corp($r);
    ok('pagina => 200', $r->getStatusCode() === 200);
    ok('  h1 unic + conținut raw', substr_count($c, '<h1') === 1 && str_contains($c, "<h2>Sub $m</h2>") && str_contains($c, '<strong>bold</strong>'));
    ok('  title/canonical', str_contains($c, "<title>Pagina $m") && str_contains($c, "href=\"http://flagprahova.test/2014-2020/pagina-$m\""));
    ok('  breadcrumb cu părintele', str_contains($c, 'fp-breadcrumb') && str_contains($c, "Arhiva $m") && str_contains($c, '"@type": "BreadcrumbList"'));
    ok('  galeria-copil sub conținut, cu miniatură + lightbox', str_contains($c, "Galerie $m") && str_contains($c, "/fisiere/mini/480/2026/09/poza-$m.webp") && str_contains($c, "data-full=\"/fisiere/mini/1600/2026/09/poza-$m.webp\"") && str_contains($c, 'loading="lazy"'));
    ok('  sidebar cu ramura + contact rapid', str_contains($c, 'fp-sidebar') && str_contains($c, "Sesiunea $m") && str_contains($c, 'fp-contact-rapid'));
    ok('  meniul marchează nodul curent', preg_match('#<a class="[^"]*is-activ[^"]*" href="/2014-2020/pagina-' . $m . '"#', $c) === 1);

    $r = cerere('GET', "/2014-2020/arhiva-$m"); $c = corp($r);
    ok('dosar => 200 cu lista copiilor grupată', $r->getStatusCode() === 200 && str_contains($c, "Sesiunea $m") && str_contains($c, "Ghid $m") && str_contains($c, '1,5 MB') && str_contains($c, 'data-ext="pdf"'));
    ok('  documentul fără fișier apare fără link', str_contains($c, "Fara fisier $m") && !str_contains($c, "href=\"\""));
    ok('  linkul extern', str_contains($c, 'href="https://example.org/p"'));
    ok('  invizibilul lipsește', !str_contains($c, "Ascuns $m"));

    $r = cerere('GET', "/2014-2020/galerie-$m"); $c = corp($r);
    ok('galerie => 200, grilă + og:image', $r->getStatusCode() === 200 && str_contains($c, 'class="fp-galerie"') && str_contains($c, "Legenda $m") && str_contains($c, "og:image\" content=\"http://flagprahova.test/fisiere/mini/1600/2026/09/poza-$m.webp"));

    $r = cerere('GET', "/2014-2020/ghid-$m");
    ok('document => 302 la fișier', $r->getStatusCode() === 302 && $r->getHeaderLine('Location') === "/fisiere/2026/09/ghid-$m.pdf");
    $r = cerere('GET', "/2014-2020/fara-fisier-$m");
    ok('document fără fișier => 404', $r->getStatusCode() === 404);
    $r = cerere('GET', "/2014-2020/extern-$m");
    ok('link => 302 extern', $r->getStatusCode() === 302 && $r->getHeaderLine('Location') === 'https://example.org/p');
    $r = cerere('GET', "/2014-2020/ascuns-$m");
    ok('pagina invizibilă => 404', $r->getStatusCode() === 404 && !str_contains(corp($r), 'secret'));
    $r = cerere('GET', "/2014-2020/copil-ascuns-$m");
    ok('copil vizibil al unui invizibil => 404', $r->getStatusCode() === 404 && !str_contains(corp($r), 'secret2'));
    $r = cerere('GET', "/2021-2027/pagina-$m");
    ok('slug din altă secțiune => 404', $r->getStatusCode() === 404);
    $r = cerere('GET', "/2014-2020/contact-$m"); $c = corp($r);
    ok('șablon contact => 200 cu conținut + formular', $r->getStatusCode() === 200 && str_contains($c, "Adresa $m") && str_contains($c, 'class="fp-form'));
} finally {
    if ($ids) { $pdo->exec('DELETE FROM meniu WHERE id IN (' . implode(',', array_reverse($ids)) . ')'); }
    if ($fids) { $pdo->exec('DELETE FROM fisiere WHERE id IN (' . implode(',', $fids) . ')'); }
}
final_test();
```

- [ ] **Step 2: Rulează, pică** (404 pe toate — ruta lipsește).

- [ ] **Step 3: `pagina()` + ruta**

În `Routes.php`: `$app->get('/{perioada:[0-9]{4}-[0-9]{4}}/{slug:[a-z0-9-]+}', fn($rq, $rs, $a) => $pc()->pagina($rq, $rs, $a));`

În `PaginiController`:

```php
    public function pagina(Request $request, Response $response, array $args): Response
    {
        $s = $this->ctx->sectiune($args['perioada']) ?? throw new HttpNotFoundException($request);
        $arbore = $this->ctx->arbore($s);
        $g = $this->ctx->gaseste($arbore, $args['slug']) ?? throw new HttpNotFoundException($request);
        $nod = $g['nod'];
        if ($nod['tip'] === 'document' || $nod['tip'] === 'link') {
            if ($nod['href'] === '') { throw new HttpNotFoundException($request); }
            return $response->withHeader('Location', $nod['href'])->withStatus(302);
        }
        $rand = $this->container['meniu']->gaseste((int) $nod['id']);
        $activ = array_map(fn($n) => (int) $n['id'], [...$g['stramosi'], $nod]);
        $vars = $this->ctx->variabile($s, '/' . $s['slug'] . '/' . $nod['slug'], [
            'nod' => $nod, 'rand' => $rand, 'stramosi' => $g['stramosi'], 'activ' => $activ,
            'ramura' => $g['stramosi'][0] ?? $nod,
        ]);
        $vars['arbore'] = $arbore;
        /** @var \App\Meniu\GalerieRepository $galerii */
        $galerii = $this->container['galerie'];
        if ($nod['tip'] === 'galerie') {
            $vars['imagini'] = $galerii->imagini((int) $nod['id']);
            if ($vars['imagini']) { $vars['og_image'] = '/fisiere/mini/1600/' . preg_replace('/\.[^.\/]+$/', '.webp', $vars['imagini'][0]['cale']); }
            return $this->twig->render($response, 'sectiune/galerie.twig', $vars);
        }
        if ($nod['tip'] === 'dosar') {
            return $this->twig->render($response, 'sectiune/dosar.twig', $vars);
        }
        $vars['galerii'] = [];
        foreach ($nod['copii'] as $c) {
            if ($c['tip'] === 'galerie') { $c['imagini'] = $galerii->imagini((int) $c['id']); $vars['galerii'][] = $c; }
        }
        $sablon = ($rand['sablon'] ?? 'standard') === 'contact' ? 'sectiune/contact.twig' : 'sectiune/pagina.twig';
        return $this->twig->render($response, $sablon, $vars);
    }
```

- [ ] **Step 4: Template-urile**

`templates/_breadcrumb.twig` (primește `stramosi`, `nod`, `sectiune`):

```twig
<nav class="fp-breadcrumb" aria-label="Ești aici">
    <a href="{{ base }}/{{ sectiune.slug }}/">{{ sectiune.titlu }}</a>
    {% for s in stramosi %} › {% if s.href and s.tip != 'document' %}<a href="{{ s.href }}">{{ s.titlu }}</a>{% else %}<span>{{ s.titlu }}</span>{% endif %}{% endfor %}
    › <span aria-current="page">{{ nod.titlu }}</span>
</nav>
```

Blocul `jsonld` cu BreadcrumbList (în fiecare din cele 4 template-uri de pagină, sau — mai bine — într-un `templates/sectiune/_baza.twig` pe care îl extind toate):

```twig
{% extends 'layout.twig' %}
{% block title %}{{ nod.titlu }} · {{ sectiune.titlu }}{% endblock %}
{% block description %}{{ nod.titlu }} — {{ sectiune.titlu }}, Asociația FLAG Prahova.{% endblock %}
{% block jsonld %}
<script type="application/ld+json">
{"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [
 {"@type": "ListItem", "position": 1, "name": {{ sectiune.titlu|json_encode|raw }}, "item": "{{ app.url }}/{{ sectiune.slug }}/"}
 {%- for s in stramosi %}, {"@type": "ListItem", "position": {{ loop.index + 1 }}, "name": {{ s.titlu|json_encode|raw }}{% if s.href %}, "item": "{{ s.href starts with 'http' ? s.href : app.url ~ s.href }}"{% endif %}}{% endfor %}
 , {"@type": "ListItem", "position": {{ stramosi|length + 2 }}, "name": {{ nod.titlu|json_encode|raw }}}
]}
</script>
{% endblock %}
{% block content %}
<div class="container fp-pagina">
    {% include '_breadcrumb.twig' %}
    <div class="row g-5">
        <div class="col-lg-8">
            <h1>{{ nod.titlu }}</h1>
            {% block corp %}{% endblock %}
        </div>
        <aside class="col-lg-4">{% include '_sidebar.twig' %}</aside>
    </div>
</div>
{% endblock %}
```

`sectiune/pagina.twig`:

```twig
{% extends 'sectiune/_baza.twig' %}
{% block corp %}
<div class="fp-prose">{{ rand.continut_html|raw }}</div>
{% if galerii %}
<div class="fp-acordeon mt-5">
    {% for g in galerii %}
    <details class="fp-acordeon__item"{% if loop.first %} open{% endif %}>
        <summary><span>{{ g.titlu }}</span><span class="fp-acordeon__meta">{{ g.imagini|length }} imagini</span></summary>
        {% include '_galerie_grila.twig' with {'imagini': g.imagini} only %}
    </details>
    {% endfor %}
</div>
{% endif %}
{% endblock %}
```

`sectiune/dosar.twig` — listă grupată recursiv prin `_lista_intrari.twig`:

```twig
{% extends 'sectiune/_baza.twig' %}
{% block corp %}
{% if nod.copii is empty %}<p class="text-muted">Nu există încă documente în această secțiune.</p>
{% else %}{% include '_lista_intrari.twig' with {'noduri': nod.copii, 'nivel': 2} only %}{% endif %}
{% endblock %}
```

`templates/_lista_intrari.twig`:

```twig
<div class="fp-lista">
{% for n in noduri %}
    {% if n.tip == 'dosar' and n.copii|length %}
        <section class="fp-lista__grup">
            <h{{ nivel }} class="fp-lista__titlu h5"><a href="{{ n.href }}">{{ n.titlu }}</a></h{{ nivel }}>
            {% include '_lista_intrari.twig' with {'noduri': n.copii, 'nivel': min(nivel + 1, 4)} only %}
        </section>
    {% else %}
        {% include '_document_rand.twig' with {'n': n} only %}
    {% endif %}
{% endfor %}
</div>
```

`sectiune/galerie.twig`:

```twig
{% extends 'sectiune/_baza.twig' %}
{% block corp %}
{% if imagini is empty %}<p class="text-muted">Galeria nu are încă imagini.</p>{% else %}{% include '_galerie_grila.twig' with {'imagini': imagini} only %}{% endif %}
{% endblock %}
```

`templates/_galerie_grila.twig`:

```twig
<div class="fp-galerie">
{% for i in imagini %}
    <a class="fp-galerie__item" href="{{ base }}{{ fisiere_url }}/{{ i.cale }}" data-full="{{ mini(i.cale, 1600) }}" data-legenda="{{ i.legenda }}">
        <img src="{{ mini(i.cale, 480) }}" alt="{{ i.legenda ?: i.nume_afisat }}" loading="lazy" width="480" height="360">
    </a>
{% endfor %}
</div>
```

`sectiune/contact.twig` (Task 6 adaugă formularul real; acum doar structura):

```twig
{% extends 'sectiune/_baza.twig' %}
{% block corp %}
<div class="fp-prose">{{ rand.continut_html|raw }}</div>
<section class="fp-form mt-5" id="formular">
    <h2 class="h4">Trimite-ne un mesaj</h2>
    {% block formular %}{% endblock %}
</section>
{% endblock %}
```

`templates/_sidebar.twig` (primește din context `ramura`, `activ`, `setari`):

```twig
<div class="fp-sidebar">
    {% if ramura.copii|length %}
    <nav class="fp-sidebar__nav" aria-label="În această secțiune">
        <h2 class="h6 text-uppercase">{{ ramura.titlu }}</h2>
        {% include '_meniu_mobil.twig' with {'noduri': ramura.copii, 'activ': activ} only %}
    </nav>
    {% endif %}
    <div class="fp-contact-rapid">
        <h2 class="h6 text-uppercase">Contact rapid</h2>
        {% if setari.contact_adresa|default('') %}<p>{% include '_icon.twig' with {'nume': 'pin'} only %} {{ setari.contact_adresa }}</p>{% endif %}
        {% if setari.contact_telefon|default('') %}<p>{% include '_icon.twig' with {'nume': 'telefon'} only %} <a href="tel:{{ setari.contact_telefon|replace({' ': ''}) }}">{{ setari.contact_telefon }}</a></p>{% endif %}
        {% if setari.contact_email_public|default('') %}<p>{% include '_icon.twig' with {'nume': 'contact'} only %} <a href="mailto:{{ setari.contact_email_public }}">{{ setari.contact_email_public }}</a></p>{% endif %}
    </div>
</div>
```

(`_meniu_mobil.twig` refolosit în sidebar — CSS-ul `.fp-sidebar__nav .fp-mobil__lista` îl stilizează ca listă verticală.) Setările de contact vin în Task 6; până atunci blocul rămâne cu titlul (testul caută doar `fp-contact-rapid`).

- [ ] **Step 5: Rulează** `public_pagina_test.php` + `public_layout_test.php` → OK. În browser: `/2014-2020/cooperare` (conținut + 14 galerii în acordeon — miniaturile dau 404 până la Task 5, e așteptat), `/2014-2020/arhiva`, `/2014-2020/strategie`.

- [ ] **Step 6: Commit** — `M3: pagini, dosare, galerii, redirect documente/linkuri, sidebar, breadcrumb`.

---

### Task 5: Miniaturi WebP la cerere

**Files:**
- Create: `src/Fisiere/Miniatura.php`, `src/Public/MiniaturaController.php`, `tests/public_miniatura_test.php`
- Modify: `src/Routes.php`, `database/reset_continut.php` (la reset șterge și `fisiere/mini/`), `CLAUDE.md` (o linie despre `fisiere/mini/`)

**Interfaces:**
- Produces `App\Fisiere\Miniatura`:
  - `public const LATIMI = [480, 1600];`
  - `__construct(string $dirFisiere)`
  - `static caleMini(string $cale, int $latime): ?string` — `mini/{latime}/{cale cu .webp}`; null dacă `$latime` nu e în `LATIMI` sau `$cale` nu respectă `^\d{4}/\d{2}/[A-Za-z0-9._-]+\.(jpe?g|png|webp)$`.
  - `asigura(string $cale, int $latime): ?string` — calea ABSOLUTĂ a miniaturii, generând-o dacă lipsește (GD, `imagewebp` calitate 82, fără upscale, orientare EXIF respectată când `exif` e încărcat); null dacă sursa lipsește sau nu se decodează. Ridică `memory_limit` la `512M` pe durata decodării.
- `MiniaturaController::__invoke(Request, Response, array $args)` — `args['latime']`, `args['cale']`; 404 (prin `HttpNotFoundException`) dacă `asigura` dă null; altfel corpul fișierului cu `Content-Type: image/webp`, `Cache-Control: public, max-age=31536000, immutable`.

- [ ] **Step 1: Testul** `tests/public_miniatura_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Fisiere\Miniatura;

$dir = settings()['upload']['dir'];
$m = bin2hex(random_bytes(3));
$rel = "2026/09/mini-test-$m.png";
@mkdir("$dir/2026/09", 0775, true);
// sursă 800x600 generată cu GD (nu depinde de fixtures)
$im = imagecreatetruecolor(800, 600); imagefill($im, 0, 0, imagecolorallocate($im, 30, 111, 197)); imagepng($im, "$dir/$rel"); imagedestroy($im);
try {
    ok('caleMini ok', Miniatura::caleMini($rel, 480) === "mini/480/2026/09/mini-test-$m.webp");
    ok('caleMini lățime nepermisă => null', Miniatura::caleMini($rel, 999) === null);
    ok('caleMini cale cu ../ => null', Miniatura::caleMini('2026/09/../../x.png', 480) === null);
    ok('caleMini pdf => null', Miniatura::caleMini('2026/09/x.pdf', 480) === null);

    $r = cerere('GET', "/fisiere/mini/480/$rel" === '' ? '' : '/fisiere/mini/480/2026/09/mini-test-' . $m . '.webp');
    ok('GET miniatură => 200 image/webp', $r->getStatusCode() === 200 && $r->getHeaderLine('Content-Type') === 'image/webp');
    ok('  cache-control lung', str_contains($r->getHeaderLine('Cache-Control'), 'max-age=31536000'));
    $abs = "$dir/mini/480/2026/09/mini-test-$m.webp";
    ok('  fișierul e scris pe disc', is_file($abs));
    [$w, $h] = getimagesize($abs);
    ok('  redimensionat la 480x360', $w === 480 && $h === 360);
    $mt = filemtime($abs); sleep(1);
    $r = cerere('GET', "/fisiere/mini/480/2026/09/mini-test-$m.webp");
    ok('  a doua cerere nu regenerează', $r->getStatusCode() === 200 && filemtime($abs) === $mt);
    $r = cerere('GET', "/fisiere/mini/1600/2026/09/mini-test-$m.webp");
    [$w] = getimagesize("$dir/mini/1600/2026/09/mini-test-$m.webp");
    ok('1600 nu mărește peste original (800)', $r->getStatusCode() === 200 && $w === 800);
    $r = cerere('GET', "/fisiere/mini/480/2026/09/nu-exista-$m.webp");
    ok('sursă lipsă => 404', $r->getStatusCode() === 404);
    $r = cerere('GET', "/fisiere/mini/300/2026/09/mini-test-$m.webp");
    ok('lățime nepermisă => 404', $r->getStatusCode() === 404);
} finally {
    @unlink("$dir/$rel");
    @unlink("$dir/mini/480/2026/09/mini-test-$m.webp");
    @unlink("$dir/mini/1600/2026/09/mini-test-$m.webp");
}
final_test();
```

(Curăță linia cu ternarul inutil din a cincea aserțiune: cererea e pur și simplu `cerere('GET', "/fisiere/mini/480/2026/09/mini-test-$m.webp")`.)

- [ ] **Step 2: Rulează, pică.**

- [ ] **Step 3: Implementarea**

`src/Fisiere/Miniatura.php`:

```php
<?php
declare(strict_types=1);

namespace App\Fisiere;

/**
 * Miniaturi WebP generate la cerere în `fisiere/mini/{lățime}/AAAA/LL/nume.webp`.
 * Prima cerere trece prin PHP (MiniaturaController); următoarele sunt servite
 * direct de Apache, fiindcă fișierul există pe disc (.htaccess: -f => servit).
 */
final class Miniatura
{
    public const LATIMI = [480, 1600];
    private const CALE_OK = '#^\d{4}/\d{2}/[A-Za-z0-9._-]+\.(jpe?g|png|webp)$#i';

    public function __construct(private string $dirFisiere) {}

    public static function caleMini(string $cale, int $latime): ?string
    {
        if (!in_array($latime, self::LATIMI, true) || !preg_match(self::CALE_OK, $cale) || str_contains($cale, '..')) { return null; }
        return 'mini/' . $latime . '/' . preg_replace('/\.[^.\/]+$/', '.webp', $cale);
    }

    /** Calea absolută a miniaturii (generată dacă lipsește) sau null. */
    public function asigura(string $cale, int $latime): ?string
    {
        $rel = self::caleMini($cale, $latime);
        if ($rel === null) { return null; }
        $dest = $this->dirFisiere . '/' . $rel;
        if (is_file($dest)) { return $dest; }
        $sursa = $this->dirFisiere . '/' . $cale;
        if (!is_file($sursa)) { return null; }
        $vechi = ini_get('memory_limit');
        ini_set('memory_limit', '512M');
        try {
            $im = @imagecreatefromstring((string) file_get_contents($sursa));
            if ($im === false) { return null; }
            $im = $this->orienteaza($im, $sursa);
            $w = imagesx($im); $h = imagesy($im);
            if ($w > $latime) {
                $nh = (int) round($h * $latime / $w);
                $nou = imagecreatetruecolor($latime, $nh);
                imagecopyresampled($nou, $im, 0, 0, 0, 0, $latime, $nh, $w, $h);
                imagedestroy($im); $im = $nou;
            }
            if (!is_dir(dirname($dest))) { mkdir(dirname($dest), 0775, true); }
            $ok = imagewebp($im, $dest, 82);
            imagedestroy($im);
            return $ok ? $dest : null;
        } finally {
            ini_set('memory_limit', (string) $vechi);
        }
    }

    private function orienteaza(\GdImage $im, string $sursa): \GdImage
    {
        if (!function_exists('exif_read_data') || !preg_match('/\.jpe?g$/i', $sursa)) { return $im; }
        $exif = @exif_read_data($sursa);
        $rot = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($rot === 0) { return $im; }
        $r = imagerotate($im, $rot, 0);
        if ($r === false) { return $im; }
        imagedestroy($im);
        return $r;
    }
}
```

`src/Public/MiniaturaController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Public;

use App\Fisiere\Miniatura;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

final class MiniaturaController
{
    public function __construct(private Miniatura $mini) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        // URL-ul cere .webp; sursa are extensia originală — o căutăm între cele acceptate.
        $ceruta = (string) $args['cale'];
        $latime = (int) $args['latime'];
        $baza = preg_replace('/\.webp$/i', '', $ceruta);
        $abs = null;
        foreach (['jpg', 'jpeg', 'png', 'webp', 'JPG', 'JPEG', 'PNG'] as $ext) {
            $abs = $this->mini->asigura($baza . '.' . $ext, $latime);
            if ($abs !== null) { break; }
        }
        if ($abs === null) { throw new HttpNotFoundException($request); }
        $response->getBody()->write((string) file_get_contents($abs));
        return $response->withHeader('Content-Type', 'image/webp')
            ->withHeader('Content-Length', (string) filesize($abs))
            ->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
    }
}
```

Nota: `Miniatura::CALE_OK` e case-insensitive, deci `.JPG` trece; pe Windows (Laragon) sistemul de fișiere e case-insensitive, deci prima potrivire `jpg` găsește și `x.JPG` — pe Linux (cPanel) bucla încearcă și variantele majuscule. Mai simplu și corect pe ambele: caută în DB. Înlocuiește bucla cu `Fisiere\Repository::gasesteDupaCalePrefix`? NU adăuga metode noi: folosește `Fisiere\Repository::lista(...)`? Nici. Păstrează bucla (7 încercări `is_file` ieftine).

Bootstrap `extinde()`: `$container['miniatura'] = new Fisiere\Miniatura($container['settings']['upload']['dir']);`
Routes: `$app->get('/fisiere/mini/{latime:[0-9]+}/{cale:.+}', fn($rq, $rs, $a) => (new \App\Public\MiniaturaController($container['miniatura']))($rq, $rs, $a));`

`database/reset_continut.php`: după ștergerea rândurilor, șterge recursiv directorul `fisiere/mini` dacă există (miniaturile se regenerează). `CLAUDE.md`, la Convenții: „Miniaturile galeriilor se generează la cerere în `fisiere/mini/{480|1600}/…` (`Fisiere\Miniatura`), gitignored ca tot `fisiere/`; `reset_continut.php` le șterge."

- [ ] **Step 4: Rulează** testul → OK. Verifică prin Apache (nu `php -S`): `curl -sI http://flagprahova.test/fisiere/mini/480/2022/10/instruire-01-busteni.webp` de două ori — a doua trebuie servită de Apache (fără header `X-Powered-By`/cu `Last-Modified`). Deschide `/2014-2020/cooperare` și lightbox-ul.

- [ ] **Step 5: Commit** — `M3: miniaturi WebP la cerere + lightbox`.

---

### Task 6: Formularul de contact + setări de contact

**Files:**
- Create: `src/Form/TimeToken.php` (copiat identic din `C:/laragon/www/pestelocal/src/Form/TimeToken.php`, namespace `App\Form`), `src/Public/ContactController.php`, `tests/public_contact_test.php`
- Modify: `src/Setari/Repository.php` (`CHEI` + 3 chei), `database/seed.php` (+ valori), `templates/admin/setari.twig` (+ 3 câmpuri), `templates/sectiune/contact.twig` (formularul), `src/Routes.php` (POST), `src/Public/PaginiController.php` (pasează `form` gol + `time_token` la randarea contact)

**Interfaces:**
- Consumes: `App\Form\TimeToken::mint()` / `isValidAndAged()`, `Mail\Mailer::send(to, subject, html, replyTo)`, `Setari\Repository::get('contact_email_destinatar')`.
- Produces `ContactController::trimite(Request, Response, array $args)`: POST `/{perioada}/{slug}`; 404 dacă slug-ul nu e pagină cu `sablon = contact` vizibilă; câmpuri `nume` (2–120), `email` (valid, ≤190), `mesaj` (10–5000), `website` (honeypot, trebuie gol), `_t` (TimeToken), `_csrf`. Erori → re-randează `sectiune/contact.twig` cu `form = {erori: {camp: mesaj}, valori: {...}}` (status 200). Succes → INSERT în `mesaje_contact` (`sectiune_id`, `nume`, `email`, `mesaj`, `ip_hash`), apoi `Mailer::send()` către `contact_email_destinatar`, `UPDATE mesaje_contact SET email_trimis = 1` dacă a plecat, apoi 302 la `/{perioada}/{slug}?trimis=1#formular`. Honeypot plin sau token invalid → răspuns IDENTIC cu succesul (302), fără INSERT (nu-i spunem botului că l-am prins).
- Chei noi în `Setari::CHEI`: `contact_adresa` (seed: `Comuna Păulești, Sat Găgeni, nr. 41, județul Prahova`), `contact_telefon` (`0762 609 685`), `contact_email_public` (`flagprahova@gmail.com`).

- [ ] **Step 1: Testul** `tests/public_contact_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Form\TimeToken;

$pdo = pdo();
$s = $pdo->query("SELECT * FROM sectiuni WHERE slug='2021-2027'")->fetch();
$m = strtolower('Ct' . bin2hex(random_bytes(3)));
$_SESSION['csrf'] = 'abc';
$ids = []; $log = dirname(__DIR__) . '/storage/logs/mail.log';
try {
    $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, continut_html, sablon) VALUES (:s, NULL, 999, :t, :sl, "pagina", "<p>x</p>", "contact")')
        ->execute(['s' => $s['id'], 't' => "Contact $m", 'sl' => "contact-$m"]);
    $ids[] = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, continut_html) VALUES (:s, NULL, 999, :t, :sl, "pagina", "<p>y</p>")')
        ->execute(['s' => $s['id'], 't' => "Normala $m", 'sl' => "normala-$m"]);
    $ids[] = (int) $pdo->lastInsertId();
    $url = "/2021-2027/contact-$m";

    $r = cerere('GET', $url); $c = corp($r);
    ok('GET contact are formularul cu csrf, token, honeypot', str_contains($c, 'name="_csrf" value="abc"') && str_contains($c, 'name="_t"') && str_contains($c, 'name="website"') && str_contains($c, 'name="mesaj"'));
    ok('  setările de contact în sidebar', str_contains($c, '0762 609 685') || str_contains($c, 'fp-contact-rapid'));

    $tokVechi = (string) (time() - 10) . '.' . hash_hmac('sha256', (string) (time() - 10), 'abc');
    $bun = ['_csrf' => 'abc', '_t' => $tokVechi, 'website' => '', 'nume' => "Ion $m", 'email' => "ion-$m@example.com", 'mesaj' => "Salut, am o intrebare $m despre program."];
    $n0 = (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn();

    $r = cerere('POST', $url, ['_csrf' => 'gresit'] + $bun);
    ok('CSRF greșit => 200 cu eroare, nimic salvat', $r->getStatusCode() === 200 && str_contains(corp($r), 'Sesiunea a expirat') && (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn() === $n0);

    $r = cerere('POST', $url, ['nume' => 'I', 'email' => 'nu-e-email', 'mesaj' => 'scurt'] + $bun);
    $c = corp($r);
    ok('validare: 3 erori, valorile păstrate', $r->getStatusCode() === 200 && str_contains($c, 'is-invalid') && substr_count($c, 'invalid-feedback') >= 3 && str_contains($c, 'value="nu-e-email"'));
    ok('  nimic salvat', (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn() === $n0);

    $r = cerere('POST', $url, ['website' => 'http://spam'] + $bun);
    ok('honeypot plin => 302 „succes" fals, nimic salvat', $r->getStatusCode() === 302 && (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn() === $n0);
    $r = cerere('POST', $url, ['_t' => TimeToken::mint()] + $bun);
    ok('token prea proaspăt => 302 fals, nimic salvat', $r->getStatusCode() === 302 && (int) $pdo->query('SELECT COUNT(*) FROM mesaje_contact')->fetchColumn() === $n0);

    $r = cerere('POST', $url, $bun);
    ok('trimitere validă => 302 cu ?trimis=1', $r->getStatusCode() === 302 && $r->getHeaderLine('Location') === "$url?trimis=1#formular");
    $row = $pdo->query("SELECT * FROM mesaje_contact WHERE email = 'ion-$m@example.com'")->fetch();
    ok('  salvat cu secțiunea și marcat trimis (SMTP gol => log)', $row && (int) $row['sectiune_id'] === (int) $s['id'] && (int) $row['email_trimis'] === 1);
    ok('  emailul e în mail.log', is_file($log) && str_contains((string) file_get_contents($log), "intrebare $m"));
    $r = cerere('GET', "$url?trimis=1");
    ok('GET ?trimis=1 arată confirmarea', str_contains(corp($r), 'Mesajul a fost trimis'));

    $r = cerere('POST', "/2021-2027/normala-$m", $bun);
    ok('POST pe pagină fără șablon contact => 404', $r->getStatusCode() === 404);
} finally {
    $pdo->exec("DELETE FROM mesaje_contact WHERE email = 'ion-$m@example.com'");
    if ($ids) { $pdo->exec('DELETE FROM meniu WHERE id IN (' . implode(',', $ids) . ')'); }
}
final_test();
```

- [ ] **Step 2: Rulează, pică.**

- [ ] **Step 3: Implementarea**

`Setari\Repository::CHEI = ['contact_email_destinatar', 'landing_titlu', 'landing_text', 'footer_text', 'contact_adresa', 'contact_telefon', 'contact_email_public'];` + seed (`INSERT IGNORE`, deci rulează `$PHP database/seed.php` ca să apară pe baza locală) + trei câmpuri `input type="text"` în `admin/setari.twig` (etichete: „Adresa (apare în subsol și în Contact rapid)", „Telefon", „Email public"). Rulează `tests/admin_setari_utilizatori_test.php` după.

`PaginiController::pagina()` — la randarea șablonului contact adaugă `$vars['form'] = ['erori' => [], 'valori' => []]; $vars['time_token'] = \App\Form\TimeToken::mint(); $vars['trimis'] = isset($request->getQueryParams()['trimis']);`.

`src/Public/ContactController.php`:

```php
<?php
declare(strict_types=1);

namespace App\Public;

use App\Form\TimeToken;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

final class ContactController
{
    private Context $ctx;

    public function __construct(private Twig $twig, private array $container)
    {
        $this->ctx = $container['context'];
    }

    public function trimite(Request $request, Response $response, array $args): Response
    {
        $s = $this->ctx->sectiune($args['perioada']) ?? throw new HttpNotFoundException($request);
        $arbore = $this->ctx->arbore($s);
        $g = $this->ctx->gaseste($arbore, $args['slug']);
        if ($g === null || $g['nod']['tip'] !== 'pagina' || ($g['nod']['sablon'] ?? '') !== 'contact') {
            throw new HttpNotFoundException($request);
        }
        $in = (array) $request->getParsedBody();
        $valori = ['nume' => trim((string) ($in['nume'] ?? '')), 'email' => trim((string) ($in['email'] ?? '')), 'mesaj' => trim((string) ($in['mesaj'] ?? ''))];
        $url = $this->ctx->base() . '/' . $s['slug'] . '/' . $g['nod']['slug'];
        $succes = $response->withHeader('Location', $url . '?trimis=1#formular')->withStatus(302);

        $erori = [];
        $csrf = (string) ($in['_csrf'] ?? '');
        if ($csrf === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $csrf)) {
            $erori['_'] = 'Sesiunea a expirat. Reîncarcă pagina și trimite din nou.';
        }
        // Bot: răspuns identic cu succesul, fără salvare.
        if ($erori === [] && (((string) ($in['website'] ?? '')) !== '' || !TimeToken::isValidAndAged((string) ($in['_t'] ?? '')))) {
            return $succes;
        }
        if (mb_strlen($valori['nume']) < 2 || mb_strlen($valori['nume']) > 120) { $erori['nume'] = 'Scrie-ți numele (2–120 caractere).'; }
        if (!filter_var($valori['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($valori['email']) > 190) { $erori['email'] = 'Adresa de email nu pare validă.'; }
        if (mb_strlen($valori['mesaj']) < 10 || mb_strlen($valori['mesaj']) > 5000) { $erori['mesaj'] = 'Mesajul trebuie să aibă între 10 și 5000 de caractere.'; }
        if ($erori !== []) {
            $rand = $this->container['meniu']->gaseste((int) $g['nod']['id']);
            $vars = $this->ctx->variabile($s, '/' . $s['slug'] . '/' . $g['nod']['slug'], [
                'nod' => $g['nod'], 'rand' => $rand, 'stramosi' => $g['stramosi'], 'ramura' => $g['stramosi'][0] ?? $g['nod'],
                'activ' => array_map(fn($n) => (int) $n['id'], [...$g['stramosi'], $g['nod']]),
                'galerii' => [], 'form' => ['erori' => $erori, 'valori' => $valori], 'time_token' => TimeToken::mint(), 'trimis' => false,
            ]);
            $vars['arbore'] = $arbore;
            return $this->twig->render($response, 'sectiune/contact.twig', $vars);
        }

        $pdo = $this->container['db']->pdo();
        $pdo->prepare('INSERT INTO mesaje_contact (sectiune_id, nume, email, mesaj, ip_hash) VALUES (:s, :n, :e, :m, :ip)')
            ->execute(['s' => $s['id'], 'n' => $valori['nume'], 'e' => $valori['email'], 'm' => $valori['mesaj'], 'ip' => ip_hash($request->getServerParams()['REMOTE_ADDR'] ?? null)]);
        $id = (int) $pdo->lastInsertId();

        $catre = $this->container['setari']->get('contact_email_destinatar') ?: $this->container['mailer']->adminAddress();
        $corp = '<p><strong>Nume:</strong> ' . e($valori['nume']) . '<br><strong>Email:</strong> ' . e($valori['email']) . '<br><strong>Secțiunea:</strong> ' . e($s['titlu']) . '</p><p>' . nl2br(e($valori['mesaj'])) . '</p>';
        if ($this->container['mailer']->send($catre, 'Mesaj din formularul de contact — ' . $s['titlu'], $corp, $valori['email'])) {
            $pdo->prepare('UPDATE mesaje_contact SET email_trimis = 1 WHERE id = :id')->execute(['id' => $id]);
        }
        return $succes;
    }
}
```

Routes: `$app->post('/{perioada:[0-9]{4}-[0-9]{4}}/{slug:[a-z0-9-]+}', fn($rq, $rs, $a) => (new \App\Public\ContactController($twig, $container))->trimite($rq, $rs, $a));`

Blocul `formular` din `sectiune/contact.twig`:

```twig
{% block formular %}
{% if trimis %}<div class="alert alert-success" role="status">Mesajul a fost trimis. Îți mulțumim — revenim cât de curând.</div>{% endif %}
{% if form.erori._ is defined %}<div class="alert alert-danger" role="alert">{{ form.erori._ }}</div>{% endif %}
<form method="post" action="{{ base }}/{{ sectiune.slug }}/{{ nod.slug }}#formular" novalidate>
    <input type="hidden" name="_csrf" value="{{ csrf }}">
    <input type="hidden" name="_t" value="{{ time_token }}">
    <div class="fp-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="c-nume">Nume</label>
            <input class="form-control{% if form.erori.nume is defined %} is-invalid{% endif %}" id="c-nume" name="nume" type="text" value="{{ form.valori.nume|default('') }}" required maxlength="120">
            {% if form.erori.nume is defined %}<div class="invalid-feedback">{{ form.erori.nume }}</div>{% endif %}
        </div>
        <div class="col-md-6">
            <label class="form-label" for="c-email">Email</label>
            <input class="form-control{% if form.erori.email is defined %} is-invalid{% endif %}" id="c-email" name="email" type="email" value="{{ form.valori.email|default('') }}" required maxlength="190">
            {% if form.erori.email is defined %}<div class="invalid-feedback">{{ form.erori.email }}</div>{% endif %}
        </div>
        <div class="col-12">
            <label class="form-label" for="c-mesaj">Mesaj</label>
            <textarea class="form-control{% if form.erori.mesaj is defined %} is-invalid{% endif %}" id="c-mesaj" name="mesaj" rows="6" required maxlength="5000">{{ form.valori.mesaj|default('') }}</textarea>
            {% if form.erori.mesaj is defined %}<div class="invalid-feedback">{{ form.erori.mesaj }}</div>{% endif %}
        </div>
        <div class="col-12"><button class="fp-btn" type="submit">Trimite mesajul</button></div>
    </div>
</form>
{% endblock %}
```

CSS: `.fp-hp { position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden }`.

- [ ] **Step 4: Rulează** `public_contact_test.php`, `public_pagina_test.php`, `admin_setari_utilizatori_test.php` → OK.

- [ ] **Step 5: Commit** — `M3: formular de contact (csrf, honeypot, time token, mail), setari de contact`.

---

### Task 7: SEO (sitemap, robots), capturi, verificare finală, documentație

**Files:**
- Create: `src/Public/SeoController.php`, `templates/sitemap.twig`, `tests/public_seo_test.php`
- Modify: `src/Routes.php`, `tests/capturi.mjs` (paginile publice), `CLAUDE.md` (stadiu: Plan 3 gata; comenzi noi), `README.md` (secțiunea „Sit public" dacă există listă de rute)

**Interfaces:**
- `SeoController::sitemap(Request, Response)` — XML cu: `/`, `/{slug}/` per secțiune, și, pentru fiecare nod din `Context::arbore()` (recursiv) cu `tip in [pagina, dosar, galerie]`, `<loc>` = `app.url + href`, `<lastmod>` = `modificat_la` ca `Y-m-d`. Fără `document`/`link`. `Content-Type: application/xml; charset=utf-8`.
- `SeoController::robots(Request, Response)` — text:

```
User-agent: *
Disallow: /admin
Disallow: /fisiere/mini/
Sitemap: {app.url}/sitemap.xml
```

- [ ] **Step 1: Testul** `tests/public_seo_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$pdo = pdo();
$s = $pdo->query("SELECT * FROM sectiuni WHERE slug='2021-2027'")->fetch();
$m = strtolower('Seo' . bin2hex(random_bytes(3)));
$ids = []; $fid = null;
try {
    $pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES ("d.pdf", :c, "application/pdf", 1)')->execute(['c' => "2026/09/d-$m.pdf"]);
    $fid = (int) $pdo->lastInsertId();
    $ins = $pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, fisier_id, vizibil) VALUES (:s, :p, 999, :t, :sl, :tip, :f, :v)');
    $ins->execute(['s' => $s['id'], 'p' => null, 't' => "Dosar $m", 'sl' => "dosar-$m", 'tip' => 'dosar', 'f' => null, 'v' => 1]); $d = (int) $pdo->lastInsertId(); $ids[] = $d;
    $ins->execute(['s' => $s['id'], 'p' => $d, 't' => "Pag $m", 'sl' => "pag-$m", 'tip' => 'pagina', 'f' => null, 'v' => 1]); $ids[] = (int) $pdo->lastInsertId();
    $ins->execute(['s' => $s['id'], 'p' => $d, 't' => "Doc $m", 'sl' => "doc-$m", 'tip' => 'document', 'f' => $fid, 'v' => 1]); $ids[] = (int) $pdo->lastInsertId();
    $ins->execute(['s' => $s['id'], 'p' => $d, 't' => "Asc $m", 'sl' => "asc-$m", 'tip' => 'pagina', 'f' => null, 'v' => 0]); $ids[] = (int) $pdo->lastInsertId();

    $r = cerere('GET', '/sitemap.xml'); $c = corp($r);
    ok('sitemap => 200 xml', $r->getStatusCode() === 200 && str_starts_with($r->getHeaderLine('Content-Type'), 'application/xml') && str_starts_with(trim($c), '<?xml'));
    ok('  landing + secțiuni', str_contains($c, '<loc>http://flagprahova.test/</loc>') && str_contains($c, '<loc>http://flagprahova.test/2021-2027/</loc>') && str_contains($c, '<loc>http://flagprahova.test/2014-2020/</loc>'));
    ok('  dosar + pagină, cu lastmod', str_contains($c, "<loc>http://flagprahova.test/2021-2027/dosar-$m</loc>") && str_contains($c, "<loc>http://flagprahova.test/2021-2027/pag-$m</loc>") && preg_match('#<lastmod>\d{4}-\d{2}-\d{2}</lastmod>#', $c) === 1);
    ok('  fără document / invizibil', !str_contains($c, "doc-$m") && !str_contains($c, "asc-$m"));
    ok('  XML valid', simplexml_load_string($c) !== false);

    $r = cerere('GET', '/robots.txt'); $c = corp($r);
    ok('robots', $r->getStatusCode() === 200 && str_contains($c, 'Disallow: /admin') && str_contains($c, 'Sitemap: http://flagprahova.test/sitemap.xml'));

    $r = cerere('GET', '/admin/login');
    ok('adminul e noindex', str_contains(corp($r), 'noindex'));
} finally {
    if ($ids) { $pdo->exec('DELETE FROM meniu WHERE id IN (' . implode(',', array_reverse($ids)) . ')'); }
    if ($fid) { $pdo->exec("DELETE FROM fisiere WHERE id = $fid"); }
}
final_test();
```

- [ ] **Step 2: Rulează, pică.**

- [ ] **Step 3: Implementarea**

```php
<?php
declare(strict_types=1);

namespace App\Public;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class SeoController
{
    private Context $ctx;

    public function __construct(private Twig $twig, private array $container)
    {
        $this->ctx = $container['context'];
    }

    public function sitemap(Request $request, Response $response): Response
    {
        $url = (string) $this->container['settings']['app']['url'];
        $intrari = [['loc' => $url . '/', 'lastmod' => null]];
        $aduna = function (array $noduri) use (&$aduna, &$intrari, $url): void {
            foreach ($noduri as $n) {
                if (in_array($n['tip'], ['pagina', 'dosar', 'galerie'], true)) {
                    $intrari[] = ['loc' => $url . $n['href'], 'lastmod' => substr((string) $n['modificat_la'], 0, 10)];
                }
                $aduna($n['copii']);
            }
        };
        foreach ($this->ctx->sectiuni() as $s) {
            $intrari[] = ['loc' => $url . '/' . $s['slug'] . '/', 'lastmod' => null];
            $aduna($this->ctx->arbore($s));
        }
        $response = $this->twig->render($response, 'sitemap.twig', ['intrari' => $intrari]);
        return $response->withHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    public function robots(Request $request, Response $response): Response
    {
        $url = (string) $this->container['settings']['app']['url'];
        $response->getBody()->write("User-agent: *\nDisallow: /admin\nDisallow: /fisiere/mini/\nSitemap: {$url}/sitemap.xml\n");
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
```

`templates/sitemap.twig`:

```twig
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{% for i in intrari %}<url><loc>{{ i.loc }}</loc>{% if i.lastmod %}<lastmod>{{ i.lastmod }}</lastmod>{% endif %}</url>
{% endfor %}</urlset>
```

Routes: `$seo = fn() => new \App\Public\SeoController($twig, $container); $app->get('/sitemap.xml', fn($rq, $rs) => $seo()->sitemap($rq, $rs)); $app->get('/robots.txt', fn($rq, $rs) => $seo()->robots($rq, $rs));`

Notă: `Context::arbore()` pune `href` cu `base`; `app.url` nu include `base_path`, deci pe un staging în subfolder `loc` trebuie să fie `url + href` DOAR dacă `app.url` nu conține deja base-ul — pe cPanel staging `APP_URL=https://flagprahova.ro/nou` și `BASE_PATH=/nou` ar dubla `/nou`. Rezolvă simplu: în `sitemap()`, `$url = rtrim(app.url, '/')` și `href` fără `base`: `substr($n['href'], strlen($this->ctx->base()))`.

- [ ] **Step 4: Capturi** — în `tests/capturi.mjs` înlocuiește lista `pagini` cu:

```js
const pagini = [
  ['landing', '/', 1366], ['landing-mobil', '/', 390],
  ['acasa-2021', '/2021-2027/', 1366], ['acasa-2021-mobil', '/2021-2027/', 390],
  ['acasa-2014', '/2014-2020/', 1366],
  ['pagina-cooperare', '/2014-2020/cooperare', 1366], ['pagina-cooperare-mobil', '/2014-2020/cooperare', 390],
  ['dosar-arhiva', '/2014-2020/arhiva', 1366],
  ['contact', '/2021-2027/contact', 1366],
  ['eroare-404', '/2021-2027/nu-exista', 1366],
  ['admin-login', '/admin/login', 1366],
];
```

și `--window-size=${latime},1400` (înălțime mai mare, să prindă și footerul pe landing). Rulează `node tests/capturi.mjs` și UITĂ-TE la capturi (Read pe PNG-uri): meniul nu se suprapune cu logo-ul, dropdown-urile nu sunt tăiate, footerul are logo-urile, mobil e lizibil (~500px real, vezi CLAUDE.md). Corectează CSS-ul până arată bine; capturile NU se commit-uie (`storage/shots/` e gitignored).

- [ ] **Step 5: Verificare finală**

```bash
PHP="C:/laragon/bin/php/php-8.3.31-nts-Win32-vs16-x64/php.exe"
for i in 1 2; do for t in tests/*_test.php; do "$PHP" "$t" > /dev/null || echo "FAIL: $t"; done; done
"$PHP" database/verifica_migrare.php; echo "exit $?"
```

Toate trec, de două ori, `verifica_migrare` exit 0. Apoi `curl -s -o /dev/null -w '%{http_code}' http://flagprahova.test/2014-2020/cooperare` = 200 prin Apache, și un URL WP vechi = 404.

- [ ] **Step 6: Documentație** — `CLAUDE.md`: stadiul devine „Plan 3 (sit public) mergeuit; urmează Plan 4 = staging/lansare"; la Convenții: rutele publice și `Public\Context` (o linie), fonturile self-hosted + `scripts/descarca_fonturi.php`, setările de contact noi, `fisiere/mini/`. `README.md`: secțiune scurtă „Sit public" cu rutele din spec §4.

- [ ] **Step 7: Commit** — `M3: sitemap, robots, capturi publice, documentatie`.

---

## Self-review (făcut la scriere)

- Spec §4: toate rutele au task (landing T2, acasă T3, pagină/dosar/galerie/document/link T4, contact T6, sitemap/robots T7, 404 T2, miniaturi T5). `/fisiere/...` rămâne static (Apache).
- Spec §6 (revizuit): paletă/fonturi/ilustrație/logo-uri T1; header cu bandă logo-uri + meniu 3 niveluri + comutator + offcanvas T2; acasă cu hero/despre/noutăți/carduri/CTA T3; sidebar + rânduri documente + acordeon galerii T4; lightbox T1 (JS) + T5 (miniaturi); footer T2.
- Spec §8: CSRF + honeypot + TimeToken + salvare înainte de mail T6; 404 real T2.
- Spec §9: rute 200, meniu 3 niveluri, dosar gol, sitemap fără invizibile, capturi — acoperite.
- Reguli globale: Bootstrap local, OG/twitter/canonical/description în layout (T2), pretty URL, `noindex` pe 404/admin, JSON-LD Organization (landing) + BreadcrumbList (pagini), un singur `h1` (testat).
- Nume consistente: `Context::variabile/arbore/gaseste/href/sectiune/sectiuni/base`, `arborePublic`, funcții Twig `marime/ext/mini/url_public`, chei `noutati/nivel1/contact_href/nod/rand/stramosi/ramura/activ/galerii/imagini/form/time_token/trimis`.
