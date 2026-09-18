# FLAG Prahova — Plan 2: migrarea conținutului din WordPress

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Baza `flagprahova` populată automat și repetabil cu tot conținutul sitului vechi: fișierele din arhivă în `/fisiere/`, arborele de meniu al perioadei 2014-2020, meniul de pornire al perioadei 2021-2027, paginile cu conținut curățate de shortcode-uri și spam, galeriile din Cooperare, paginile de contact și textele „Acasă”.

**Architecture:** Trei unități pure + două scripturi CLI. `App\Migrare\Legacy` citește baza WordPress (a doua conexiune PDO, read-only). `App\Migrare\Curata` transformă HTML-ul vechi (shortcode-uri VC, blocuri Gutenberg, spam, căi `/wp-content/uploads/`) în HTML sanitizat prin `Support\Html::curata`. `database/import_fisiere.php` dezarhivează uploads-urile în `/fisiere/AAAA/LL/` și le înregistrează în `fisiere` (cheie `legacy_url`). `database/migrate_wp.php` construiește arborele în `meniu` (cheie `legacy_id`) și e idempotent. `database/verifica_migrare.php` tipărește raportul final.

**Tech Stack:** PHP 8.1+ (CLI Laragon 8.3), PDO/MySQL, `ZipArchive`, `DOMDocument`, `smalot/pdfparser` (doar pentru extragerea textului SDL, dev), harness-ul de test din `tests/_bootstrap.php`.

**Spec:** `docs/superpowers/specs/2026-09-18-flagprahova-site-nou-design.md` — §7 (Migrare), plus §3 (model de date) și §8 (sanitizare).

**Date sursă (existente local):**
- Dump WP importat în baza locală `flagprahova_wp_old`, prefix `wpt9_` (creat din `materiale/arhiva/flagprah_wp25.sql`). Dacă lipsește: `$MYSQL -e "CREATE DATABASE flagprahova_wp_old CHARACTER SET utf8mb4"` apoi `$MYSQL flagprahova_wp_old -e "source materiale/arhiva/flagprah_wp25.sql"`.
- Arhiva `materiale/arhiva/public_html-26august2026.zip` (2,9 GB; `wp-content/uploads/AAAA/LL/…`, ~4000 fișiere).
- Meniul vechi: 231 rânduri `nav_menu_item` (meniu `Meniu FLAG`, term 15), 3 niveluri, rădăcinile în `menu_order`: 176 (pagina 75 Acasă), 250 Noutăți, 204 Strategie, 2984 (pagina 2899 Cooperare), 217 Apel lansare, 251 Arhivă, 268 Media, 255 (pagina 252 Utile), 175 (pagina 156 Contact).
- Pagini cu conținut în `post_content`: 75 Acasă, 567 Măsura 1, 575 Măsura 2, 2899 Cooperare (shortcode-uri `[vc_*]`, 14 galerii `vc_tta_section`+`vc_gallery`), 3534 și 3559 (blocuri Gutenberg `wp:file`/`wp:paragraph`), 803 Anunț de angajare, 277 (doar spam). Pagini cu conținut în `wpt9_postmeta.meta_key='_variant_page_builder_html'`: 156 Contact, 225 Legislație, 252 Utile (rețete cu 7 imagini), 269 Comunicat Telegrama. Pagini goale cu copii în meniu: 2714. Pagini goale fără copii: 307, 288, 396, 362, 384, 2528, 2577, 2581, 2588, 2592.
- Spam: categorii `wpt9_terms` fără posturi (ignorate) și text injectat în pagini (ex. „best-ghostwriter.com”, „paper writing service”).

## Global Constraints

- PHP ≥ 8.1 în cod; fără biblioteci JS noi; `smalot/pdfparser` intră în `require-dev`.
- SQL scris de mână, prepared statements native (placeholdere distincte, `LIMIT` inline `(int)`), `utf8mb4` pe ambele conexiuni.
- Idempotență: `import_fisiere.php` pe `fisiere.cale` (upsert), `migrate_wp.php` pe `meniu.legacy_id` (upsert), re-rularea nu duplică nimic și nu șterge intrări adăugate de client din admin.
- Orice HTML scris în `meniu.continut_html` sau `sectiuni.acasa_html` trece prin `App\Support\Html::curata()`.
- Fișierele migrate păstrează structura `AAAA/LL/` și numele normalizat prin `App\Fisiere\Upload::numeSigur()`; `nume_afisat` = numele original (url-decodat); `legacy_url` = calea relativă la `wp-content/uploads/` exact cum apare în WP (ex. `2017/08/POESP R1.pdf`).
- Nu se creează redirecturi legacy (decizie 2026-09-18).
- Scripturile scriu raport la `stdout` și nu aruncă pe date lipsă: linkuri rupte și fișiere lipsă se contorizează și se listează la final.
- Comenzi: `PHP="C:/laragon/bin/php/php-8.3.31-nts-Win32-vs16-x64/php.exe"`, `MYSQL="C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysql.exe" -u root --default-character-set=utf8mb4`. Fără diacritice pe linia de comandă `mysql.exe`.
- Commit după fiecare task; fără push (Daniel decide).

## Structura de fișiere

| Fișier | Responsabilitate |
|---|---|
| `config/settings.php` (+ `.env.example`) | secțiunea `db_wp` (`DB_WP_NAME=flagprahova_wp_old`) |
| `src/Migrare/Legacy.php` | citire WP: meniu cu meta, pagini, builder html, atașamente |
| `src/Migrare/Curata.php` | HTML vechi → HTML curat: shortcode-uri VC, blocuri Gutenberg, spam, rescriere `/wp-content/uploads/`, extragere galerii |
| `src/Migrare/Harta.php` | regulile de mapare meniu → `meniu` (secțiune, tip, părinte, ordine) — pură, testabilă |
| `database/import_fisiere.php` | zip → `/fisiere/` + `fisiere` |
| `database/migrate_wp.php` | construiește arborele + paginile + galeriile + contact + acasă |
| `database/verifica_migrare.php` | raport: numărări pe tip/secțiune, documente fără fișier, linkuri externe rămase |
| `database/reset_continut.php` | DEV: golește `meniu`, `galerie_imagini`, `fisiere` (cu `--da`) |
| `database/data/acasa-2021-2027.html`, `contact-2014-2020.html`, `contact-2021-2027.html` | conținut curat scris de mână (sursa: builder html vechi + SDL) |
| `tests/migrare_*_test.php` | teste per unitate + end-to-end pe baza WP reală |

---

### Task 1: A doua conexiune (WP) și `App\Migrare\Legacy`

**Files:**
- Create: `src/Migrare/Legacy.php`, `tests/migrare_legacy_test.php`
- Modify: `config/settings.php` (secțiunea `db_wp`), `.env.example` și `.env` (`DB_WP_NAME=flagprahova_wp_old`), `src/Bootstrap.php` (`$container['legacy']` doar dacă `DB_WP_NAME` e setat)

**Interfaces:**
- Produces `App\Migrare\Legacy::__construct(PDO $pdo, string $prefix = 'wpt9_')` și:
  - `meniu(): array` — toate `nav_menu_item` publicate din meniul „Meniu FLAG”, ordonate după `menu_order`, fiecare `{id:int, ordine:int, parent:int, tip:'custom'|'post_type', obiect_id:int, titlu:string, url:string}`; `titlu` = `post_title` sau, dacă e gol și `tip='post_type'`, titlul paginii; `url` = `_menu_item_url` url-decodat, spații normalizate.
  - `pagina(int $id): ?array` — `{id, titlu, slug, continut:string}` unde `continut` = `post_content` dacă e nevid după `trim`, altfel `_variant_page_builder_html`, altfel `''`.
  - `atasamentCale(int $id): ?string` — `_wp_attached_file` (ex. `2022/10/Instruire_01_Busteni.jpg`).
  - `atasamenteCai(array $ids): array` — `[id => cale]` pentru o listă.
  - Static `caleDinUrl(string $url): ?string` — din `/wp-content/uploads/2017/08/POESP%20R1.pdf`, `http://www.flagprahova.ro/wp-content/uploads/…` sau `https://…` întoarce `2017/08/POESP R1.pdf` (url-decodat); `null` dacă nu e un fișier sub uploads (fără extensie, folder, `#`, `http://ab`).

- [ ] **Step 1: Testul**

`tests/migrare_legacy_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Migrare\Legacy;

$s = settings();
ok('settings are db_wp.name', ($s['db_wp']['name'] ?? '') === 'flagprahova_wp_old');
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $s['db_wp']['host'], $s['db_wp']['port'], $s['db_wp']['name']), $s['db_wp']['user'], $s['db_wp']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$l = new Legacy($pdo);

$m = $l->meniu();
ok('meniu(): 231 intrări', count($m) === 231);
$byId = array_column($m, null, 'id');
ok('  579 Organigrama: custom, parent 237, url decodat', $byId[579]['tip'] === 'custom' && $byId[579]['parent'] === 237 && $byId[579]['url'] === '/wp-content/uploads/2017/06/Organigrama.pdf');
ok('  243 url cu %20 decodat', $byId[243]['url'] === '/wp-content/uploads/2021/09/POESP - R3.pdf');
ok('  176 e pagina 75 cu titlul paginii', $byId[176]['tip'] === 'post_type' && $byId[176]['obiect_id'] === 75 && $byId[176]['titlu'] === 'Acasă');
ok('  250 Noutăți rădăcină', $byId[250]['parent'] === 0 && $byId[250]['titlu'] === 'Noutăți');
ok('  ordonat după menu_order', $m[0]['ordine'] <= $m[1]['ordine'] && $m[1]['ordine'] <= $m[2]['ordine']);

$p = $l->pagina(75);
ok('pagina(75) din post_content', $p !== null && str_contains($p['continut'], 'contractului de finanțare'));
$p = $l->pagina(156);
ok('pagina(156) din builder html', $p !== null && str_contains($p['continut'], '0762 609 685'));
ok('pagina(307) goală => continut ""', ($l->pagina(307)['continut'] ?? 'x') === '');
ok('pagina(999999) => null', $l->pagina(999999) === null);

ok('atasamentCale(2903)', $l->atasamentCale(2903) === '2022/10/Instruire_01_Busteni.jpg');
ok('atasamenteCai', $l->atasamenteCai([2903, 2947]) === [2903 => '2022/10/Instruire_01_Busteni.jpg', 2947 => '2022/10/Culinar_01_Busteni.jpg']);

ok('caleDinUrl relativ', Legacy::caleDinUrl('/wp-content/uploads/2021/09/POESP%20-%20R3.pdf') === '2021/09/POESP - R3.pdf');
ok('caleDinUrl absolut http', Legacy::caleDinUrl('http://www.flagprahova.ro/wp-content/uploads/2017/08/Calendar%20Estimativ-2.jpg') === '2017/08/Calendar Estimativ-2.jpg');
ok('caleDinUrl folder => null', Legacy::caleDinUrl('/wp-content/uploads/2017/08/') === null);
ok('caleDinUrl # => null', Legacy::caleDinUrl('#') === null);
ok('caleDinUrl http://ab => null', Legacy::caleDinUrl('http://ab') === null);
ok('caleDinUrl extern => null', Legacy::caleDinUrl('https://example.com/x.pdf') === null);
ok('caleDinUrl gol => null', Legacy::caleDinUrl('') === null);
final_test();
```

