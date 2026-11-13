<?php
declare(strict_types=1);

namespace App\Comentarii;

use App\Database;
use PDO;

/**
 * Întrebările și comentariile la ghiduri (dosare cu `meniu.comentarii = 1`).
 * Un comentariu nou are starea `nou` și devine public doar după aprobare
 * (`publicat`). Emailul autorului e doar pentru administrare.
 */
final class Repository
{
    public const STARI = ['nou', 'publicat', 'respins'];

    private PDO $pdo;

    public function __construct(Database $db)
    {
        $this->pdo = $db->pdo();
    }

    /** Comentariile aprobate ale unei intrări, cele mai vechi primele (firul se citește de sus în jos). */
    public function publicate(int $meniuId): array
    {
        $st = $this->pdo->prepare("SELECT id, nume, mesaj, raspuns, raspuns_la, trimis_la FROM comentarii
            WHERE meniu_id = :m AND stare = 'publicat' ORDER BY trimis_la, id");
        $st->execute(['m' => $meniuId]);
        return $st->fetchAll();
    }

    public function adauga(int $meniuId, string $nume, string $email, string $mesaj, ?string $ipHash): int
    {
        $this->pdo->prepare('INSERT INTO comentarii (meniu_id, nume, email, mesaj, ip_hash) VALUES (:m, :n, :e, :t, :ip)')
            ->execute(['m' => $meniuId, 'n' => $nume, 'e' => $email, 't' => $mesaj, 'ip' => $ipHash]);
        return (int) $this->pdo->lastInsertId();
    }

    public function marcheazaEmailTrimis(int $id): void
    {
        $this->pdo->prepare('UPDATE comentarii SET email_trimis = 1 WHERE id = :id')->execute(['id' => $id]);
    }

    /** Câte comentarii a trimis IP-ul în ultima oră (pentru throttle). */
    public function recenteDeLa(string $ipHash): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM comentarii WHERE ip_hash = :h AND trimis_la > (NOW() - INTERVAL 1 HOUR)');
        $st->execute(['h' => $ipHash]);
        return (int) $st->fetchColumn();
    }

    /** Lista pentru admin, cu titlul intrării și slug-ul secțiunii; `$stare` null = toate. */
    public function lista(?string $stare = null): array
    {
        $sql = 'SELECT c.*, m.titlu AS intrare_titlu, m.slug AS intrare_slug, s.slug AS sectiune_slug
            FROM comentarii c JOIN meniu m ON m.id = c.meniu_id JOIN sectiuni s ON s.id = m.sectiune_id';
        if ($stare !== null && in_array($stare, self::STARI, true)) {
            $st = $this->pdo->prepare($sql . ' WHERE c.stare = :st ORDER BY c.trimis_la DESC, c.id DESC');
            $st->execute(['st' => $stare]);
            return $st->fetchAll();
        }
        return $this->pdo->query($sql . ' ORDER BY c.trimis_la DESC, c.id DESC')->fetchAll();
    }

    public function gaseste(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT c.*, m.titlu AS intrare_titlu, m.slug AS intrare_slug, s.slug AS sectiune_slug
            FROM comentarii c JOIN meniu m ON m.id = c.meniu_id JOIN sectiuni s ON s.id = m.sectiune_id WHERE c.id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    /** Moderare: stare, text (ex. scoaterea datelor personale) și răspunsul FLAG. */
    public function actualizeaza(int $id, string $stare, string $nume, string $mesaj, string $raspuns): void
    {
        $raspuns = trim($raspuns);
        $this->pdo->prepare('UPDATE comentarii SET stare = :st, nume = :n, mesaj = :t,
                raspuns_la = CASE WHEN :r1 = \'\' THEN NULL WHEN raspuns <=> :r2 THEN raspuns_la ELSE NOW() END,
                raspuns = NULLIF(:r3, \'\')
            WHERE id = :id')
            ->execute(['st' => in_array($stare, self::STARI, true) ? $stare : 'nou', 'n' => $nume, 't' => $mesaj,
                'r1' => $raspuns, 'r2' => $raspuns, 'r3' => $raspuns, 'id' => $id]);
    }

    public function sterge(int $id): void
    {
        $this->pdo->prepare('DELETE FROM comentarii WHERE id = :id')->execute(['id' => $id]);
    }

    public function numarNoi(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM comentarii WHERE stare = 'nou'")->fetchColumn();
    }
}
