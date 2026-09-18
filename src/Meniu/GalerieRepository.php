<?php
declare(strict_types=1);

namespace App\Meniu;

use App\Database;
use PDO;

final class GalerieRepository
{
    private PDO $pdo;

    public function __construct(Database $db) { $this->pdo = $db->pdo(); }

    public function imagini(int $meniuId): array
    {
        $st = $this->pdo->prepare('SELECT g.id, g.fisier_id, g.ordine, g.legenda, f.cale, f.nume_afisat
            FROM galerie_imagini g JOIN fisiere f ON f.id = g.fisier_id WHERE g.meniu_id = :m ORDER BY g.ordine, g.id');
        $st->execute(['m' => $meniuId]);
        return $st->fetchAll();
    }

    /** @param array<int, array{fisier_id:int, legenda:string}> $imagini */
    public function seteaza(int $meniuId, array $imagini): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM galerie_imagini WHERE meniu_id = :m')->execute(['m' => $meniuId]);
            $verif = $this->pdo->prepare("SELECT id FROM fisiere WHERE id = :id AND mime IN ('image/jpeg','image/png','image/webp')");
            $ins = $this->pdo->prepare('INSERT INTO galerie_imagini (meniu_id, fisier_id, ordine, legenda) VALUES (:m, :f, :o, :l)');
            $o = 0;
            foreach ($imagini as $img) {
                $verif->execute(['id' => (int) $img['fisier_id']]);
                if (!$verif->fetch()) { continue; }
                $ins->execute(['m' => $meniuId, 'f' => (int) $img['fisier_id'], 'o' => $o++, 'l' => mb_substr(trim((string) ($img['legenda'] ?? '')), 0, 255)]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