- [ ] **Step 2: Rulează, pică** — `settings are db_wp.name` FAIL, apoi `Class "App\Migrare\Legacy" not found`.

- [ ] **Step 3: Config + Legacy**

În `config/settings.php` adaugă după `'db'`:

```php
    // Baza WordPress veche, DOAR pentru scripturile de migrare (read-only).
    'db_wp' => [
        'host' => $_ENV['DB_WP_HOST'] ?? ($_ENV['DB_HOST'] ?? '127.0.0.1'),
        'port' => $_ENV['DB_WP_PORT'] ?? ($_ENV['DB_PORT'] ?? '3306'),
        'name' => $_ENV['DB_WP_NAME'] ?? '',
        'user' => $_ENV['DB_WP_USER'] ?? ($_ENV['DB_USER'] ?? 'root'),
        'pass' => $_ENV['DB_WP_PASS'] ?? ($_ENV['DB_PASS'] ?? ''),
        'prefix' => $_ENV['DB_WP_PREFIX'] ?? 'wpt9_',
    ],
```

`.env.example` + `.env`: `DB_WP_NAME=flagprahova_wp_old` (cu comentariu „baza WP veche, doar pentru migrare; gol în producție”).

`src/Migrare/Legacy.php`:

```php
<?php
declare(strict_types=1);

namespace App\Migrare;

use PDO;

/** Citire read-only din baza WordPress veche. Nu scrie niciodată. */
final class Legacy
{
    public function __construct(private PDO $pdo, private string $prefix = 'wpt9_') {}

    /** Meniul „Meniu FLAG” (term_taxonomy nav_menu), plat, ordonat după menu_order. */
    public function meniu(): array
    {
        $p = $this->prefix;
        $sql = "SELECT i.ID id, i.menu_order ordine, i.post_title titlu_item,
                   MAX(CASE WHEN m.meta_key = '_menu_item_menu_item_parent' THEN m.meta_value END) parent,
                   MAX(CASE WHEN m.meta_key = '_menu_item_type' THEN m.meta_value END) tip,
                   MAX(CASE WHEN m.meta_key = '_menu_item_object_id' THEN m.meta_value END) obiect_id,
                   MAX(CASE WHEN m.meta_key = '_menu_item_url' THEN m.meta_value END) url
                FROM {$p}posts i
                JOIN {$p}term_relationships tr ON tr.object_id = i.ID
                JOIN {$p}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'nav_menu'
                JOIN {$p}postmeta m ON m.post_id = i.ID
                WHERE i.post_type = 'nav_menu_item' AND i.post_status = 'publish'
                GROUP BY i.ID, i.menu_order, i.post_title
                ORDER BY i.menu_order, i.ID";
        $out = [];
        foreach ($this->pdo->query($sql)->fetchAll() as $r) {
            $tip = (string) $r['tip'];
            $oid = (int) $r['obiect_id'];
            $titlu = trim((string) $r['titlu_item']);
            if ($titlu === '' && $tip === 'post_type') {
                $pg = $this->pagina($oid);
                $titlu = $pg['titlu'] ?? '';
            }
            $out[] = [
                'id'        => (int) $r['id'],
                'ordine'    => (int) $r['ordine'],
                'parent'    => (int) $r['parent'],
                'tip'       => $tip === 'post_type' ? 'post_type' : 'custom',
                'obiect_id' => $oid,
                'titlu'     => $titlu,
                'url'       => self::normalizeazaUrl((string) $r['url']),
            ];
        }
        return $out;
    }

    public function pagina(int $id): ?array
    {
        $p = $this->prefix;
        $st = $this->pdo->prepare("SELECT ID, post_title, post_name, post_content FROM {$p}posts WHERE ID = :id AND post_type = 'page' LIMIT 1");
        $st->execute(['id' => $id]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $continut = trim((string) $r['post_content']);
        if ($continut === '') {
            $st = $this->pdo->prepare("SELECT meta_value FROM {$p}postmeta WHERE post_id = :id AND meta_key = '_variant_page_builder_html' LIMIT 1");
            $st->execute(['id' => $id]);
            $continut = trim((string) ($st->fetchColumn() ?: ''));
        }
        return ['id' => (int) $r['ID'], 'titlu' => (string) $r['post_title'], 'slug' => (string) $r['post_name'], 'continut' => $continut];
    }

    public function atasamentCale(int $id): ?string
    {
        $r = $this->atasamenteCai([$id]);
        return $r[$id] ?? null;
    }

    /** @param int[] $ids @return array<int,string> */
    public function atasamenteCai(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return [];
        }
        $p = $this->prefix;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo->prepare("SELECT post_id, meta_value FROM {$p}postmeta WHERE meta_key = '_wp_attached_file' AND post_id IN ($in)");
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[(int) $r['post_id']] = (string) $r['meta_value'];
        }
        return $out;
    }

    /** `…/wp-content/uploads/2017/08/X%20Y.pdf` → `2017/08/X Y.pdf`; null dacă nu e un fișier sub uploads. */
    public static function caleDinUrl(string $url): ?string
    {
        $url = self::normalizeazaUrl($url);
        if (!preg_match('#^(?:https?://(?:www\.)?flagprahova\.ro)?/wp-content/uploads/(.+)$#i', $url, $m)) {
            return null;
        }
        $cale = trim($m[1]);
        if ($cale === '' || str_ends_with($cale, '/') || !preg_match('#\.[a-z0-9]{2,5}$#i', $cale)) {
            return null;
        }
        return $cale;
    }

    private static function normalizeazaUrl(string $url): string
    {
        $u = trim(rawurldecode($url));
        return preg_replace('/\s+/u', ' ', $u) ?? $u;
    }
}
```

În `src/Bootstrap::extinde()`:

```php
        if (($container['settings']['db_wp']['name'] ?? '') !== '') {
            $container['legacy'] = static function () use ($container): Migrare\Legacy {
                $c = $container['settings']['db_wp'];
                $pdo = new \PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']), $c['user'], $c['pass'], [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, \PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                return new Migrare\Legacy($pdo, (string) $c['prefix']);
            };
        }
```

(closure leneș: conexiunea la WP se deschide doar când un script o cere cu `($container['legacy'])()`.)

- [ ] **Step 4: Rulează testul** — toate PASS. Rulează și `tests/schelet_test.php` (Bootstrap neschimbat funcțional).

- [ ] **Step 5: Commit** — `git add config .env.example src/Migrare src/Bootstrap.php tests/migrare_legacy_test.php && git commit -m "M2: conexiune WP legacy + Migrare\Legacy (meniu, pagini, atasamente)"`

### Task 2: `App\Migrare\Curata` — HTML vechi → HTML curat, galerii extrase

**Files:**
- Create: `src/Migrare/Curata.php`, `tests/migrare_curata_test.php`

**Interfaces:**
- Produces `App\Migrare\Curata::__construct(callable $rezolvaCale)` unde `$rezolvaCale(string $caleLegacy): ?string` întoarce calea nouă relativă la `/fisiere/` (ex. `2017/08/peste-la-cuptor.jpg`) sau `null` dacă fișierul nu a fost importat.
- `Curata::proceseaza(string $html): array{html: string, galerii: array<int, array{titlu: string, ids: int[]}>, linkuri_rupte: string[], externe: string[], spam_eliminat: int}`:
  1. blocuri Gutenberg: scoate comentariile `<!-- wp:… -->`/`<!-- /wp:… -->`; un `wp:file` (`<div class="wp-block-file">…<object …></object><a href="X">Nume</a><a … download>Download</a></div>`) devine `<p><a href="X">Nume</a></p>` (fără `<object>`, fără al doilea link);
  2. shortcode-uri VC: `[vc_video link="https://www.youtube.com/watch?v=ID"]` → `<iframe src="https://www.youtube.com/embed/ID" width="560" height="315" allowfullscreen></iframe>`; fiecare `[vc_tta_section title="T" …]…[vc_gallery … images="1,2,3" …]…[/vc_tta_section]` e SCOS din HTML și adăugat în `galerii` ca `{titlu: T, ids: [1,2,3]}`; toate celelalte `[vc_*]`/`[/vc_*]`/`[vc_empty_space …]` dispar, conținutul dintre ele rămâne; `&nbsp;` între blocuri devine spațiu;
  3. shortcode-uri rămase de forma `[contact-form-7 …]` sau `[…]` necunoscute dispar;
  4. spam: se elimină `<a>` al căror `href` se potrivește cu `Curata::SPAM` (regex, vezi cod) și elementele `<p>`/`<li>`/`<div>` al căror text se potrivește cu `Curata::SPAM_TEXT`; fiecare eliminare incrementează `spam_eliminat`;
  5. rescriere: pentru fiecare `href`/`src` care e un fișier sub `wp-content/uploads` (`Legacy::caleDinUrl`), dacă `$rezolvaCale` întoarce o cale → `/fisiere/{cale}`; dacă `null` → linkul se păstrează ca text simplu (fără `<a>`) și URL-ul intră în `linkuri_rupte`; un `<img>` cu fișier lipsă dispare;
  6. linkuri externe (`http(s)://` către alt domeniu decât `flagprahova.ro`, `youtube.com`, `youtu.be`) rămân, dar host-urile intră în `externe` (pentru raport);
  7. clase/stiluri WP (`class="lead"`, `style=…`, `id=…`) dispar (le scoate oricum `Html::curata`); `<h1>` devine `<h2>` (pagina are un singur h1, titlul);
  8. rezultatul trece prin `App\Support\Html::curata()`.

- [ ] **Step 1: Testul**

