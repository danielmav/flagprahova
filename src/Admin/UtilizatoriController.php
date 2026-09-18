<?php
declare(strict_types=1);

namespace App\Admin;

use App\Mail\Mailer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * Conturi de administrare + fluxul de parolă (invitație și „parolă uitată").
 *
 * Contul nou se creează fără parolă și primește prin email un link de setare;
 * parola nu trece niciodată prin mâna celui care invită.
 */
final class UtilizatoriController
{
    use Helpers;

    /** Durata fixă, în milisecunde, a unui POST pe „parolă uitată". */
    private const PRAG_MS = 1500;

    private Auth $auth;
    private array $settings;
    private UtilizatoriRepository $utilizatori;
    private PasswordTokenRepository $tokens;
    private Mailer $mailer;
    private LoginThrottle $throttle;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth        = $container['auth'];
        $this->settings    = $container['settings'];
        $this->utilizatori = $container['utilizatori'];
        $this->tokens      = $container['parola_tokens'];
        $this->mailer      = $container['mailer'];
        $this->throttle    = $container['parola_throttle'];
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->render($response, 'admin/utilizatori.twig', ['lista' => $this->utilizatori->toti()]);
    }

    public function adauga(Request $request, Response $response): Response
    {
        $in    = (array) $request->getParsedBody();
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $nume  = trim((string) ($in['nume'] ?? ''));
        if (!$this->csrfOk($request)) {
            $this->flash('eroare', 'Sesiunea a expirat. Reîncarcă pagina.');
            return $this->redirect($response, '/utilizatori');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('eroare', 'Adresa de email nu e validă.');
            return $this->redirect($response, '/utilizatori');
        }
        if ($this->utilizatori->gasesteDupaEmail($email) !== null) {
            $this->flash('eroare', 'Există deja un cont cu această adresă.');
            return $this->redirect($response, '/utilizatori');
        }
        $id = $this->utilizatori->creeaza($email, $nume);
        $trimis = $this->trimiteLink($id, PasswordTokenRepository::TTL_ZILE * 24 * 60);
        $this->flash(
            $trimis ? 'ok' : 'eroare',
            $trimis
                ? "Cont creat. Linkul de setare a parolei a fost trimis la $email."
                : 'Cont creat, dar emailul nu a putut fi trimis. Folosește „Trimite link”.'
        );
        return $this->redirect($response, '/utilizatori');
    }

    public function trimiteLinkActiune(Request $request, Response $response, array $args): Response
    {
        if ($this->csrfOk($request) && $this->utilizatori->gaseste((int) $args['id']) !== null) {
            $ok = $this->trimiteLink((int) $args['id'], PasswordTokenRepository::TTL_ZILE * 24 * 60);
            $this->flash($ok ? 'ok' : 'eroare', $ok ? 'Link trimis.' : 'Emailul nu a putut fi trimis.');
        }
        return $this->redirect($response, '/utilizatori');
    }

    public function sterge(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->csrfOk($request)) {
            return $this->redirect($response, '/utilizatori');
        }
        // Întâi „nu există": `sterge()` întoarce false și pentru un id inexistent,
        // iar mesajul „ultimul cont" ar fi atunci pur și simplu fals.
        if ($this->utilizatori->gaseste($id) === null) {
            $this->flash('eroare', 'Contul nu există.');
        } elseif ($id === (int) ($this->auth->user()['id'] ?? 0)) {
            $this->flash('eroare', 'Nu îți poți șterge propriul cont.');
        } elseif (!$this->utilizatori->sterge($id)) {
            $this->flash('eroare', 'Nu poți șterge ultimul cont.');
        } else {
            $this->flash('ok', 'Cont șters.');
        }
        return $this->redirect($response, '/utilizatori');
    }

    /**
     * Rută publică: formularul „parolă uitată".
     *
     * Răspunsul e identic pentru o adresă cunoscută și una necunoscută —
     * altfel formularul ar deveni un detector de conturi.
     */
    public function parolaUitata(Request $request, Response $response): Response
    {
        $valabilitate = self::valabilitate(PasswordTokenRepository::TTL_RESETARE_MINUTE);
        if ($request->getMethod() === 'GET') {
            return $this->render($response, 'admin/parola_uitata.twig', ['trimis' => false, 'utilizator' => null, 'valabilitate' => $valabilitate]);
        }
        $start = (float) hrtime(true);
        $ip = ip_hash($request->getServerParams()['REMOTE_ADDR'] ?? null);
        if ($this->csrfOk($request) && !$this->throttle->tooMany($ip)) {
            $this->throttle->record($ip);
            $email = strtolower(trim((string) (((array) $request->getParsedBody())['email'] ?? '')));
            $u = $this->utilizatori->gasesteDupaEmail($email);
            if ($u !== null) {
                $this->trimiteLink((int) $u['id'], PasswordTokenRepository::TTL_RESETARE_MINUTE);
            }
        }
        // În TOATE ramurile POST, inclusiv CSRF respins și throttle.
        $this->asteaptaPanaLaPrag($start);
        return $this->render($response, 'admin/parola_uitata.twig', ['trimis' => true, 'utilizator' => null, 'valabilitate' => $valabilitate]);
    }

    /** Rută publică: setarea parolei din link. */
    public function parola(Request $request, Response $response, array $args): Response
    {
        $token = (string) $args['token'];
        // Doar verificăm, nu consumăm: un prefetch al linkului din clientul de
        // email ar arde invitația înainte ca omul s-o vadă.
        if (!$this->tokens->esteValabil($token)) {
            $this->flash('eroare', 'Linkul a expirat sau a fost folosit. Cere unul nou.');
            return $this->redirect($response, '/parola-uitata');
        }
        if ($request->getMethod() === 'GET') {
            return $this->render($response, 'admin/parola.twig', ['eroare' => null, 'token' => $token, 'utilizator' => null]);
        }
        $in = (array) $request->getParsedBody();
        $p1 = (string) ($in['parola'] ?? '');
        $p2 = (string) ($in['parola2'] ?? '');
        if (!$this->csrfOk($request)) {
            return $this->render($response, 'admin/parola.twig', ['eroare' => 'Sesiunea a expirat.', 'token' => $token, 'utilizator' => null]);
        }
        if (mb_strlen($p1) < 10 || $p1 !== $p2) {
            return $this->render($response, 'admin/parola.twig', ['eroare' => 'Parola trebuie să aibă cel puțin 10 caractere și să coincidă.', 'token' => $token, 'utilizator' => null]);
        }
        $uid = $this->tokens->consume($token);
        if ($uid === null) {
            return $this->redirect($response, '/parola-uitata');
        }
        $this->utilizatori->seteazaParola($uid, $p1);
        return $this->redirect($response, '/login?ok=parola');
    }

    /**
     * Emite un token și trimite linkul. Invitația ANTERIOARĂ moare abia după
     * ce emailul a plecat cu adevărat: dacă SMTP-ul e căzut, omul rămâne cu
     * linkul vechi, în loc să rămână fără niciunul.
     *
     * `$ttlMinute` vine de la flux (invitație vs. „parolă uitată") și e și
     * durata scrisă în DB, și textul din email — o singură sursă de adevăr.
     */
    private function trimiteLink(int $uid, int $ttlMinute): bool
    {
        $u = $this->utilizatori->gaseste($uid);
        if ($u === null) {
            return false; // niciun token pentru un cont care nu există
        }
        $raw = $this->tokens->issue($uid, false, $ttlMinute);
        if ($raw === null) {
            return false;
        }
        $link = $this->settings['app']['url'] . $this->settings['app']['base_path'] . $this->adminPath() . '/parola/' . $raw;
        $html = $this->twig->fetch('mail/parola.twig', [
            'nume' => $u['nume'],
            'link' => $link,
            'valabilitate' => self::valabilitate($ttlMinute),
        ]);
        if (!$this->mailer->send((string) $u['email'], 'Setarea parolei — administrare FLAG Prahova', $html)) {
            $this->tokens->invalideazaToken($raw);
            return false;
        }
        $this->tokens->pastreazaDoar($uid, $raw);
        return true;
    }

    /**
     * Durata unui link, în românește: „30 de minute”, „7 zile”. Aceeași valoare
     * ajunge și în email, și în textul paginii — ca să nu promitem altceva
     * decât scrie în `expira_la`.
     */
    private static function valabilitate(int $ttlMinute): string
    {
        if ($ttlMinute >= 24 * 60) {
            $zile = intdiv($ttlMinute, 24 * 60);
            return $zile === 1 ? 'o zi' : "$zile zile";
        }
        if ($ttlMinute >= 60) {
            $ore = intdiv($ttlMinute, 60);
            return $ore === 1 ? 'o oră' : ($ore < 20 ? "$ore ore" : "$ore de ore");
        }
        return $ttlMinute === 1 ? 'un minut' : ($ttlMinute < 20 ? "$ttlMinute minute" : "$ttlMinute de minute");
    }

    /**
     * Ține durata unui POST pe „parolă uitată" ~constantă, indiferent de ramură.
     *
     * Textul răspunsului e deja identic pentru o adresă cunoscută și una
     * necunoscută, dar trimiterea SMTP e sincronă și se face doar pentru
     * adresele care există — fără egalizare, cronometrul ar spune ce textul
     * ascunde.
     */
    private function asteaptaPanaLaPrag(float $startNs): void
    {
        $scursMs = (hrtime(true) - $startNs) / 1_000_000;
        $ramasMs = self::PRAG_MS - $scursMs;
        if ($ramasMs > 0) {
            usleep((int) round($ramasMs * 1000));
        }
    }
}
