<?php

declare(strict_types=1);

namespace App\Admin;

use App\Database;
use PDO;
use Throwable;

/**
 * Link-uri de setare a parolei pentru conturile de administrare.
 *
 * Reguli: tokenul BRUT există doar în email, în DB ținem doar `sha256(raw)`,
 * e single-use, iar consumul e atomic (`UPDATE ... WHERE folosit_la IS NULL`).
 *
 * TTL de **7 zile**, nu minute: ăsta nu e un login rapid, ci o invitație de
 * cont pe care destinatarul o deschide „când ajunge la birou". Compensăm
 * durata prin faptul că un token nou îl invalidează pe cel vechi
 * (`invalideazaPentru()`), deci nu se acumulează invitații valabile.
 */
final class PasswordTokenRepository
{
    /** Cât trăiește o invitație de setare a parolei. */
    public const TTL_ZILE = 7;

    private ?PDO $pdo;

    public function __construct(Database $db)
    {
        try {
            $this->pdo = $db->pdo();
        } catch (Throwable) {
            $this->pdo = null;
        }
    }

    public function isAvailable(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * Emite un token proaspăt. Întoarce tokenul BRUT (de pus în link), null la
     * eșec.
     *
     * `$invalideazaVechi` controlează CÂND moare invitația anterioară. Implicit
     * moare imediat, ca la „Trimite link" repetat: altfel fiecare retrimitere
     * ar lăsa în urmă încă un link valabil 7 zile, iar un email vechi recuperat
     * dintr-o inbox ar rămâne o cale de intrare.
     *
     * Apelantul care chiar trimite emailul dă `false` și, DOAR dacă mesajul a
     * plecat, apelează `pastreazaDoar()`. Altfel, un SMTP căzut ar arde
     * invitația veche fără să livreze una nouă — utilizatorul ar rămâne fără
     * nicio cale de intrare.
     */
    public function issue(int $utilizatorId, bool $invalideazaVechi = true): ?string
    {
        if (!$this->pdo) {
            return null;
        }
        $raw = bin2hex(random_bytes(32));
        try {
            if ($invalideazaVechi) {
                $this->invalideazaPentru($utilizatorId);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO parola_tokens (utilizator_id, token_hash, expira_la)
                 VALUES (:u, :h, (NOW() + INTERVAL ' . self::TTL_ZILE . ' DAY))'
            );
            $stmt->execute(['u' => $utilizatorId, 'h' => hash('sha256', $raw)]);
            return $raw;
        } catch (Throwable $e) {
            error_log('[flagprahova][admin] emitere token parola esuata: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verifică un token FĂRĂ să-l consume — pentru afișarea formularului.
     *
     * Separarea de `consume()` e importantă: dacă am consuma la GET, un
     * prefetch de link din client-ul de email ar arde invitația înainte ca
     * omul s-o vadă.
     */
    public function esteValabil(string $raw): bool
    {
        return $this->peek($raw) !== null;
    }

    /** Ca `esteValabil()`, dar întoarce id-ul utilizatorului sau null. */
    public function peek(string $raw): ?int
    {
        if (!$this->pdo || $raw === '') {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT utilizator_id FROM parola_tokens
                 WHERE token_hash = :h AND folosit_la IS NULL AND expira_la > NOW()
                 LIMIT 1'
            );
            $stmt->execute(['h' => hash('sha256', $raw)]);
            $row = $stmt->fetch();
            return $row === false ? null : (int) $row['utilizator_id'];
        } catch (Throwable $e) {
            error_log('[flagprahova][admin] verificare token parola esuata: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Consumă un token: valid, nefolosit ȘI neexpirat → îl marchează folosit și
     * întoarce `utilizator_id`. Orice abatere → null.
     *
     * Marcarea e atomică (`WHERE folosit_la IS NULL`): dacă `rowCount()` nu e
     * 1, altcineva l-a consumat între timp.
     */
    public function consume(string $raw): ?int
    {
        if (!$this->pdo || $raw === '') {
            return null;
        }
        try {
            $hash = hash('sha256', $raw);
            $sel = $this->pdo->prepare(
                'SELECT id, utilizator_id FROM parola_tokens
                 WHERE token_hash = :h AND folosit_la IS NULL AND expira_la > NOW()
                 LIMIT 1'
            );
            $sel->execute(['h' => $hash]);
            $row = $sel->fetch();
            if ($row === false) {
                return null;
            }

            $upd = $this->pdo->prepare(
                'UPDATE parola_tokens SET folosit_la = NOW()
                 WHERE token_hash = :h AND folosit_la IS NULL AND expira_la > NOW()'
            );
            $upd->execute(['h' => $hash]);
            if ($upd->rowCount() !== 1) {
                return null; // consumat între timp
            }
            return (int) $row['utilizator_id'];
        } catch (Throwable $e) {
            error_log('[flagprahova][admin] consum token parola esuat: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Momentul până la care mai e valabilă o invitație nefolosită, sau null
     * dacă nu există niciuna. Folosit doar la afișarea stării în listă.
     */
    public function valabilPanaLa(int $utilizatorId): ?string
    {
        if (!$this->pdo) {
            return null;
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT expira_la FROM parola_tokens
                 WHERE utilizator_id = :u AND folosit_la IS NULL AND expira_la > NOW()
                 ORDER BY expira_la DESC LIMIT 1'
            );
            $stmt->execute(['u' => $utilizatorId]);
            $v = $stmt->fetchColumn();
            return $v === false ? null : (string) $v;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Lasă valabil DOAR tokenul dat; le marchează folosite pe toate celelalte
     * ale aceluiași cont. Se apelează după ce emailul a plecat cu adevărat.
     */
    public function pastreazaDoar(int $utilizatorId, string $raw): void
    {
        if (!$this->pdo || $raw === '') {
            return;
        }
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE parola_tokens SET folosit_la = NOW()
                 WHERE utilizator_id = :u AND folosit_la IS NULL AND token_hash <> :h'
            );
            $stmt->execute(['u' => $utilizatorId, 'h' => hash('sha256', $raw)]);
        } catch (Throwable $e) {
            error_log('[flagprahova][admin] pastrare token nou esuata: ' . $e->getMessage());
        }
    }

    /**
     * Arde un singur token — folosit când emailul care-l conținea n-a plecat.
     * Invitația anterioară a contului rămâne neatinsă, deci valabilă.
     */
    public function invalideazaToken(string $raw): void
    {
        if (!$this->pdo || $raw === '') {
            return;
        }
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE parola_tokens SET folosit_la = NOW()
                 WHERE token_hash = :h AND folosit_la IS NULL'
            );
            $stmt->execute(['h' => hash('sha256', $raw)]);
        } catch (Throwable $e) {
            error_log('[flagprahova][admin] invalidare token esuata: ' . $e->getMessage());
        }
    }

    /** Marchează folosite toate tokenurile nefolosite ale unui cont. */
    public function invalideazaPentru(int $utilizatorId): void
    {
        if (!$this->pdo) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE parola_tokens SET folosit_la = NOW()
                 WHERE utilizator_id = :u AND folosit_la IS NULL'
            );
            $stmt->execute(['u' => $utilizatorId]);
        } catch (Throwable $e) {
            error_log('[flagprahova][admin] invalidare tokenuri esuata: ' . $e->getMessage());
        }
    }
}