`tests/migrare_curata_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Migrare\Curata;

$harta = ['2017/08/peste-la-cuptor.jpg' => '2017/08/peste-la-cuptor.jpg', '2022/06/Anexa A CF.docx' => '2022/06/anexa-a-cf.docx', '2023/12/Comunicat-SDL.pdf' => '2023/12/comunicat-sdl.pdf'];
$c = new Curata(fn(string $cale): ?string => $harta[$cale] ?? null);

// 1. Gutenberg wp:file
$r = $c->proceseaza('<!-- wp:file {"id":3538,"href":"https://www.flagprahova.ro/wp-content/uploads/2023/12/Comunicat-SDL.pdf"} -->' . "\n" . '<div class="wp-block-file"><object class="wp-block-file__embed" data="https://www.flagprahova.ro/wp-content/uploads/2023/12/Comunicat-SDL.pdf" type="application/pdf"></object><a id="x" href="https://www.flagprahova.ro/wp-content/uploads/2023/12/Comunicat-SDL.pdf">Comunicat-SDL</a><a href="https://www.flagprahova.ro/wp-content/uploads/2023/12/Comunicat-SDL.pdf" class="wp-block-file__button" download>Download</a></div>' . "\n" . '<!-- /wp:file --><!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->');
ok('wp:file => un singur link rescris', $r['html'] === '<p><a href="/fisiere/2023/12/comunicat-sdl.pdf">Comunicat-SDL</a></p><p>Text</p>');
ok('  fără comentarii wp:', !str_contains($r['html'], 'wp:'));

// 2. VC: video + galerii + shortcode-uri
$r = $c->proceseaza('[vc_row][vc_column][vc_column_text]<p>Titlu proiect</p>&nbsp;<p>A</p>[/vc_column_text][vc_empty_space height="50px"][vc_video link="https://www.youtube.com/watch?v=o6dPJ2CLD5g"][/vc_column][/vc_row][vc_row][vc_column][vc_column_text]<h3>GALERII FOTO</h3>[/vc_column_text][vc_tta_accordion][vc_tta_section title="Instruire vanzari" tab_id="a"][vc_gallery interval="3" images="2903,2904,2905" img_size="full"][/vc_tta_section][vc_tta_section title="Curs Culinar" tab_id="b"][vc_gallery images="2947" img_size="full"][/vc_tta_section][/vc_tta_accordion][/vc_column][/vc_row]');
ok('VC: text păstrat, shortcode-uri scoase', str_contains($r['html'], '<p>Titlu proiect</p>') && !str_contains($r['html'], '[vc_'));
ok('VC: video => iframe embed', str_contains($r['html'], '<iframe src="https://www.youtube.com/embed/o6dPJ2CLD5g"'));
ok('VC: 2 galerii extrase', count($r['galerii']) === 2 && $r['galerii'][0] === ['titlu' => 'Instruire vanzari', 'ids' => [2903, 2904, 2905]] && $r['galerii'][1]['ids'] === [2947]);
ok('VC: secțiunile de galerie nu rămân în html', !str_contains($r['html'], 'Instruire vanzari'));

// 3. shortcode necunoscut
ok('contact-form-7 dispare', $c->proceseaza('<p>A</p>[contact-form-7 title="" id="159"]<p>B</p>')['html'] === '<p>A</p><p>B</p>');

// 4. spam
$r = $c->proceseaza('<p>Ghid</p><p>Do a virtual book tour and get on some paper writing service podcasts.</p><p>Daher <a href="https://best-ghostwriter.com/">best-ghostwriter.com</a> die informationen.</p><ul><li><a href="/wp-content/uploads/2022/06/Anexa%20A%20CF.docx">Anexa A</a></li></ul>');
ok('spam: paragrafele cu text spam dispar', !str_contains($r['html'], 'paper writing') && !str_contains($r['html'], 'ghostwriter'));
ok('spam: contor', $r['spam_eliminat'] >= 2);
ok('spam: conținutul bun rămâne, link rescris', str_contains($r['html'], '<p>Ghid</p>') && str_contains($r['html'], 'href="/fisiere/2022/06/anexa-a-cf.docx"'));

// 5. fișier lipsă + img
$r = $c->proceseaza('<p><a href="/wp-content/uploads/2019/05/Lipsa.pdf">Lipsă</a> <img src="/wp-content/uploads/2017/08/peste-la-cuptor.jpg" alt="x"> <img src="/wp-content/uploads/2017/08/nu-exista.jpg"></p>');
ok('lipsă: linkul devine text, în linkuri_rupte', !str_contains($r['html'], '<a') && str_contains($r['html'], 'Lipsă') && $r['linkuri_rupte'] === ['/wp-content/uploads/2019/05/Lipsa.pdf', '/wp-content/uploads/2017/08/nu-exista.jpg']);
ok('lipsă: img existent rescris, img lipsă scos', substr_count($r['html'], '<img') === 1 && str_contains($r['html'], 'src="/fisiere/2017/08/peste-la-cuptor.jpg"'));

// 6. externe + 7. h1 + clase
$r = $c->proceseaza('<h1 class="t">Rețete</h1><p class="lead" style="x">A <a href="https://www.madr.ro/x" target="_blank">MADR</a></p>');
ok('h1 => h2, fără clase', str_starts_with($r['html'], '<h2>Rețete</h2><p>A '));
ok('extern păstrat, host raportat', str_contains($r['html'], 'href="https://www.madr.ro/x"') && $r['externe'] === ['www.madr.ro']);
ok('gol => gol', $c->proceseaza('')['html'] === '');
final_test();
```

- [ ] **Step 2: Rulează, pică** — `Class "App\Migrare\Curata" not found`.

- [ ] **Step 3: Implementarea**

`src/Migrare/Curata.php`:

```php
<?php
declare(strict_types=1);

namespace App\Migrare;

use App\Support\Html;
use DOMDocument;
use DOMElement;
use DOMXPath;

final class Curata
{
    public const SPAM = '#ghostwriter|essay|paper[- ]?writ|payday|casino|brides|dating|hookup|cbd|resume[- ]help|loans?\b#i';
    public const SPAM_TEXT = '#ghostwriter|essay|paper writing|write my|payday|casino|brides|hookup|informationen angeordnet#i';
    private const DOMENII_PROPRII = ['flagprahova.ro', 'www.flagprahova.ro', 'youtube.com', 'www.youtube.com', 'youtu.be', 'www.youtube-nocookie.com'];

    /** @var callable(string):?string */
    private $rezolvaCale;

    public function __construct(callable $rezolvaCale)
    {
        $this->rezolvaCale = $rezolvaCale;
    }

    public function proceseaza(string $html): array
    {
        $galerii = [];
        $rupte = [];
        $externe = [];
        $spam = 0;

        // 1. Gutenberg
        $html = preg_replace('#<!--\s*/?wp:[^>]*-->#s', '', $html) ?? $html;
        $html = preg_replace_callback('#<div class="wp-block-file">(.*?)</div>#s', static function (array $m): string {
            if (preg_match('#<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>#s', $m[1], $a)) {
                return '<p><a href="' . $a[1] . '">' . strip_tags($a[2]) . '</a></p>';
            }
            return '';
        }, $html) ?? $html;

        // 2. VC
        $html = preg_replace_callback('#\[vc_tta_section\b([^\]]*)\](.*?)\[/vc_tta_section\]#s', static function (array $m) use (&$galerii): string {
            $titlu = preg_match('#title="([^"]*)"#', $m[1], $t) ? html_entity_decode($t[1], ENT_QUOTES, 'UTF-8') : 'Galerie';
            if (preg_match('#\[vc_gallery\b[^\]]*images="([0-9,\s]+)"#', $m[2], $g)) {
                $ids = array_values(array_filter(array_map('intval', explode(',', $g[1]))));
                $galerii[] = ['titlu' => $titlu, 'ids' => $ids];
                return '';
            }
            return $m[2];
        }, $html) ?? $html;
        $html = preg_replace_callback('#\[vc_video\b[^\]]*link="([^"]+)"[^\]]*\]#', static function (array $m): string {
            if (preg_match('#(?:v=|youtu\.be/|embed/)([A-Za-z0-9_-]{6,})#', $m[1], $v)) {
                return '<iframe src="https://www.youtube.com/embed/' . $v[1] . '" width="560" height="315" allowfullscreen></iframe>';
            }
            return '';
        }, $html) ?? $html;
        $html = preg_replace('#\[/?vc_[a-z_]+\b[^\]]*\]#', '', $html) ?? $html;
        // 3. alte shortcode-uri
        $html = preg_replace('#\[/?[a-z][a-z0-9_-]*(?:\s[^\]]*)?\]#', '', $html) ?? $html;
        $html = str_replace(['&nbsp;', "\u{A0}"], ' ', $html);

        // 4–7 pe DOM
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="radacina">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);
        $rad = $dom->getElementById('radacina');
        if ($rad === null) {
            return ['html' => '', 'galerii' => $galerii, 'linkuri_rupte' => [], 'externe' => [], 'spam_eliminat' => 0];
        }

        // 4. spam: linkuri, apoi blocuri
        foreach (iterator_to_array($xp->query('.//a[@href]', $rad)) as $a) {
            if (preg_match(self::SPAM, $a->getAttribute('href'))) {
                $a->parentNode?->removeChild($a);
                $spam++;
            }
        }
        foreach (iterator_to_array($xp->query('.//p|.//li|.//div', $rad)) as $el) {
            if ($el->parentNode !== null && preg_match(self::SPAM_TEXT, $el->textContent)) {
                $el->parentNode->removeChild($el);
                $spam++;
            }
        }

        // 5–6. href/src
        foreach (iterator_to_array($xp->query('.//a[@href]|.//img[@src]', $rad)) as $el) {
            /** @var DOMElement $el */
            $atr = $el->tagName === 'img' ? 'src' : 'href';
            $url = $el->getAttribute($atr);
            $cale = Legacy::caleDinUrl($url);
            if ($cale !== null) {
                $noua = ($this->rezolvaCale)($cale);
                if ($noua !== null) {
                    $el->setAttribute($atr, '/fisiere/' . $noua);
                } else {
                    $rupte[] = $url;
                    if ($el->tagName === 'img') {
                        $el->parentNode?->removeChild($el);
                    } else {
                        $text = $dom->createTextNode($el->textContent);
                        $el->parentNode?->replaceChild($text, $el);
                    }
                }
                continue;
            }
            if (preg_match('#^https?://([^/]+)#i', $url, $h)) {
                $host = strtolower($h[1]);
                if (!in_array($host, self::DOMENII_PROPRII, true) && !in_array($host, $externe, true)) {
                    $externe[] = $host;
                }
            }
        }

        // 7. h1 → h2
        foreach (iterator_to_array($xp->query('.//h1', $rad)) as $h1) {
            $h2 = $dom->createElement('h2');
            while ($h1->firstChild) { $h2->appendChild($h1->firstChild); }
            $h1->parentNode?->replaceChild($h2, $h1);
        }

        $out = '';
        foreach ($rad->childNodes as $c) { $out .= $dom->saveHTML($c); }
        $out = preg_replace('/>\s+</', '><', trim($out)) ?? $out;

        return [
            'html'          => Html::curata($out),
            'galerii'       => $galerii,
            'linkuri_rupte' => $rupte,
            'externe'       => $externe,
            'spam_eliminat' => $spam,
        ];
    }
}
```

Observații pentru implementator: `Html::curata` elimină clasele/stilurile și `<div>`-urile (păstrează copiii), deci aserțiunile de egalitate strictă din test depind de ieșirea lui; dacă spațiile dintre elemente diferă, normalizează în test cu `preg_replace('/>\s+</', '><', …)`, nu slăbi aserțiunile de conținut. `&nbsp;` din HTML vechi nu trebuie să rămână ca `\u{A0}` în text.

- [ ] **Step 4: Rulează testul** — toate PASS.

- [ ] **Step 5: Commit** — `git add src/Migrare/Curata.php tests/migrare_curata_test.php && git commit -m "M2: Migrare\Curata — VC/Gutenberg, spam, rescriere uploads, galerii"`

### Task 3: `database/import_fisiere.php` — arhiva → `/fisiere/` + `fisiere`

