<?php

declare(strict_types=1);

namespace App\Admin;

use App\Database;
use PDO;
use Throwable;

/**
 * Singurul loc care știe forma unei sesiuni de admin.
 *
 * Nu verifică throttle-ul — asta e treaba controllerului, care decide și ce
 * mesaj arată. Aici e doar: credențialele sunt bune sau nu.
 */
final class Auth
{
    /** Cheia sub care ținem adminul logat în sesiune. */
    private const SESSION_KEY = 'admin_user';

    /**
     * Hash fals, folosit ca să verificăm parola și când emailul NU există.
     * Fără el, răspunsul pentru un email inexistent vine vizibil mai repede
     * (sărim `password_verify`), iar diferența de timp spune atacatorului ce
     * adrese au cont. Generat cu `password_hash()`, deci `password_verify`
     * face aceeași muncă reală ca pentru un utilizator adevărat.
     */
    private const DUMMY_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    private ?PDO $pdo;

    public function __construct(Database $db)
    {
        try {
            $this->pdo = $db->pdo();
        } catch (Throwable) {
            $this->pdo = null;
        }
    }

    /**
     * Verifică credențialele și, la succes, deschide sesiunea de admin.
     * Mesajul de eroare îl alege apelantul — și trebuie să fie același pentru
     * „parolă greșită" și „email inexistent".
     */
    public function attempt(string $email, string $parola): bool
    {
        if (!$this->pdo) {
            return false;
        }

        $user = null;
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, email, nume, parola_hash FROM utilizatori WHERE email = :e LIMIT 1'
            );
            $stmt->execute(['e' => $email]);
            $row = $stmt->fetch();
            $user = $row === false ? null : $row;
        } catch (Throwable $e) {
            error_log('[flagprahova][admin] cautare utilizator esuata: ' . $e->getMessage());
            return false;
        }

        // Verificăm mereu, chiar dacă utilizatorul nu există — vezi DUMMY_HASH.
        $hashStocat = (string) ($user['parola_hash'] ?? '');
        $hash       = $hashStocat !== '' ? $hashStocat : self::DUMMY_HASH;
        $parolaOk   = password_verify($parola, $hash);

        if ($user === null || $hashStocat === '' || !$parolaOk) {
            return false;
        }

        // Sesiunea + tokenul CSRF există deja pentru orice vizitator anonim
        // (vezi Bootstrap), deci fără regenerare cineva ar putea fixa un ID de
        // sesiune înainte de login și ar moșteni sesiunea de admin după.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            // session_regenerate_id() schimbă doar ID-ul de sesiune, NU și
            // conținutul $_SESSION — tokenul CSRF minat de Bootstrap pentru
            // vizitatorul anonim ar supraviețui neschimbat în sesiunea de
            // admin. Îl regenerăm explicit, cu aceeași rețetă ca Bootstrap.
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        // În sesiune ținem doar identitatea, niciodată hash-ul parolei.
        $_SESSION[self::SESSION_KEY] = [
            'id'    => (int) $user['id'],
            'email' => (string) $user['email'],
            'nume'  => (string) $user['nume'],
        ];

        $this->touchLastLogin((int) $user['id']);

        return true;
    }

    public function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]['id']);
    }

    /** @return array{id:int,email:string,nume:string}|null */
    public function user(): ?array
    {
        return $this->check() ? $_SESSION[self::SESSION_KEY] : null;
    }

    /**
     * Identitatea CURENTĂ, citită din bază, nu din sesiune.
     *
     * Sesiunea e o fotografie făcută la login. Fără reverificare, ștergerea
     * unui cont n-ar avea niciun efect asupra cuiva deja conectat.
     *
     * @return array{id:int,email:string,nume:string}|null
     *         null = fără sesiune sau cont șters
     */
    public function utilizatorCurent(): ?array
    {
        $sesiune = $this->user();
        if ($sesiune === null || !$this->pdo) {
            // Fără DB nu putem reverifica. Ne bazăm pe sesiune: a refuza aici ar
            // însemna că o bază picată închide adminul afară exact când are
            // nevoie să intre.
            return $sesiune;
        }

        if ($this->reverificat !== null) {
            return $this->reverificat === false ? null : $this->reverificat;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT id, email, nume FROM utilizatori WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $sesiune['id']]);
            $row = $stmt->fetch();

            if ($row === false) {
                $this->reverificat = false;
                return null;
            }

            $proaspat = [
                'id'    => (int) $row['id'],
                'email' => (string) $row['email'],
                'nume'  => (string) $row['nume'],
            ];
            // Ținem sesiunea sincronizată, ca layout-ul să nu arate date vechi.
            $_SESSION[self::SESSION_KEY] = $proaspat;
            $this->reverificat = $proaspat;
            return $proaspat;
        } catch (Throwable $e) {
            error_log('[flagprahova][admin] reverificare cont esuata: ' . $e->getMessage());
            return $sesiune;
        }
    }

    /** Memoizare pe cerere: `null` = neverificat, `false` = respins. */
    private array|false|null $reverificat = null;

    public function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            // La fel ca la login: regenerarea ID-ului de sesiune nu atinge
            // $_SESSION, deci tokenul CSRF vechi ar rămâne valabil și după
            // logout dacă nu-l regenerăm explicit aici.
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
    }

    private function touchLastLogin(int $id): void
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE utilizatori SET ultimul_login = NOW() WHERE id = :id');
            $stmt->execute(['id' => $id]);
        } catch (Throwable) {
            // Un ultimul_login neactualizat nu e motiv să refuzi login-ul.
        }
    }
}
