<?php
declare(strict_types=1);

namespace App\Fisiere;

use App\Database;
use PDO;

final class Repository
{
    private PDO $pdo;

    public function __construct(Database $db)
    {
        $this->pdo = $db->pdo();
    }

    public function inregistreaza(array $r): int
    {
        $ex = $this->gasesteDupaCale((string) $r['cale']);
        if ($ex !== null) {
            $this->pdo->prepare('UPDATE fisiere SET nume_afisat = :n, mime = :m, marime = :s, legacy_url = COALESCE(:lg, legacy_url) WHERE id = :id')
                ->execute(['n' => $r['nume_afisat'], 'm' => $r['mime'], 's' => (int) $r['marime'], 'lg' => $r['legacy_url'] ?? null, 'id' => $ex['id']]);
            return (int) $ex['id'];
        }
        $this->pdo->prepare('INSERT INTO fisiere (nume_afisat, cale, mime, marime, incarcat_de, legacy_url) VALUES (:n, :c, :m, :s, :u, :lg)')
            ->execute(['n' => $r['nume_afisat'], 'c' => $r['cale'], 'm' => $r['mime'], 's' => (int) $r['marime'],
                       'u' => $r['incarcat_de'] ?? null, 'lg' => $r['legacy_url'] ?? null]);
        return (int) $this->pdo->lastInsertId();
    }

    public function gaseste(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM fisiere WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function gasesteDupaCale(string $cale): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM fisiere WHERE cale = :c');
        $st->execute(['c' => $cale]);
        return $st->fetch() ?: null;
    }

    public function lista(?string $an, ?string $luna, string $cauta = '', bool $doarImagini = false): array
    {
        $w = []; $p = [];
        if ($an !== null && $an !== '') { $w[] = 'cale LIKE :an'; $p['an'] = $an . '/%'; }
        if ($luna !== null && $luna !== '' && $an) { $w[] = 'cale LIKE :luna'; $p['luna'] = $an . '/' . $luna . '/%'; }
        if ($cauta !== '') { $w[] = '(nume_afisat LIKE :q1 OR cale LIKE :q2)'; $p['q1'] = '%' . $cauta . '%'; $p['q2'] = '%' . $cauta . '%'; }
        if ($doarImagini) { $w[] = "mime IN ('image/jpeg','image/png','image/webp')"; }
        $sql = 'SELECT * FROM fisiere' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY incarcat_la DESC, id DESC LIMIT 500';
        $st = $this->pdo->prepare($sql);
        $st->execute($p);
        return $st->fetchAll();
    }

    public function aniLuni(): array
    {
        $rows = $this->pdo->query("SELECT DISTINCT SUBSTRING(cale, 1, 4) an, SUBSTRING(cale, 6, 2) luna FROM fisiere ORDER BY an DESC, luna DESC")->fetchAll();
        $out = [];
        foreach ($rows as $r) { $out[(string) $r['an']][] = (string) $r['luna']; }
        return array_map(fn($an, $luni) => ['an' => (string) $an, 'luni' => $luni], array_keys($out), $out);
    }

    public function redenumeste(int $id, string $numeAfisat): void
    {
        $this->pdo->prepare('UPDATE fisiere SET nume_afisat = :n WHERE id = :id')->execute(['n' => trim($numeAfisat), 'id' => $id]);
    }

    public function sterge(int $id, string $dirAbs): bool
    {
        $f = $this->gaseste($id);
        if ($f === null) { return false; }
        $abs = rtrim($dirAbs, '/\\') . '/' . $f['cale'];
        if (is_file($abs)) { @unlink($abs); }
        return $this->pdo->prepare('DELETE FROM fisiere WHERE id = :id')->execute(['id' => $id]);
    }
}