**Files:**
- Create: `database/import_fisiere.php`, `tests/migrare_import_fisiere_test.php`

**Interfaces:**
- CLI: `php database/import_fisiere.php --zip=materiale/arhiva/public_html-26august2026.zip [--dest=fisiere] [--doar=2026/08] [--limita=N]`. Implicit `--dest` = `settings['upload']['dir']`.
- Comportament: iterează intrările `wp-content/uploads/AAAA/LL/nume.ext`; SARE peste directoare, peste miniaturi WP (`-\d+x\d+\.(jpe?g|png|webp|gif)$`), peste `js_composer|wc-logs|woocommerce_uploads|wp-less` și peste extensii care nu sunt în `Upload::EXTENSII` (+ `gif` acceptat ca imagine — dacă `Upload::EXTENSII` nu îl are, importă-l totuși ca `image/gif` și notează în raport); scrie fișierul la `{dest}/AAAA/LL/{Upload::numeSigur(nume)}` (la coliziune de nume cu conținut DIFERIT: sufix `-2`, `-3`; la conținut identic (md5) reutilizează); MIME din conținut cu `finfo`; înregistrează prin `Fisiere\Repository::inregistreaza(['nume_afisat' => nume original, 'cale' => …, 'mime' => …, 'marime' => …, 'incarcat_de' => null, 'legacy_url' => 'AAAA/LL/nume.ext'])` — deci `legacy_url` e calea relativă la uploads, exact ca în WP.
- Idempotent: dacă există deja un rând cu același `legacy_url` și fișierul e pe disc, sare (contor `existente`).
- Raport la final: `importate N, existente N, sărite (miniaturi) N, sărite (extensie) N, erori N`, plus lista erorilor.
- Expune funcțiile ca să fie testabile: fișierul definește `function importa_fisiere(ZipArchive $zip, string $dest, \App\Fisiere\Repository $repo, ?string $doar = null, int $limita = 0): array` și le rulează doar când `PHP_SAPI === 'cli' && realpath($argv[0]) === __FILE__` (ca testul să-l poată `require` fără să pornească importul).

- [ ] **Step 1: Testul** — construiește un zip sintetic și importă într-un director temporar:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require dirname(__DIR__) . '/database/import_fisiere.php';

$tmp = sys_get_temp_dir() . '/fp-imp-' . bin2hex(random_bytes(3));
mkdir($tmp);
$zipCale = "$tmp/test.zip";
$z = new ZipArchive();
$z->open($zipCale, ZipArchive::CREATE);
$pdf = file_get_contents(__DIR__ . '/fixtures/mic.pdf');
$png = file_get_contents(__DIR__ . '/fixtures/mic.png');
$z->addFromString('wp-content/uploads/2020/01/Anunț angajare – Manager.pdf', $pdf);
$z->addFromString('wp-content/uploads/2020/01/poza.png', $png);
$z->addFromString('wp-content/uploads/2020/01/poza-300x200.png', $png);          // miniatură
$z->addFromString('wp-content/uploads/2020/02/altul.pdf', $pdf . 'x');
$z->addFromString('wp-content/uploads/2020/02/altul.PDF', $pdf . 'y');           // coliziune de nume, conținut diferit
$z->addFromString('wp-content/uploads/js_composer/x.css', 'a{}');                // plugin
$z->addFromString('wp-content/uploads/2020/02/script.php', '<?php echo 1;');      // extensie nepermisă
$z->addFromString('wp-content/plugins/x/y.php', '<?php');                          // în afara uploads
$z->close();

$repo = new App\Fisiere\Repository(new App\Database(settings()['db']));
$dest = "$tmp/fisiere";
try {
    $z = new ZipArchive(); $z->open($zipCale);
    $r = importa_fisiere($z, $dest, $repo);
    $z->close();
    ok('importate 4', $r['importate'] === 4);
    ok('miniaturi sărite 1', $r['sarite_miniaturi'] === 1);
    ok('extensie sărită 1', $r['sarite_extensie'] === 1);
    ok('fișier normalizat pe disc', is_file("$dest/2020/01/anunt-angajare-manager.pdf"));
    $f = $repo->gasesteDupaCale('2020/01/anunt-angajare-manager.pdf');
    ok('  înregistrat cu legacy_url original', $f && $f['legacy_url'] === '2020/01/Anunț angajare – Manager.pdf' && $f['nume_afisat'] === 'Anunț angajare – Manager.pdf' && $f['mime'] === 'application/pdf');
    ok('coliziune: al doilea primește -2', $repo->gasesteDupaCale('2020/02/altul-2.pdf') !== null && $repo->gasesteDupaCale('2020/02/altul.pdf') !== null);
    // idempotent
    $z = new ZipArchive(); $z->open($zipCale);
    $r2 = importa_fisiere($z, $dest, $repo);
    $z->close();
    ok('re-rulare: 0 importate, 4 existente', $r2['importate'] === 0 && $r2['existente'] === 4);
    ok('re-rulare: nu apar rânduri noi', count(pdo()->query("SELECT id FROM fisiere WHERE cale LIKE '2020/0%'")->fetchAll()) === 4);
    // filtru --doar
    $repo3 = $repo; $dest3 = "$tmp/f3";
    $z = new ZipArchive(); $z->open($zipCale);
    $r3 = importa_fisiere($z, $dest3, $repo3, '2020/02');
    $z->close();
    ok('--doar limitează la 2020/02 (existente, nu importate)', $r3['importate'] + $r3['existente'] === 2);
} finally {
    pdo()->exec("DELETE FROM fisiere WHERE cale LIKE '2020/0%'");
    foreach (glob("$tmp/*/*/*/*") ?: [] as $f) { @unlink($f); }
}
final_test();
```

Atenție: testul folosește căi `2020/01`, `2020/02` — dacă baza locală are deja fișiere reale în acele luni (după importul real), schimbă anul de test în `1999`, atât în zip cât și în curățare.

- [ ] **Step 2: Rulează, pică** — `importa_fisiere` nedefinită.

- [ ] **Step 3: Implementarea** `database/import_fisiere.php`:

```php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Fisiere\Repository as Fisiere;
use App\Fisiere\Upload;

function importa_fisiere(ZipArchive $zip, string $dest, Fisiere $repo, ?string $doar = null, int $limita = 0): array
{
    $r = ['importate' => 0, 'existente' => 0, 'sarite_miniaturi' => 0, 'sarite_extensie' => 0, 'erori' => []];
    $ext = array_merge(Upload::EXTENSII, ['gif']);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nume = $zip->getNameIndex($i);
        if (!preg_match('#^wp-content/uploads/(\d{4}/\d{2})/([^/]+)$#', $nume, $m)) { continue; }
        [$_, $luna, $fisier] = $m;
        if ($doar !== null && !str_starts_with($luna, $doar)) { continue; }
        if (preg_match('/-\d+x\d+\.(jpe?g|png|webp|gif)$/i', $fisier)) { $r['sarite_miniaturi']++; continue; }
        $e = strtolower(pathinfo($fisier, PATHINFO_EXTENSION));
        if (!in_array($e, $ext, true)) { $r['sarite_extensie']++; continue; }
        $legacy = "$luna/$fisier";
        $existent = gaseste_dupa_legacy($repo, $legacy);
        if ($existent !== null && is_file(rtrim($dest, '/\\') . '/' . $existent['cale'])) { $r['existente']++; continue; }
        if ($limita > 0 && $r['importate'] >= $limita) { break; }
        $continut = $zip->getFromIndex($i);
        if ($continut === false) { $r['erori'][] = "$legacy: nu pot citi din arhivă"; continue; }
        $dir = rtrim($dest, '/\\') . "/$luna";
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) { $r['erori'][] = "$legacy: nu pot crea $dir"; continue; }
        $sigur = Upload::numeSigur($fisier);
        $baza = pathinfo($sigur, PATHINFO_FILENAME);
        $cand = $sigur;
        for ($n = 2; is_file("$dir/$cand") && md5_file("$dir/$cand") !== md5($continut); $n++) {
            $cand = "$baza-$n.$e";
        }
        if (!is_file("$dir/$cand") && file_put_contents("$dir/$cand", $continut) === false) { $r['erori'][] = "$legacy: nu pot scrie"; continue; }
        $mime = $finfo->buffer(substr($continut, 0, 8192)) ?: 'application/octet-stream';
        $repo->inregistreaza([
            'nume_afisat' => $fisier, 'cale' => "$luna/$cand", 'mime' => $mime,
            'marime' => strlen($continut), 'incarcat_de' => null, 'legacy_url' => $legacy,
        ]);
        $r['importate']++;
    }
    return $r;
}

function gaseste_dupa_legacy(Fisiere $repo, string $legacy): ?array
{
    return $repo->gasesteDupaLegacy($legacy);
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $root = dirname(__DIR__);
    if (is_file($root . '/.env')) { Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
    $settings = require $root . '/config/settings.php';
    $opt = getopt('', ['zip:', 'dest::', 'doar::', 'limita::']);
    $zipCale = (string) ($opt['zip'] ?? '');
    if ($zipCale === '' || !is_file($zipCale)) { fwrite(STDERR, "Folosire: import_fisiere.php --zip=cale.zip [--dest=dir] [--doar=AAAA/LL] [--limita=N]\n"); exit(1); }
    $zip = new ZipArchive();
    if ($zip->open($zipCale) !== true) { fwrite(STDERR, "Nu pot deschide arhiva.\n"); exit(1); }
    $repo = new Fisiere(new App\Database($settings['db']));
    $t = microtime(true);
    $r = importa_fisiere($zip, (string) ($opt['dest'] ?? $settings['upload']['dir']), $repo, $opt['doar'] ?? null, (int) ($opt['limita'] ?? 0));
    $zip->close();
    printf("importate %d, existente %d, sarite miniaturi %d, sarite extensie %d, erori %d, %.1f s\n", $r['importate'], $r['existente'], $r['sarite_miniaturi'], $r['sarite_extensie'], count($r['erori']), microtime(true) - $t);
    foreach ($r['erori'] as $e) { echo "  ! $e\n"; }
}
```

Adaugă în `src/Fisiere/Repository.php`:

```php
    public function gasesteDupaLegacy(string $legacy): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM fisiere WHERE legacy_url = :l LIMIT 1');
        $st->execute(['l' => $legacy]);
        return $st->fetch() ?: null;
    }
