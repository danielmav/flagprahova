<?php
declare(strict_types=1);

namespace App\Admin;

use App\Database;
use PDO;

/**
 * Conturile de administrare. Un cont nou se creează fără parolă
 * (`parola_hash = ''`) și primește un link de setare — vezi
 * `PasswordTokenRepository`. `Auth::attempt()` refuză explicit un hash gol,
 * deci un cont neactivat nu poate intra.
 */
final class UtilizatoriRepository
{
    private PDO $pdo;

    public function __construct(Database $db)
    {
        $this->pdo = $db->pdo();
    }

    public function toti(): array
    {
        return $this->pdo->query('SELECT id, email, nume, ultimul_login, creat_la, parola_hash <> "" AS are_parola FROM utilizatori ORDER BY nume, email')->fetchAll();
    }

    public function numara(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM utilizatori')->fetchColumn();
    }

    public function gaseste(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM utilizatori WHERE id = :id');
        $st->execute(['id' => $id]);
        return $st->fetch() ?: null;
    }

    public function gasesteDupaEmail(string $email): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM utilizatori WHERE email = :e');
        $st->execute(['e' => $email]);
        return $st->fetch() ?: null;
    }

    public function creeaza(string $email, string $nume): int
    {
        $this->pdo->prepare('INSERT INTO utilizatori (email, nume, parola_hash) VALUES (:e, :n, "")')->execute(['e' => $email, 'n' => $nume]);
        return (int) $this->pdo->lastInsertId();
    }

    public function seteazaParola(int $id, string $parola): void
    {
        $this->pdo->prepare('UPDATE utilizatori SET parola_hash = :h WHERE id = :id')->execute(['h' => password_hash($parola, PASSWORD_DEFAULT), 'id' => $id]);
    }

    /** Refuză ștergerea ultimului cont: fără el nimeni n-ar mai putea intra în admin. */
    public function sterge(int $id): bool
    {
        if ($this->numara() <= 1) {
            return false;
        }
        return $this->pdo->prepare('DELETE FROM utilizatori WHERE id = :id')->execute(['id' => $id]);
    }
}
