<?php
declare(strict_types=1);

namespace App\Setari;

use App\Database;
use PDO;

/**
 * Setările editabile din admin: perechi cheie/valoare în tabela `setari`.
 *
 * Citirea se face o singură dată per cerere (`$cache`), pentru că layout-ul
 * public cere de regulă mai multe chei; `set()` invalidează cache-ul.
 */
final class Repository
{
    public const CHEI = ['contact_email_destinatar', 'landing_titlu', 'landing_text', 'footer_text'];

    private PDO $pdo;
    private ?array $cache = null;

    public function __construct(Database $db)
    {
        $this->pdo = $db->pdo();
    }

    public function toate(): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            foreach ($this->pdo->query('SELECT cheie, valoare FROM setari')->fetchAll() as $r) {
                $this->cache[$r['cheie']] = (string) $r['valoare'];
            }
        }
        return $this->cache;
    }

    public function get(string $cheie, string $implicit = ''): string
    {
        return $this->toate()[$cheie] ?? $implicit;
    }

    public function set(string $cheie, string $valoare): void
    {
        $this->pdo->prepare('INSERT INTO setari (cheie, valoare) VALUES (:c, :v) ON DUPLICATE KEY UPDATE valoare = VALUES(valoare)')
            ->execute(['c' => $cheie, 'v' => $valoare]);
        $this->cache = null;
    }
}