```

Notă memorie: `getFromIndex` încarcă fișierul în memorie; cel mai mare upload e ~100 MB, deci ridică `memory_limit` la `512M` în capul scriptului (`ini_set('memory_limit', '512M')`) — doar în ramura CLI.

- [ ] **Step 4: Rulează testul** — toate PASS. Apoi rulează importul REAL, întâi limitat: `$PHP database/import_fisiere.php --zip=materiale/arhiva/public_html-26august2026.zip --doar=2026/08` → așteptat `importate 4`. Apoi complet (durează câteva minute): `$PHP database/import_fisiere.php --zip=materiale/arhiva/public_html-26august2026.zip` → așteptat ≈ 1000–1300 importate (fără cele ~2500 miniaturi), 0 erori; verifică `du -sh fisiere/` ≈ 1,5–2 GB. Notează cifrele exacte în raport.

- [ ] **Step 5: Commit** — `git add database/import_fisiere.php src/Fisiere/Repository.php tests/migrare_import_fisiere_test.php && git commit -m "M2: import_fisiere — arhiva WP -> /fisiere/ + registru, idempotent"` (folderul `fisiere/` e gitignored).

### Task 4: `App\Migrare\Harta` (regulile de mapare) și `database/migrate_wp.php`

**Files:**
- Create: `src/Migrare/Harta.php`, `database/migrate_wp.php`, `database/data/acasa-2021-2027.html`, `database/data/contact-2014-2020.html`, `database/data/contact-2021-2027.html`, `tests/migrare_harta_test.php`, `tests/migrare_wp_test.php`
- Modify: `composer.json` (`require-dev`: `smalot/pdfparser ^2.10`), `src/Meniu/Repository.php` (`gasesteDupaLegacy(int): ?array`), `.gitignore` (`/storage/migrare/`)

**Interfaces:**
- `Harta::SET_2021 = [3622, 3615, 3617, 3604, 3607, 3609, 3611, 3613, 3620]` — intrările vechi care trec în secțiunea 2021-2027 (Noutăți: comunicat SDL + anunțurile 2026; Strategie: SDL 2021-2027), conform briefului.
- `Harta::PAGINI_SARITE = [75]` — pagina Acasă nu devine intrare de meniu (textul ei merge în `sectiuni.acasa_html`).
- `Harta::MENIU_2021` — structura fixă a perioadei noi (ordine, titlu, tip, copii), cu `legacy_id` sintetice `9000xx`:
  `[9001 Noutăți dosar] [9002 Strategie dosar] [9003 Acțiuni dosar] [9004 Apel lansare dosar] [9005 Arhivă dosar] [9006 Proceduri operaționale FLAG dosar] [9007 Media dosar → 9071 Comunicate de presă, 9072 Animări, 9073 Galerie] [9008 Utile pagina] [9009 Contact pagina sablon=contact]`.
- `Harta::clasifica(array $item, ?array $pagina, bool $areCopii, callable $existaFisier): array{tip: string, url: ?string, cale: ?string, motiv: ?string}` — pură:
  - `post_type` + pagină cu `continut` nevid → `pagina`; pagină goală + copii → `dosar`; pagină goală fără copii → `tip = 'sari'`, `motiv = 'pagina goala'`;
  - `custom` + `Legacy::caleDinUrl(url)` non-null → dacă `$existaFisier(cale)` → `document` (`cale` = legacy), altfel `sari` cu `motiv = 'fisier lipsa: …'`;
  - `custom` + URL extern `http(s)://` care nu e flagprahova.ro → `link`;
  - orice altceva (`#`, gol, `http://ab`, folder) → `dosar` dacă are copii, altfel `sari` cu `motiv = 'placeholder fara copii'`.
- `Harta::sectiuneaPentru(int $legacyId): string` → `'2021-2027'` dacă e în `SET_2021`, altfel `'2014-2020'`.
- `migrate_wp.php` definește `function migreaza(array $ctx, bool $verbose = true): array` (ctx = `['legacy' => Legacy, 'meniu' => Meniu\Repository, 'galerie' => GalerieRepository, 'fisiere' => Fisiere\Repository, 'pdo' => PDO al bazei noi, 'root' => string]`) și întoarce raportul `{creat: int, actualizat: int, sarit: array<string>, galerii: int, imagini_lipsa: int, linkuri_rupte: string[], externe: string[], spam: int}`. Se rulează doar în ramura `PHP_SAPI === 'cli' && realpath($argv[0]) === __FILE__`.
- Algoritm `migreaza()`:
  1. `$items = legacy->meniu()`; `$copii[parent][] = item`; secțiunile din `meniu->sectiuni()` indexate pe slug.
  2. **2014-2020**: parcurge recursiv rădăcinile (`parent = 0`) în ordinea `menu_order`, sărind `PAGINI_SARITE` și `SET_2021`; pentru fiecare item, `clasifica()`; dacă `sari` → în raport; altfel upsert în `meniu` pe `legacy_id` (`gasesteDupaLegacy` → `actualizeaza`, altfel `creeaza`) cu `sectiune_id`, `parent_id` = id-ul nou al părintelui (sau null), `titlu`, `slug` gol la creare (generat; NU se rescrie la actualizare, ca URL-urile să rămână stabile), `tip`, `fisier_id` (prin `fisiere->gasesteDupaLegacy(cale)['id']`), `url`, `vizibil = 1`, `continut_html` din `Curata::proceseaza(pagina.continut)['html']` pentru `pagina`; `sablon = 'contact'` pentru pagina 156. Ordinea copiilor = ordinea din meniul vechi (după inserare rulează `meniu->reordoneaza(sid, arboreNou)` pe fiecare secțiune, sau setează `ordine` explicit — alege `reordoneaza`, deja testată).
  3. Galeriile întoarse de `Curata` pentru o pagină devin intrări `galerie` copii ale paginii (`legacy_id` = `800000 + id_pagina * 100 + index`), cu `GalerieRepository::seteaza(id, [{fisier_id, legenda: ''}])` unde `fisier_id` vine din `legacy->atasamenteCai(ids)` → `fisiere->gasesteDupaLegacy(cale)`; id-urile fără fișier se numără în `imagini_lipsa`.
  4. **2021-2027**: creează `MENIU_2021` (upsert pe `legacy_id` sintetic); sub 9001 mută intrările `SET_2021` din Noutăți (ordinea din meniul vechi), sub 9002 pe 3620; 9008 Utile primește `continut_html` = același HTML curat ca pagina 252; 9009 Contact primește `database/data/contact-2021-2027.html`; iar pagina veche Contact (156) primește `database/data/contact-2014-2020.html` în loc de builder html (formularul CF7 și tabelul vechi nu se pot refolosi).
  5. `sectiuni.acasa_html`: 2014-2020 ← `Curata(pagina 75)`; 2021-2027 ← `database/data/acasa-2021-2027.html` (ambele prin `Html::curata`). `UPDATE sectiuni SET acasa_html = :h WHERE slug = :s`.
  6. Raport tipărit: numărători, lista `sarit` (id, titlu, motiv), linkuri rupte, hosturi externe, spam eliminat.

- [ ] **Step 1: Fișierele de date** (scrise de mână, HTML simplu; `Html::curata` le va trece oricum):

`database/data/contact-2014-2020.html`:

```html
<h2>Asociația FLAG Prahova</h2>
<p><strong>Sediu:</strong> Sat Urleta, Comuna Bănești, str. Principală, nr. 26, județul Prahova, cod poștal 107050</p>
<p><strong>Punct de lucru:</strong> Comuna Păulești, Sat Găgeni, nr. 41, județul Prahova</p>
<p><strong>Manager General</strong><br>Laura-Mădălina Manolache<br>Tel: <a href="tel:+40762609685">0762 609 685</a></p>
<p><strong>Asistent Manager</strong><br>Mădălina Maria Olteanu<br>Tel: <a href="tel:+40732248884">0732 248 884</a></p>
<p><strong>Email:</strong> <a href="mailto:flagprahova@gmail.com">flagprahova@gmail.com</a></p>
<h3>Program de lucru</h3>
<ul>
<li>Luni – Vineri: 08:00 – 16:30 (pauză de masă 12:00 – 12:30)</li>
<li>Sâmbătă și Duminică: închis</li>
</ul>
```

`database/data/contact-2021-2027.html`:

```html
<h2>Asociația FLAG Prahova</h2>
<p><strong>Punct de lucru:</strong> Comuna Păulești, Sat Găgeni, nr. 41, județul Prahova</p>
<p><strong>Manager</strong><br>Laura-Mădălina Manolache<br>Tel: <a href="tel:+40762609685">0762 609 685</a></p>
<p><strong>Email:</strong> <a href="mailto:flagprahova@gmail.com">flagprahova@gmail.com</a></p>
<h3>Program de lucru</h3>
<ul>
<li>Luni – Vineri: 08:00 – 16:30 (pauză de masă 12:00 – 12:30)</li>
<li>Sâmbătă și Duminică: închis</li>
</ul>
```

`database/data/acasa-2021-2027.html` — se scrie DUPĂ extragerea textului din SDL: `composer require --dev smalot/pdfparser`, apoi un script temporar în scratchpad care face `(new \Smalot\PdfParser\Parser())->parseFile('fisiere/2026/08/sdl-a-zonei-de-pescuit-si-avacultura.pdf')->getText()` și salvează în `storage/migrare/sdl-2021-2027.txt` (gitignored). Din text, implementatorul scrie 3 paragrafe (max 1200 de caractere): (1) ce e Asociația FLAG Prahova și strategia 2021-2027 (denumirea exactă a strategiei și a programului de finanțare, așa cum apar în PDF), (2) teritoriul acoperit (localitățile, dacă sunt enumerate), (3) obiectivul general. Fără cifre inventate: orice sumă sau dată apare doar dacă e în PDF. Textul se pune și în raport, pentru validarea lui Daniel. Fallback dacă PDF-ul nu e parsabil: două paragrafe scrise din titlul strategiei și din textul Acasă vechi, marcate în raport ca „de completat de client”.

- [ ] **Step 2: Testul pentru `Harta` (pur)**

`tests/migrare_harta_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

use App\Migrare\Harta;

$exista = fn(string $cale): bool => $cale === '2017/06/Organigrama.pdf';
$item = fn(array $o) => $o + ['id' => 1, 'ordine' => 1, 'parent' => 0, 'tip' => 'custom', 'obiect_id' => 0, 'titlu' => 'X', 'url' => ''];

$r = Harta::clasifica($item(['url' => '/wp-content/uploads/2017/06/Organigrama.pdf']), null, false, $exista);
ok('document existent', $r['tip'] === 'document' && $r['cale'] === '2017/06/Organigrama.pdf');
$r = Harta::clasifica($item(['url' => '/wp-content/uploads/2019/05/Lipsa.pdf']), null, false, $exista);
ok('document lipsă => sari', $r['tip'] === 'sari' && str_contains((string) $r['motiv'], 'lipsa'));
ok('# cu copii => dosar', Harta::clasifica($item(['url' => '#']), null, true, $exista)['tip'] === 'dosar');
ok('http://ab cu copii => dosar', Harta::clasifica($item(['url' => 'http://ab']), null, true, $exista)['tip'] === 'dosar');
ok('folder cu copii => dosar', Harta::clasifica($item(['url' => '/wp-content/uploads/2017/08/']), null, true, $exista)['tip'] === 'dosar');
ok('# fără copii => sari', Harta::clasifica($item(['url' => '#']), null, false, $exista)['tip'] === 'sari');
$r = Harta::clasifica($item(['url' => 'https://www.madr.ro/pescuit.html']), null, false, $exista);
ok('extern => link', $r['tip'] === 'link' && $r['url'] === 'https://www.madr.ro/pescuit.html');
ok('pagină cu conținut => pagina', Harta::clasifica($item(['tip' => 'post_type', 'obiect_id' => 567]), ['id' => 567, 'titlu' => 'M1', 'slug' => 'm1', 'continut' => '<p>x</p>'], false, $exista)['tip'] === 'pagina');
ok('pagină goală cu copii => dosar', Harta::clasifica($item(['tip' => 'post_type', 'obiect_id' => 2714]), ['id' => 2714, 'titlu' => 'R', 'slug' => 'r', 'continut' => ''], true, $exista)['tip'] === 'dosar');
ok('pagină goală fără copii => sari', Harta::clasifica($item(['tip' => 'post_type', 'obiect_id' => 307]), ['id' => 307, 'titlu' => 'S', 'slug' => 's', 'continut' => ''], false, $exista)['tip'] === 'sari');
ok('pagină inexistentă => sari', Harta::clasifica($item(['tip' => 'post_type', 'obiect_id' => 1]), null, false, $exista)['tip'] === 'sari');
ok('sectiuneaPentru 3622 => 2021-2027', Harta::sectiuneaPentru(3622) === '2021-2027');
ok('sectiuneaPentru 3601 => 2014-2020', Harta::sectiuneaPentru(3601) === '2014-2020');
ok('MENIU_2021 are 9 rădăcini, Media cu 3 copii', count(Harta::MENIU_2021) === 9 && count(Harta::MENIU_2021[6]['copii']) === 3 && Harta::MENIU_2021[8]['sablon'] === 'contact');
final_test();
```

