<?php

declare(strict_types=1);

namespace App\Admin;

use App\Database;
use PDO;
use Throwable;

/**
 * Throttle pentru login-ul de back-office: numără încercările EȘUATE pe IP.
 *
 * Pe IP, nu pe cont, deliberat: blocarea contului după N eșecuri ar fi un buton
 * de DoS prin care oricine ar putea închide adminul afară din propriul
 * back-office, trimițând parole greșite pe adresa lui.
 *
 * `null` ca ip_hash (IP_SALT gol sau REMOTE_ADDR lipsă) înseamnă fail-open:
 * mai bine fără throttle decât blocat afară din admin de o variabilă de mediu.
 */
final class LoginThrottle
{
    /** Câte eșecuri acceptăm de la același IP în fereastră. */
    private const MAX_FAILED = 5;

    private ?PDO $pdo;

    /**
     * @param string $scope separă throttle-urile care împart tabela
     *   `login_incercari`: 'admin' (login back-office) vs alte fluxuri viitoare.
     */
    public function __construct(Database $db, private string $scope = 'admin')
    {
        try {
            $this->pdo = $db->pdo();
        } catch (Throwable) {
            $this->pdo = null;
        }
    }

    public function tooMany(?string $ipHash, int $minutes = 15): bool
    {
        if (!$this->pdo || $ipHash === null || $ipHash === '') {
            return false;
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM login_incercari
                 WHERE ip_hash = :h AND scope = :sc AND la > (NOW() - INTERVAL :m MINUTE)'
            );
            $stmt->bindValue('h', $ipHash);
            $stmt->bindValue('sc', $this->scope);
            $stmt->bindValue('m', $minutes, PDO::PARAM_INT);
            $stmt->execute();
            return (int) $stmt->fetchColumn() >= self::MAX_FAILED;
        } catch (Throwable $e) {
            error_log('[flagprahova][throttle] verificare esuata (posibil tabela login_incercari lipsa / migrarea nerulata): ' . $e->getMessage());
            return false;
        }
    }

    public function record(?string $ipHash): void
    {
        if (!$this->pdo || $ipHash === null || $ipHash === '') {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('INSERT INTO login_incercari (ip_hash, scope) VALUES (:h, :sc)');
            $stmt->execute(['h' => $ipHash, 'sc' => $this->scope]);
        } catch (Throwable $e) {
            error_log('[flagprahova][throttle] nu am putut inregistra incercarea: ' . $e->getMessage());
        }
    }

    /** După un login reușit, IP-ul pornește de la zero — DOAR pe scope-ul curent. */
    public function clear(?string $ipHash): void
    {
        if (!$this->pdo || $ipHash === null || $ipHash === '') {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('DELETE FROM login_incercari WHERE ip_hash = :h AND scope = :sc');
            $stmt->execute(['h' => $ipHash, 'sc' => $this->scope]);
        } catch (Throwable) {
            // Curățenia e un bonus; un eșec aici nu trebuie să strice login-ul.
        }
    }
}
