<?php

declare(strict_types=1);

namespace App\Form;

/**
 * Token anti-spam „capcană de timp": „<timestamp>.<hmac>", semnat cu cheia
 * de sesiune CSRF (`$_SESSION['csrf']`).
 *
 * `Bootstrap` garantează că `$_SESSION['csrf']` există (64 caractere hex) la
 * orice cerere web, iar `Form\Controller::csrfOk()` rulează mereu înainte de
 * verificarea acestui token și respinge o sesiune fără el — deci nu există
 * niciun fallback de cheie aici. Un fallback hardcodat ar fi un secret slab
 * într-o cale critică de securitate, fără niciun beneficiu real.
 *
 * Extras într-un singur loc fiindcă algoritmul era duplicat în două
 * controllere (Form\Controller la randare+verificare, Location\Controller
 * doar la randare) — dacă se schimbă hash-ul sau se adaugă un pepper într-un
 * singur loc, cele două se desincronizează silențios și `isBot()` respinge
 * orice cerere reală, fără nicio eroare vizibilă.
 */
final class TimeToken
{
    /** Sub atâtea secunde de la randare, e bot, nu om. */
    private const MIN_FILL_SECONDS = 3;

    /** Emite un token proaspăt pentru randarea unui formular. */
    public static function mint(): string
    {
        $ts = (string) time();
        return $ts . '.' . self::sign($ts);
    }

    /**
     * Verifică un token primit la submit: format valid (exact două părți),
     * semnătură corectă ȘI vechime de cel puțin `MIN_FILL_SECONDS`.
     * Orice abatere de la oricare condiție înseamnă token invalid.
     */
    public static function isValidAndAged(string $token): bool
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }
        [$ts, $sig] = $parts;
        if (!hash_equals(self::sign($ts), $sig)) {
            return false;
        }
        return (time() - (int) $ts) >= self::MIN_FILL_SECONDS;
    }

    private static function sign(string $ts): string
    {
        // Fără fallback: `Bootstrap` garantează `$_SESSION['csrf']` la orice
        // cerere web, iar `csrfOk()` respinge deja o sesiune fără el înainte
        // să ajungem aici. Dacă lipsește totuși, tokenul trebuie să fie
        // nefolosibil, nu semnat tăcut cu un secret fix.
        return hash_hmac('sha256', $ts, (string) $_SESSION['csrf']);
    }
}