- [ ] **Step 3: `Harta.php`**

```php
<?php
declare(strict_types=1);

namespace App\Migrare;

final class Harta
{
    public const SET_2021 = [3622, 3615, 3617, 3604, 3607, 3609, 3611, 3613, 3620];
    public const PAGINI_SARITE = [75];
    public const LEGACY_CONTACT_VECHI = 175; // intrarea de meniu a paginii 156
    /** Pagini la care `post_content` e doar spam, dar `_variant_page_builder_html` are conținutul real. */
    public const PAGINI_BUILDER = [277];
    public const NOUTATI_2021 = 9001;
    public const STRATEGIE_2021 = 9002;
    public const UTILE_2021 = 9008;
    public const CONTACT_2021 = 9009;

    /** @var array<int, array{legacy:int,titlu:string,tip:string,sablon?:string,copii?:array}> */
    public const MENIU_2021 = [
        ['legacy' => 9001, 'titlu' => 'Noutăți', 'tip' => 'dosar'],
        ['legacy' => 9002, 'titlu' => 'Strategie', 'tip' => 'dosar'],
        ['legacy' => 9003, 'titlu' => 'Acțiuni', 'tip' => 'dosar'],
        ['legacy' => 9004, 'titlu' => 'Apel lansare', 'tip' => 'dosar'],
        ['legacy' => 9005, 'titlu' => 'Arhivă', 'tip' => 'dosar'],
        ['legacy' => 9006, 'titlu' => 'Proceduri operaționale FLAG', 'tip' => 'dosar'],
        ['legacy' => 9007, 'titlu' => 'Media', 'tip' => 'dosar', 'copii' => [
            ['legacy' => 9071, 'titlu' => 'Comunicate de presă', 'tip' => 'dosar'],
            ['legacy' => 9072, 'titlu' => 'Animări', 'tip' => 'dosar'],
            ['legacy' => 9073, 'titlu' => 'Galerie', 'tip' => 'dosar'],
        ]],
        ['legacy' => 9008, 'titlu' => 'Utile', 'tip' => 'pagina'],
        ['legacy' => 9009, 'titlu' => 'Contact', 'tip' => 'pagina', 'sablon' => 'contact'],
    ];

    public static function sectiuneaPentru(int $legacyId): string
    {
        return in_array($legacyId, self::SET_2021, true) ? '2021-2027' : '2014-2020';
    }

    /** @return array{tip:string,url:?string,cale:?string,motiv:?string} */
    public static function clasifica(array $item, ?array $pagina, bool $areCopii, callable $existaFisier): array
    {
        $out = ['tip' => 'sari', 'url' => null, 'cale' => null, 'motiv' => null];
        if ($item['tip'] === 'post_type') {
            if ($pagina === null) { $out['motiv'] = 'pagina inexistenta'; return $out; }
            if (trim((string) $pagina['continut']) !== '') { $out['tip'] = 'pagina'; return $out; }
            if ($areCopii) { $out['tip'] = 'dosar'; return $out; }
            $out['motiv'] = 'pagina goala';
            return $out;
        }
        $url = (string) $item['url'];
        $cale = Legacy::caleDinUrl($url);
        if ($cale !== null) {
            if ($existaFisier($cale)) { return ['tip' => 'document', 'url' => null, 'cale' => $cale, 'motiv' => null]; }
            $out['motiv'] = 'fisier lipsa: ' . $cale;
            return $out;
        }
        if (preg_match('#^https?://([^/]+)#i', $url, $m) && !preg_match('#(^|\.)flagprahova\.ro$#i', $m[1]) && str_contains($m[1], '.')) {
            return ['tip' => 'link', 'url' => $url, 'cale' => null, 'motiv' => null];
        }
        if ($areCopii) { $out['tip'] = 'dosar'; return $out; }
        $out['motiv'] = 'placeholder fara copii';
        return $out;
    }
}
```

(`http://ab`, `http://a`, `http://v`, `http://e`, `http:///wp-content/…`, `http://#` nu conțin un host cu punct → nu sunt `link`.)

- [ ] **Step 4: Testul end-to-end `migrate_wp`** — rulează pe baza WP reală, dar fără arhiva zip: creează rânduri `fisiere` FALSE pentru toate căile referite de meniu și de galerii (fără fișiere pe disc), rulează `migreaza()`, verifică arborele, apoi ȘTERGE tot ce a creat (rândurile `meniu` cu `legacy_id` non-null și rândurile `fisiere` de test) și restaurează `sectiuni.acasa_html`.

`tests/migrare_wp_test.php`:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require dirname(__DIR__) . '/database/migrate_wp.php';

use App\Migrare\Legacy;

$s = settings();
$pdo = pdo();
$wp = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $s['db_wp']['host'], $s['db_wp']['port'], $s['db_wp']['name']), $s['db_wp']['user'], $s['db_wp']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
$db = new App\Database($s['db']);
$ctx = ['legacy' => new Legacy($wp), 'meniu' => new App\Meniu\Repository($db), 'galerie' => new App\Meniu\GalerieRepository($db), 'fisiere' => new App\Fisiere\Repository($db), 'pdo' => $pdo, 'root' => dirname(__DIR__)];

$acasaVechi = $pdo->query('SELECT slug, acasa_html FROM sectiuni')->fetchAll(PDO::FETCH_KEY_PAIR);
$fisiereTest = [];
// fișiere false pentru tot ce referă meniul (documente) + 3 imagini din galeria „Instruire vanzari” + imaginile din Utile
$cai = [];
foreach ($ctx['legacy']->meniu() as $it) { $c = Legacy::caleDinUrl($it['url']); if ($c) { $cai[] = $c; } }
$cai = array_merge($cai, array_values($ctx['legacy']->atasamenteCai([2903, 2904, 2905])), ['2017/08/peste-la-cuptor.jpg', '2017/08/marinata-de-peste.jpg']);
foreach (array_unique($cai) as $c) {
    $mime = preg_match('/\.(jpe?g|png)$/i', $c) ? 'image/jpeg' : 'application/pdf';
    $id = $ctx['fisiere']->inregistreaza(['nume_afisat' => basename($c), 'cale' => 'test-migrare/' . slugify($c) . '.' . pathinfo($c, PATHINFO_EXTENSION), 'mime' => $mime, 'marime' => 1, 'incarcat_de' => null, 'legacy_url' => $c]);
    $fisiereTest[] = $id;
}
try {
    $r = migreaza($ctx, false);
    $sid20 = (int) $pdo->query("SELECT id FROM sectiuni WHERE slug='2014-2020'")->fetchColumn();
    $sid21 = (int) $pdo->query("SELECT id FROM sectiuni WHERE slug='2021-2027'")->fetchColumn();
    $n20 = (int) $pdo->query("SELECT COUNT(*) FROM meniu WHERE sectiune_id=$sid20 AND legacy_id IS NOT NULL")->fetchColumn();
    $n21 = (int) $pdo->query("SELECT COUNT(*) FROM meniu WHERE sectiune_id=$sid21 AND legacy_id IS NOT NULL")->fetchColumn();
    ok('2014-2020: peste 190 de intrări migrate', $n20 >= 190);
    ok('2021-2027: 12 sintetice + 9 mutate = 21', $n21 === 21);
    ok('raport: creat = total (inclusiv galeriile)', $r['creat'] === $n20 + $n21);
    $m = $ctx['meniu'];
    $org = $m->gasesteDupaLegacy(579);
    ok('579 Organigrama => document cu fisier_id', $org && $org['tip'] === 'document' && (int) $org['fisier_id'] > 0);
    $proc = $m->gasesteDupaLegacy(237);
    ok('237 Proceduri => dosar, părinte Strategie (204)', $proc && $proc['tip'] === 'dosar' && (int) $proc['parent_id'] === (int) $m->gasesteDupaLegacy(204)['id']);
    ok('  Organigrama sub Proceduri', (int) $org['parent_id'] === (int) $proc['id']);
    $coop = $m->gasesteDupaLegacy(2984);
    ok('2984 Cooperare => pagina cu conținut curat (fără [vc_)', $coop && $coop['tip'] === 'pagina' && str_contains($coop['continut_html'], 'INTERACȚIUNE') && !str_contains($coop['continut_html'], '[vc_'));
    $gal = $m->copii((int) $coop['id']);
    ok('  14 galerii copii ale paginii Cooperare', count($gal) === 14 && $gal[0]['tip'] === 'galerie' && $gal[0]['titlu'] === 'Instruire vanzari');
    ok('  galeria 1 are 3 imagini (cele cu fișier fals)', count($ctx['galerie']->imagini((int) $gal[0]['id'])) === 3);
    ok('  imagini lipsă raportate', $r['imagini_lipsa'] > 100);
    $contact = $m->gasesteDupaLegacy(175);
    ok('175 Contact => pagina sablon contact cu ambele persoane', $contact && $contact['sablon'] === 'contact' && str_contains($contact['continut_html'], 'Olteanu'));
    $c21 = $m->gasesteDupaLegacy(9009);
    ok('9009 Contact 2021 => doar Laura', $c21 && $c21['sablon'] === 'contact' && str_contains($c21['continut_html'], 'Manolache') && !str_contains($c21['continut_html'], 'Olteanu'));
    $u21 = $m->gasesteDupaLegacy(9008);
    ok('9008 Utile 2021 => rețete cu imagine rescrisă', $u21 && str_contains($u21['continut_html'], 'Marinată') && str_contains($u21['continut_html'], '/fisiere/test-migrare/'));
    $nou = $m->gasesteDupaLegacy(3622);
    ok('3622 mutat în 2021-2027 sub Noutăți (9001)', $nou && (int) $nou['sectiune_id'] === $sid21 && (int) $nou['parent_id'] === (int) $m->gasesteDupaLegacy(9001)['id']);
    ok('3601 DIGICO rămâne în 2014-2020 sub Noutăți (250)', (int) $m->gasesteDupaLegacy(3601)['sectiune_id'] === $sid20);
    $cons = $m->gasesteDupaLegacy(280);
    ok('280 Consultare publică => pagina din builder html (nu din post_content-ul spam)', $cons && $cons['tip'] === 'pagina' && str_contains($cons['continut_html'], 'Consultare publică') && !str_contains($cons['continut_html'], 'podcasts'));
    ok('spam eliminat > 0', $r['spam'] > 0);
    ok('acasa_html 2014-2020 setat', str_contains((string) $pdo->query("SELECT acasa_html FROM sectiuni WHERE id=$sid20")->fetchColumn(), 'contractului de finanțare'));
    ok('acasa_html 2021-2027 setat', strlen((string) $pdo->query("SELECT acasa_html FROM sectiuni WHERE id=$sid21")->fetchColumn()) > 200);
    // idempotență
    $r2 = migreaza($ctx, false);
    ok('re-rulare: 0 creat, nimic duplicat', $r2['creat'] === 0 && (int) $pdo->query("SELECT COUNT(*) FROM meniu WHERE legacy_id IS NOT NULL")->fetchColumn() === $n20 + $n21);
    ok('re-rulare: slug-ul nu se schimbă', $m->gasesteDupaLegacy(579)['slug'] === $org['slug']);
} finally {
    $pdo->exec('DELETE FROM meniu WHERE legacy_id IS NOT NULL');
    foreach ($fisiereTest as $id) { $pdo->exec("DELETE FROM fisiere WHERE id = $id"); }
    $st = $pdo->prepare('UPDATE sectiuni SET acasa_html = :h WHERE slug = :s');
    foreach ($acasaVechi as $slug => $h) { $st->execute(['h' => $h, 's' => $slug]); }
}
final_test();
```

Notă: `DELETE FROM meniu WHERE legacy_id IS NOT NULL` șterge și rândurile migrate anterior (de la o rulare reală) — testul se rulează ÎNAINTE de migrarea reală sau după `reset_continut.php`; documentează asta în capul testului. Aserțiunea „280 sărită”: 280 e intrarea de meniu a paginii 277.

- [ ] **Step 5: `migrate_wp.php`** — implementează `migreaza()` conform algoritmului din Interfaces. Schelet:

```php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Migrare\Curata;
use App\Migrare\Harta;
use App\Migrare\Legacy;
use App\Support\Html;

