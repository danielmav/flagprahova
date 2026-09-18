<?php
declare(strict_types=1);

namespace App\Migrare;

use PDO;

/** Citire read-only din baza WordPress veche. Nu scrie niciodată. */
final class Legacy
{
    public function __construct(private PDO $pdo, private string $prefix = 'wpt9_', private string $numeMeniu = 'Meniu FLAG') {}

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
                JOIN {$p}terms t ON t.term_id = tt.term_id AND t.name = :nume
                JOIN {$p}postmeta m ON m.post_id = i.ID
                WHERE i.post_type = 'nav_menu_item' AND i.post_status = 'publish'
                GROUP BY i.ID, i.menu_order, i.post_title
                ORDER BY i.menu_order, i.ID";
        $st = $this->pdo->prepare($sql);
        $st->execute(['nume' => $this->numeMeniu]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
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

    /**
     * `$preferaBuilder = true` inversează ordinea implicită: ia întâi
     * `_variant_page_builder_html` și cade pe `post_content` doar dacă e gol
     * (pagini la care `post_content` a fost înlocuit de spam).
     */
    public function pagina(int $id, bool $preferaBuilder = false): ?array
    {
        $p = $this->prefix;
        $st = $this->pdo->prepare("SELECT ID, post_title, post_name, post_content FROM {$p}posts WHERE ID = :id AND post_type = 'page' LIMIT 1");
        $st->execute(['id' => $id]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $builder = function () use ($p, $id): string {
            $st = $this->pdo->prepare("SELECT meta_value FROM {$p}postmeta WHERE post_id = :id AND meta_key = '_variant_page_builder_html' LIMIT 1");
            $st->execute(['id' => $id]);
            return trim((string) ($st->fetchColumn() ?: ''));
        };
        if ($preferaBuilder) {
            $continut = $builder();
            if ($continut === '') {
                $continut = trim((string) $r['post_content']);
            }
        } else {
            $continut = trim((string) $r['post_content']);
            if ($continut === '') {
                $continut = $builder();
            }
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
