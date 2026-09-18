<?php
declare(strict_types=1);

namespace App\Meniu;

use App\Database;
use InvalidArgumentException;
use PDO;

final class Repository
{
    public const TIPURI  = ['pagina', 'document', 'dosar', 'link', 'galerie'];
    public const SABLOANE = ['standard', 'contact'];
    private const COLOANE = ['parent_id', 'titlu', 'slug', 'tip', 'continut_html', 'fisier_id', 'url', 'sablon', 'vizibil', 'legacy_id'];

    private PDO $pdo;

    public function __construct(Database $db)
    {
        $this->pdo = $db->pdo();
    }

    public function sectiuni(): array
    {
        return $this->pdo->query('SELECT * FROM sectiuni ORDER BY ordine, id')->fetchAll();
    }

    public function sectiuneDupaSlug(string $slug): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM sectiuni WHERE slug = :s LIMIT 1');
        $st->execute(['s' => $slug]);
        return $st->fetch() ?: null;
    }

    public function arbore(int $sectiuneId, bool $doarVizibile = false): array
    {
        $sql = 'SELECT id, parent_id, ordine, titlu, slug, tip, url, fisier_id, vizibil FROM meniu WHERE sectiune_id = :s'
            . ($doarVizibile ? ' AND vizibil = 1' : '') . ' ORDER BY ordine, id';
        $st = $this->pdo->prepare($sql);
        $st->execute(['s' => $sectiuneId]);
        $noduri = [];
        foreach ($st->fetchAll() as $r) {
            $r['copii'] = [];
            $noduri[(int) $r['id']] = $r;
        }
        $radacini = [];
        foreach ($noduri as $id => &$n) {
            $p = $n['parent_id'] === null ? null : (int) $n['parent_id'];
            if ($p !== null && isset($noduri[$p])) {
                $noduri[$p]['copii'][] = &$n;
            } else {
                $radacini[] = &$n;
            }
        }
        unset($n);
        return $radacini;
    }

    public function gaseste(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM meniu WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function gasesteDupaSlug(int $sectiuneId, string $slug): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM meniu WHERE sectiune_id = :s AND slug = :sl LIMIT 1');
        $st->execute(['s' => $sectiuneId, 'sl' => $slug]);
        return $st->fetch() ?: null;
    }

    public function copii(int $parentId): array
    {
        $st = $this->pdo->prepare('SELECT * FROM meniu WHERE parent_id = :p ORDER BY ordine, id');
        $st->execute(['p' => $parentId]);
        return $st->fetchAll();
    }

    public function slugUnic(int $sectiuneId, string $slug, ?int $exceptId = null): string
    {
        $baza = slugify($slug);
        $cand = $baza;
        for ($i = 2; $i < 1000; $i++) {
            $st = $this->pdo->prepare('SELECT id FROM meniu WHERE sectiune_id = :s AND slug = :sl AND id <> :ex LIMIT 1');
            $st->execute(['s' => $sectiuneId, 'sl' => $cand, 'ex' => $exceptId ?? 0]);
            if (!$st->fetch()) {
                return $cand;
            }
            $cand = $baza . '-' . $i;
        }
        return $baza . '-' . bin2hex(random_bytes(3));
    }

    public function creeaza(array $date): int
    {
        $sid    = (int) $date['sectiune_id'];
        $parent = isset($date['parent_id']) && $date['parent_id'] !== '' && $date['parent_id'] !== null ? (int) $date['parent_id'] : null;
        $slug   = $this->slugUnic($sid, ($date['slug'] ?? '') !== '' ? (string) $date['slug'] : (string) $date['titlu']);
        $st = $this->pdo->prepare('SELECT COALESCE(MAX(ordine), -1) + 1 FROM meniu WHERE sectiune_id = :s AND ' . ($parent === null ? 'parent_id IS NULL' : 'parent_id = :p'));
        $st->execute($parent === null ? ['s' => $sid] : ['s' => $sid, 'p' => $parent]);
        $ordine = (int) $st->fetchColumn();

        $st = $this->pdo->prepare('INSERT INTO meniu (sectiune_id, parent_id, ordine, titlu, slug, tip, continut_html, fisier_id, url, sablon, vizibil, legacy_id)
            VALUES (:s, :p, :o, :t, :sl, :tip, :c, :f, :u, :sab, :v, :lg)');
        $st->execute([
            's' => $sid, 'p' => $parent, 'o' => $ordine,
            't' => trim((string) $date['titlu']), 'sl' => $slug,
            'tip' => in_array($date['tip'] ?? '', self::TIPURI, true) ? $date['tip'] : 'document',
            'c' => $date['continut_html'] ?? null,
            'f' => isset($date['fisier_id']) && $date['fisier_id'] !== '' ? (int) $date['fisier_id'] : null,
            'u' => ($date['url'] ?? '') !== '' ? (string) $date['url'] : null,
            'sab' => in_array($date['sablon'] ?? '', self::SABLOANE, true) ? $date['sablon'] : 'standard',
            'v' => (int) ($date['vizibil'] ?? 1),
            'lg' => isset($date['legacy_id']) ? (int) $date['legacy_id'] : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function actualizeaza(int $id, array $date): void
    {
        $curent = $this->gaseste($id);
        if ($curent === null) {
            return;
        }
        $sid = (int) $curent['sectiune_id'];
        if (array_key_exists('slug', $date) || array_key_exists('titlu', $date)) {
            $date['slug'] = $this->slugUnic($sid, ($date['slug'] ?? '') !== '' ? (string) $date['slug'] : (string) ($date['titlu'] ?? $curent['titlu']), $id);
        }
        if (array_key_exists('parent_id', $date)) {
            $date['parent_id'] = $date['parent_id'] === '' || $date['parent_id'] === null ? null : (int) $date['parent_id'];
            if ($date['parent_id'] === $id) {
                $date['parent_id'] = $curent['parent_id'];
            }
        }
        $set = [];
        $par = ['id' => $id];
        foreach (self::COLOANE as $c) {
            if (array_key_exists($c, $date)) {
                $set[]   = "$c = :$c";
                $par[$c] = $date[$c] === '' && in_array($c, ['fisier_id', 'url', 'continut_html', 'legacy_id'], true) ? null : $date[$c];
            }
        }
        if ($set === []) {
            return;
        }
        $this->pdo->prepare('UPDATE meniu SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($par);
    }

    public function numaraDescendenti(int $id): int
    {
        $n = 0;
        foreach ($this->copii($id) as $c) {
            $n += 1 + $this->numaraDescendenti((int) $c['id']);
        }
        return $n;
    }

    public function sterge(int $id): int
    {
        $total = 1 + $this->numaraDescendenti($id);
        $st = $this->pdo->prepare('DELETE FROM meniu WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->rowCount() === 0 ? 0 : $total;
    }

    public function reordoneaza(int $sectiuneId, array $arbore): void
    {
        $st = $this->pdo->prepare('SELECT id FROM meniu WHERE sectiune_id = :s');
        $st->execute(['s' => $sectiuneId]);
        $permise = array_flip(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
        $upd = $this->pdo->prepare('UPDATE meniu SET parent_id = :p, ordine = :o WHERE id = :id AND sectiune_id = :s');
        $this->pdo->beginTransaction();
        try {
            $parcurge = function (array $noduri, ?int $parent) use (&$parcurge, $permise, $upd, $sectiuneId): void {
                foreach (array_values($noduri) as $i => $n) {
                    $id = (int) ($n['id'] ?? 0);
                    if (!isset($permise[$id])) {
                        throw new InvalidArgumentException("Intrarea $id nu aparține secțiunii $sectiuneId");
                    }
                    $upd->execute(['p' => $parent, 'o' => $i, 'id' => $id, 's' => $sectiuneId]);
                    $parcurge((array) ($n['copii'] ?? []), $id);
                }
            };
            $parcurge($arbore, null);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function fisierFolosit(int $fisierId): array
    {
        $st = $this->pdo->prepare('SELECT cale FROM fisiere WHERE id = :id');
        $st->execute(['id' => $fisierId]);
        $cale = (string) $st->fetchColumn();
        $st = $this->pdo->prepare('SELECT DISTINCT m.id, m.titlu FROM meniu m
            LEFT JOIN galerie_imagini g ON g.meniu_id = m.id
            WHERE m.fisier_id = :f1 OR g.fisier_id = :f2 OR (:cale <> \'\' AND m.continut_html LIKE :like)
            ORDER BY m.titlu');
        $st->execute(['f1' => $fisierId, 'f2' => $fisierId, 'cale' => $cale, 'like' => '%' . $cale . '%']);
        return $st->fetchAll();
    }
}