function migreaza(array $ctx, bool $verbose = true): array
{
    $legacy = $ctx['legacy']; $meniu = $ctx['meniu']; $galerie = $ctx['galerie']; $fisiere = $ctx['fisiere']; $pdo = $ctx['pdo'];
    $rap = ['creat' => 0, 'actualizat' => 0, 'sarit' => [], 'galerii' => 0, 'imagini_lipsa' => 0, 'linkuri_rupte' => [], 'externe' => [], 'spam' => 0];
    $sect = [];
    foreach ($meniu->sectiuni() as $s) { $sect[$s['slug']] = (int) $s['id']; }
    $curata = new Curata(fn(string $c): ?string => $fisiere->gasesteDupaLegacy($c)['cale'] ?? null);
    $existaFisier = fn(string $c): bool => $fisiere->gasesteDupaLegacy($c) !== null;

    $items = $legacy->meniu();
    $copii = [];
    foreach ($items as $it) { $copii[$it['parent']][] = $it; }

    $upsert = function (int $legacyId, array $date) use ($meniu, &$rap): int {
        $ex = $meniu->gasesteDupaLegacy($legacyId);
        if ($ex !== null) {
            unset($date['slug']);
            $meniu->actualizeaza((int) $ex['id'], $date);
            $rap['actualizat']++;
            return (int) $ex['id'];
        }
        $rap['creat']++;
        return $meniu->creeaza($date + ['legacy_id' => $legacyId, 'slug' => '']);
    };

    $paginaHtml = function (int $paginaId) use ($legacy, $curata, &$rap): array {
        $p = $legacy->pagina($paginaId, in_array($paginaId, Harta::PAGINI_BUILDER, true));
        $r = $curata->proceseaza((string) ($p['continut'] ?? ''));
        $rap['linkuri_rupte'] = array_merge($rap['linkuri_rupte'], $r['linkuri_rupte']);
        $rap['externe'] = array_unique(array_merge($rap['externe'], $r['externe']));
        $rap['spam'] += $r['spam_eliminat'];
        return $r;
    };

    $adaugaGalerii = function (int $parintePaginaId, int $paginaId, array $galerii, int $sid) use ($upsert, $galerie, $legacy, $fisiere, &$rap): void {
        foreach (array_values($galerii) as $i => $g) {
            $gid = $upsert(800000 + $paginaId * 100 + $i, ['sectiune_id' => $sid, 'parent_id' => $parintePaginaId, 'titlu' => $g['titlu'], 'tip' => 'galerie', 'vizibil' => 1]);
            $cai = $legacy->atasamenteCai($g['ids']);
            $set = [];
            foreach ($g['ids'] as $aid) {
                $f = isset($cai[$aid]) ? $fisiere->gasesteDupaLegacy($cai[$aid]) : null;
                if ($f === null) { $rap['imagini_lipsa']++; continue; }
                $set[] = ['fisier_id' => (int) $f['id'], 'legenda' => ''];
            }
            $galerie->seteaza($gid, $set);
            $rap['galerii']++;
        }
    };

    // --- 2014-2020 ---
    $sid20 = $sect['2014-2020'];
    $arbore20 = [];
    $root = (string) $ctx['root'];
    $parcurge = function (array $lista, ?int $parintNou, array &$arboreNod) use (&$parcurge, $copii, $legacy, $fisiere, $meniu, $root, $existaFisier, $upsert, $paginaHtml, $adaugaGalerii, $sid20, &$rap): void {
        foreach ($lista as $it) {
            if (in_array($it['id'], Harta::SET_2021, true)) { continue; }
            if ($it['tip'] === 'post_type' && in_array($it['obiect_id'], Harta::PAGINI_SARITE, true)) { continue; }
            $pag = $it['tip'] === 'post_type' ? $legacy->pagina($it['obiect_id'], in_array($it['obiect_id'], Harta::PAGINI_BUILDER, true)) : null;
            $areCopii = !empty($copii[$it['id']]);
            $cls = Harta::clasifica($it, $pag, $areCopii, $existaFisier);
            if ($cls['tip'] === 'sari') { $rap['sarit'][] = "{$it['id']} „{$it['titlu']}”: {$cls['motiv']}"; continue; }
            $date = ['sectiune_id' => $sid20, 'parent_id' => $parintNou, 'titlu' => $it['titlu'], 'tip' => $cls['tip'], 'url' => $cls['url'] ?? '', 'vizibil' => 1, 'sablon' => 'standard'];
            $galerii = [];
            if ($cls['tip'] === 'document') { $date['fisier_id'] = (int) $fisiere->gasesteDupaLegacy($cls['cale'])['id']; }
            if ($cls['tip'] === 'pagina') {
                if ($it['id'] === Harta::LEGACY_CONTACT_VECHI) {
                    $date['continut_html'] = Html::curata((string) file_get_contents($root . '/database/data/contact-2014-2020.html'));
                    $date['sablon'] = 'contact';
                } else {
                    $r = $paginaHtml($it['obiect_id']);
                    if ($r['html'] === '' && $r['galerii'] === [] && !$areCopii) {
                        // ex. pagina 277 „Consultare publică”: doar spam → după curățare nu rămâne nimic
                        $rap['sarit'][] = "{$it['id']} „{$it['titlu']}”: continut gol dupa curatare";
                        continue;
                    }
                    $date['continut_html'] = $r['html'];
                    $galerii = $r['galerii'];
                }
            }
            $idNou = $upsert($it['id'], $date);
            $nod = ['id' => $idNou, 'copii' => []];
            if ($galerii !== []) {
                $adaugaGalerii($idNou, $it['obiect_id'], $galerii, $sid20);
                foreach ($meniu->copii($idNou) as $c) { if ($c['tip'] === 'galerie') { $nod['copii'][] = ['id' => (int) $c['id'], 'copii' => []]; } }
            }
            if ($areCopii) { $parcurge($copii[$it['id']], $idNou, $nod['copii']); }
            $arboreNod[] = $nod;
        }
    };
    $parcurge($copii[0] ?? [], null, $arbore20);
    $meniu->reordoneaza($sid20, $arbore20);

    // --- 2021-2027 ---
    $sid21 = $sect['2021-2027'];
    $arbore21 = [];
    $utileHtml = $paginaHtml(252)['html'];
    foreach (Harta::MENIU_2021 as $def) {
        $date = ['sectiune_id' => $sid21, 'parent_id' => null, 'titlu' => $def['titlu'], 'tip' => $def['tip'], 'vizibil' => 1, 'sablon' => $def['sablon'] ?? 'standard'];
        if ($def['legacy'] === Harta::UTILE_2021) { $date['continut_html'] = $utileHtml; }
        if ($def['legacy'] === Harta::CONTACT_2021) { $date['continut_html'] = Html::curata((string) file_get_contents($ctx['root'] . '/database/data/contact-2021-2027.html')); }
        $id = $upsert($def['legacy'], $date);
        $nod = ['id' => $id, 'copii' => []];
        foreach ($def['copii'] ?? [] as $c) {
            $nod['copii'][] = ['id' => $upsert($c['legacy'], ['sectiune_id' => $sid21, 'parent_id' => $id, 'titlu' => $c['titlu'], 'tip' => $c['tip'], 'vizibil' => 1]), 'copii' => []];
        }
        if ($def['legacy'] === Harta::NOUTATI_2021 || $def['legacy'] === Harta::STRATEGIE_2021) {
            $tinta = $def['legacy'] === Harta::NOUTATI_2021 ? 250 : 204;
            foreach ($copii[$tinta] ?? [] as $it) {
                if (!in_array($it['id'], Harta::SET_2021, true)) { continue; }
                $cls = Harta::clasifica($it, null, false, $existaFisier);
                if ($cls['tip'] === 'sari') { $rap['sarit'][] = "{$it['id']} „{$it['titlu']}”: {$cls['motiv']}"; continue; }
                $fid = $cls['tip'] === 'document' ? (int) $fisiere->gasesteDupaLegacy($cls['cale'])['id'] : null;
                $nod['copii'][] = ['id' => $upsert($it['id'], ['sectiune_id' => $sid21, 'parent_id' => $id, 'titlu' => $it['titlu'], 'tip' => $cls['tip'], 'fisier_id' => $fid, 'url' => $cls['url'] ?? '', 'vizibil' => 1]), 'copii' => []];
            }
        }
        $arbore21[] = $nod;
    }
    $meniu->reordoneaza($sid21, $arbore21);

    // --- Acasă ---
    $st = $pdo->prepare('UPDATE sectiuni SET acasa_html = :h WHERE slug = :s');
    $st->execute(['h' => $paginaHtml(75)['html'], 's' => '2014-2020']);
    $st->execute(['h' => Html::curata((string) file_get_contents($ctx['root'] . '/database/data/acasa-2021-2027.html')), 's' => '2021-2027']);

    if ($verbose) {
        printf("creat %d, actualizat %d, galerii %d, imagini lipsa %d, spam eliminat %d, sarite %d, linkuri rupte %d, externe: %s\n",
            $rap['creat'], $rap['actualizat'], $rap['galerii'], $rap['imagini_lipsa'], $rap['spam'], count($rap['sarit']), count($rap['linkuri_rupte']), implode(', ', $rap['externe']));
        foreach ($rap['sarit'] as $s) { echo "  - sarit: $s\n"; }
        foreach (array_unique($rap['linkuri_rupte']) as $l) { echo "  - link rupt: $l\n"; }
    }
    return $rap;
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $root = dirname(__DIR__);
    if (is_file($root . '/.env')) { Dotenv\Dotenv::createImmutable($root)->safeLoad(); }
    $settings = require $root . '/config/settings.php';
    if (($settings['db_wp']['name'] ?? '') === '') { fwrite(STDERR, "DB_WP_NAME lipseste din .env\n"); exit(1); }
    $c = $settings['db_wp'];
    $wp = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']), $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    $db = new App\Database($settings['db']);
    migreaza(['legacy' => new Legacy($wp, (string) $c['prefix']), 'meniu' => new App\Meniu\Repository($db), 'galerie' => new App\Meniu\GalerieRepository($db), 'fisiere' => new App\Fisiere\Repository($db), 'pdo' => $db->pdo(), 'root' => $root]);
}
```

Modifică `Legacy::pagina(int $id, bool $preferaBuilder = false): ?array` — cu `$preferaBuilder = true` întoarce `_variant_page_builder_html` dacă e nevid, altfel `post_content` (inversul implicitului). Adaugă în `Meniu\Repository`:

```php
    public function gasesteDupaLegacy(int $legacyId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM meniu WHERE legacy_id = :l LIMIT 1');
        $st->execute(['l' => $legacyId]);
        return $st->fetch() ?: null;
    }
