<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Fabrică de conexiuni PDO către baza de date FLAG Prahova (MySQL).
 * Conexiunea e leneșă și memoizată. Prepared statements peste tot, fără emulare.
 */
final class Database
{
    private static ?PDO $pdo = null;

    /** @param array<string,mixed> $config secțiunea 'db' din settings */
    public function __construct(private array $config) {}

    public function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $this->config['host'],
            $this->config['port'],
            $this->config['name']
        );

        $pdo = new PDO($dsn, $this->config['user'], $this->config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Fusul SESIUNII MySQL, aliniat pe cel al lui PHP (pus în
        // `src/Support/helpers.php`, autoîncărcat peste tot).
        //
        // Fără asta, PHP și MySQL răspund la „ce zi e azi?" din două ceasuri
        // diferite: `CURDATE()`/`NOW()` vin de pe fusul SERVERULUI de baze de
        // date, pe care nu-l controlăm — pe cPanel poate fi orice. Interogări ca
        // `CURDATE() BETWEEN p.data_start AND p.data_final`
        // compară un „azi" al bazei cu date scrise
        // din PHP; dacă cele două ceasuri diferă, o promovare care începe azi
        // pare expirată. S-a manifestat exact așa, între 21:00 și 24:00 UTC.
        //
        // Se trimite OFFSETUL (`+03:00`), nu numele fusului: `SET time_zone =
        // 'Europe/Bucharest'` cere tabelele `mysql.time_zone*`, care pe hosting
        // partajat sunt de regulă nepopulate, și ar arunca. Offsetul e cel de
        // ACUM, deci ține cont de ora de vară — iar conexiunile trăiesc cât o
        // cerere, deci nu apucă să traverseze o schimbare de oră.
        //
        // Eșecul NU e fatal: o bază care refuză `SET time_zone` rămâne pe fusul
        // ei, adică exact comportamentul de dinainte. Mai bine un decalaj decât
        // un sit căzut.
        try {
            $stmt = $pdo->prepare('SET time_zone = :tz');
            $stmt->execute(['tz' => date('P')]);
        } catch (\Throwable $e) {
            error_log('[flagprahova][db] nu am putut fixa time_zone: ' . $e->getMessage());
        }

        return self::$pdo = $pdo;
    }
}
