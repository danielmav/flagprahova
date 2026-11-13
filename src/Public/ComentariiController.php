<?php
declare(strict_types=1);

namespace App\Public;

use App\Form\TimeToken;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

/**
 * Întrebări și comentarii la un ghid (dosar cu `meniu.comentarii = 1`).
 * Comentariul intră ca „nou” și apare pe site doar după aprobarea din admin.
 * Protecții ca la formularul de contact: CSRF, honeypot, token de timp, 5/oră/IP.
 */
final class ComentariiController
{
    private Context $ctx;

    public function __construct(private Twig $twig, private array $container)
    {
        $this->ctx = $container['context'];
    }

    /** Variabilele de șablon pentru secțiunea de comentarii a unei intrări (goale dacă nu are comentarii). */
    public static function variabile(array $container, array $rand, array $query = []): array
    {
        if (!(int) ($rand['comentarii'] ?? 0)) {
            return ['comentarii' => null];
        }
        return [
            'comentarii' => $container['comentarii']->publicate((int) $rand['id']),
            'form' => ['erori' => [], 'valori' => []],
            'time_token' => TimeToken::mint(),
            'trimis' => isset($query['trimis']),
        ];
    }

    public function trimite(Request $request, Response $response, array $args): Response
    {
        $s = $this->ctx->sectiune($args['perioada']) ?? throw new HttpNotFoundException($request);
        $arbore = $this->ctx->arbore($s);
        $g = $this->ctx->gaseste($arbore, $args['slug']) ?? throw new HttpNotFoundException($request);
        $nod = $g['nod'];
        $rand = $this->container['meniu']->gaseste((int) $nod['id']);
        if ($rand === null || $nod['tip'] !== 'dosar' || !(int) $rand['comentarii']) {
            throw new HttpNotFoundException($request);
        }

        $in = (array) $request->getParsedBody();
        $valori = [
            'nume' => trim((string) ($in['nume'] ?? '')),
            'email' => trim((string) ($in['email'] ?? '')),
            'mesaj' => trim((string) ($in['mesaj'] ?? '')),
            'acord' => ($in['acord'] ?? '') === '1',
        ];
        $url = $this->ctx->base() . '/' . $s['slug'] . '/' . $nod['slug'];
        $succes = $response->withHeader('Location', $url . '?trimis=1#intrebari')->withStatus(302);

        $erori = [];
        $csrf = (string) ($in['_csrf'] ?? '');
        if ($csrf === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $csrf)) {
            $erori['_'] = 'Sesiunea a expirat. Reîncarcă pagina și trimite din nou.';
        }
        // Bot: răspuns identic cu succesul, fără salvare.
        if ($erori === [] && (((string) ($in['website'] ?? '')) !== '' || !TimeToken::isValidAndAged((string) ($in['_t'] ?? '')))) {
            return $succes;
        }
        if (mb_strlen($valori['nume']) < 2 || mb_strlen($valori['nume']) > 120) { $erori['nume'] = 'Scrie-ți numele (2–120 caractere).'; }
        if (!filter_var($valori['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($valori['email']) > 190) { $erori['email'] = 'Adresa de email nu pare validă.'; }
        if (mb_strlen($valori['mesaj']) < 10 || mb_strlen($valori['mesaj']) > 5000) { $erori['mesaj'] = 'Mesajul trebuie să aibă între 10 și 5000 de caractere.'; }
        if (!$valori['acord']) { $erori['acord'] = 'Bifează acordul ca să putem publica mesajul.'; }
        if ($erori !== []) {
            $vars = $this->ctx->variabile($s, '/' . $s['slug'] . '/' . $nod['slug'], [
                'nod' => $nod, 'rand' => $rand, 'stramosi' => $g['stramosi'], 'ramura' => $g['stramosi'][0] ?? $nod,
                'activ' => array_map(fn($n) => (int) $n['id'], [...$g['stramosi'], $nod]),
            ] + self::variabile($this->container, $rand));
            $vars['arbore'] = $arbore;
            $vars['form'] = ['erori' => $erori, 'valori' => $valori];
            return $this->twig->render($response, 'sectiune/dosar.twig', $vars);
        }

        /** @var \App\Comentarii\Repository $repo */
        $repo = $this->container['comentarii'];
        // Throttle: 5 pe oră de pe același IP, apoi „succes” tăcut (ca la contact).
        $ipHash = ip_hash($request->getServerParams()['REMOTE_ADDR'] ?? null);
        if ($ipHash !== null && $repo->recenteDeLa($ipHash) >= 5) {
            return $succes;
        }
        $id = $repo->adauga((int) $rand['id'], $valori['nume'], $valori['email'], $valori['mesaj'], $ipHash);

        $setari = $this->container['settings'];
        $link = rtrim((string) $setari['app']['url'], '/') . $setari['admin']['path'] . '/comentarii/' . $id;
        $catre = $this->container['setari']->get('contact_email_destinatar') ?: $this->container['mailer']->adminAddress();
        $corp = '<p>Un mesaj nou așteaptă aprobarea la <strong>' . e($rand['titlu']) . '</strong>.</p>'
            . '<p><strong>Nume:</strong> ' . e($valori['nume']) . '<br><strong>Email:</strong> ' . e($valori['email']) . '</p>'
            . '<p>' . nl2br(e($valori['mesaj'])) . '</p>'
            . '<p><a href="' . e($link) . '">Aprobă sau răspunde din administrare</a></p>';
        if ($this->container['mailer']->send($catre, 'Întrebare nouă — ' . $rand['titlu'], $corp, $valori['email'])) {
            $repo->marcheazaEmailTrimis($id);
        }
        return $succes;
    }
}