```

și verifică că `actualizeaza()` acceptă `legacy_id`/`sectiune_id` fără să le rescrie (COLOANE nu conține `sectiune_id` — la mutarea unei intrări din 2014-2020 în 2021-2027 pe re-rulare nu e nevoie, secțiunea e stabilă de la prima rulare; dar `parent_id` da).

- [ ] **Step 6: Rulează testele** — `tests/migrare_harta_test.php`, apoi `tests/migrare_wp_test.php` (pe baza WP reală; durează ~10–20 s). Toate PASS. Apoi suita completă.

- [ ] **Step 7: Commit** — `git add composer.json composer.lock src/Migrare/Harta.php src/Meniu/Repository.php database/migrate_wp.php database/data tests/migrare_harta_test.php tests/migrare_wp_test.php .gitignore && git commit -m "M2: migrate_wp — arbore 2014-2020, meniu 2021-2027, pagini curate, galerii, contact, acasa"`

### Task 5: Migrarea reală, verificare, reset pentru dev

**Files:**
- Create: `database/verifica_migrare.php`, `database/reset_continut.php`, `tests/migrare_verifica_test.php`
- Modify: `CLAUDE.md` (secțiunea „Migrare”), `README.md` (pașii de migrare)

**Interfaces:**
- `verifica_migrare.php` tipărește: număr intrări per secțiune și per tip; documente cu `fisier_id` NULL sau cu fișier lipsă pe disc; pagini cu `continut_html` gol; galerii fără imagini; intrări cu `slug` duplicat (n-ar trebui); hosturi externe din `continut_html`; exit code 1 dacă există documente fără fișier. Expune `function verifica(PDO $pdo, string $dirFisiere): array{erori: string[], sumar: array}`.
- `reset_continut.php --da` (DEV): `DELETE FROM galerie_imagini; DELETE FROM meniu; DELETE FROM fisiere; UPDATE sectiuni SET acasa_html = NULL;` și reface `setari` la valorile din `seed.php` (șterge cheile din `Setari::CHEI` și re-rulează seed); refuză fără `--da` sau dacă `APP_ENV !== 'dev'`.

- [ ] **Step 1: Test pentru `verifica()`** — inserează o intrare `document` cu `fisier_id` NULL și una cu fișier inexistent pe disc, apelează `verifica()`, așteaptă 2 erori care conțin titlurile; șterge în `finally`.

```php
<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require dirname(__DIR__) . '/database/verifica_migrare.php';
$pdo = pdo();
$sid = (int) $pdo->query("SELECT id FROM sectiuni WHERE slug='2014-2020'")->fetchColumn();
$marca = bin2hex(random_bytes(3));
$pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime) VALUES ("x.pdf", :c, "application/pdf", 1)')->execute(['c' => "1999/01/lipsa-$marca.pdf"]);
$fid = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO meniu (sectiune_id, titlu, slug, tip, fisier_id) VALUES (:s, :t, :sl, "document", NULL)')->execute(['s' => $sid, 't' => "Fara fisier $marca", 'sl' => "fara-$marca"]);
$m1 = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO meniu (sectiune_id, titlu, slug, tip, fisier_id) VALUES (:s, :t, :sl, "document", :f)')->execute(['s' => $sid, 't' => "Fisier lipsa $marca", 'sl' => "lipsa-$marca", 'f' => $fid]);
$m2 = (int) $pdo->lastInsertId();
try {
    $r = verifica($pdo, settings()['upload']['dir']);
    ok('2 erori pentru cele 2 intrări', count(array_filter($r['erori'], fn($e) => str_contains($e, $marca))) === 2);
    ok('sumar are numărători pe tip', isset($r['sumar']['2014-2020']['document']));
} finally {
    $pdo->exec("DELETE FROM meniu WHERE id IN ($m1,$m2)");
    $pdo->exec("DELETE FROM fisiere WHERE id = $fid");
}
final_test();
```

- [ ] **Step 2: Implementează `verifica_migrare.php` și `reset_continut.php`** conform Interfaces (aceeași structură cu funcție + ramură CLI ca la `import_fisiere.php`).

- [ ] **Step 3: Rulează migrarea REALĂ, în ordinea asta**, notând fiecare ieșire în raport:
  1. `$PHP database/reset_continut.php --da` (curăță resturile de teste manuale din Plan 1: 38 de intrări de meniu și setările `landing_text='t'`, `footer_text='f'`).
  2. `$PHP database/import_fisiere.php --zip=materiale/arhiva/public_html-26august2026.zip` (dacă nu a fost rulat în Task 3; altfel raportează `existente`).
  3. `$PHP database/migrate_wp.php` → așteptat: `creat ≈ 210–225`, `galerii 14`, `sarite` mici (paginile goale 307/288/396/362/384/2528/2577/2581/2588/2592, 277 spam, eventuale fișiere lipsă), `imagini lipsa 0` (arhiva conține pozele), `externe` = o listă scurtă de verificat (madr.ro, ampeste.ro, youtube…).
  4. `$PHP database/verifica_migrare.php` → exit 0, 0 documente fără fișier.
  5. `$PHP database/migrate_wp.php` a doua oară → `creat 0`.
  6. Deschide `http://flagprahova.test/admin/meniu?sectiune=2014-2020` și `?sectiune=2021-2027`: arborele are 3 niveluri, documentele se deschid din admin (linkul din formularul intrării), pagina Cooperare are 14 galerii cu imagini, Utile are rețetele cu poze. Fă capturi în `storage/shots/admin-meniu-2014.png` și `admin-meniu-2021.png` (Chrome headless nu are sesiune — folosește browserul normal sau descrie în raport ce ai verificat prin curl cu sesiune).

- [ ] **Step 4: Documentație** — în `CLAUDE.md` secțiunea „Migrare din WordPress” (5–8 rânduri: baza `flagprahova_wp_old`, ordinea scripturilor, `legacy_id`/`legacy_url` = cheile de idempotență, `SET_2021`, `reset_continut.php` DOAR pe dev, `tests/migrare_wp_test.php` șterge rândurile cu `legacy_id` → se rulează înainte de migrarea reală sau după reset). În `README.md`: secțiunea „Migrarea conținutului vechi” cu cele 4 comenzi.

- [ ] **Step 5: Commit** — `git add database/verifica_migrare.php database/reset_continut.php tests/migrare_verifica_test.php CLAUDE.md README.md && git commit -m "M2: verificare migrare, reset dev, documentatie; migrarea reala rulata local"`

---

## Auto-verificare

- **Acoperire spec §7:** `import_fisiere.php` (fișiere, miniaturi sărite, foldere de plugin sărite, `legacy_url`) → T3; `migrate_wp.php` (arbore 2014-2020, tipuri după URL, mutarea intrărilor din brief în 2021-2027, meniul gol 2021-2027 cu Media → Comunicate/Animări/Galerie, Utile, Contact) → T4; pagini curățate de `[vc_*]`, spam, clase WP, linkuri rescrise, galerii Cooperare → T2 + T4; iframe YouTube → T2; Contact 2014-2020 cu ambele persoane, 2021-2027 doar Laura → T4 (fișiere de date); Acasă 2021-2027 din SDL → T4; categorii spam/WooCommerce/revizii/CF7 ignorate → T1 citește doar `nav_menu_item`, `page`, `attachment`; idempotență pe `legacy_id`/`legacy_url` → T3/T4 (teste de re-rulare); raport final (intrări per tip, fișiere lipsă, linkuri rupte) → T4 + T5.
- **Consistență de nume:** `Legacy::{meniu, pagina, atasamentCale, atasamenteCai, caleDinUrl}`; `Curata::proceseaza` → `{html, galerii, linkuri_rupte, externe, spam_eliminat}`; `Harta::{SET_2021, PAGINI_SARITE, MENIU_2021, clasifica, sectiuneaPentru, LEGACY_CONTACT_VECHI, NOUTATI_2021, STRATEGIE_2021, UTILE_2021, CONTACT_2021}`; `Fisiere\Repository::gasesteDupaLegacy(string)`; `Meniu\Repository::gasesteDupaLegacy(int)`; funcțiile CLI `importa_fisiere`, `migreaza`, `verifica`. Chei container noi: `legacy` (closure).
- **Placeholder-e:** textul Acasă 2021-2027 e singurul conținut pe care implementatorul îl scrie din PDF, cu regulile din T4 Step 1 și cu validarea lui Daniel în raport.
- **Pentru Plan 3 (sit public):** paginile de tip `pagina` pot avea copii `galerie` (Cooperare) → template-ul paginii afișează galeriile copil sub conținut; `sectiuni.acasa_html` e sursa textului Acasă; hosturile externe din raport se verifică manual.
