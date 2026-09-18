<?php
declare(strict_types=1);

namespace App\Public;

use App\Form\TimeToken;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

final class ContactController
{
    private Context $ctx;

    public function __construct(private Twig $twig, private array $container)
    {
        $this->ctx = $container['context'];
    }

    public function trimite(Request $request, Response $response, array $args): Response
    {
        $s = $this->ctx->sectiune($args['perioada']) ?? throw new HttpNotFoundException($request);
        $arbore = $this->ctx->arbore($s);
        $g = $this->ctx->gaseste($arbore, $args['slug']);
        if ($g === null || $g['nod']['tip'] !== 'pagina' || ($g['nod']['sablon'] ?? '') !== 'contact') {
            throw new HttpNotFoundException($request);
        }
        $in = (array) $request->getParsedBody();
        $valori = ['nume' => trim((string) ($in['nume'] ?? '')), 'email' => trim((string) ($in['email'] ?? '')), 'mesaj' => trim((string) ($in['mesaj'] ?? ''))];
        $url = $this->ctx->base() . '/' . $s['slug'] . '/' . $g['nod']['slug'];
        $succes = $response->withHeader('Location', $url . '?trimis=1#formular')->withStatus(302);

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
        if ($erori !== []) {
            return $this->twig->render($response, 'sectiune/contact.twig', $this->variabileContact($s, $arbore, $g, $erori, $valori, false));
        }

        $pdo = $this->container['db']->pdo();
        $pdo->prepare('INSERT INTO mesaje_contact (sectiune_id, nume, email, mesaj, ip_hash) VALUES (:s, :n, :e, :m, :ip)')
            ->execute(['s' => $s['id'], 'n' => $valori['nume'], 'e' => $valori['email'], 'm' => $valori['mesaj'], 'ip' => ip_hash($request->getServerParams()['REMOTE_ADDR'] ?? null)]);
        $id = (int) $pdo->lastInsertId();

        $catre = $this->container['setari']->get('contact_email_destinatar') ?: $this->container['mailer']->adminAddress();
        $corp = '<p><strong>Nume:</strong> ' . e($valori['nume']) . '<br><strong>Email:</strong> ' . e($valori['email']) . '<br><strong>Secțiunea:</strong> ' . e($s['titlu']) . '</p><p>' . nl2br(e($valori['mesaj'])) . '</p>';
        if ($this->container['mailer']->send($catre, 'Mesaj din formularul de contact — ' . $s['titlu'], $corp, $valori['email'])) {
            $pdo->prepare('UPDATE mesaje_contact SET email_trimis = 1 WHERE id = :id')->execute(['id' => $id]);
        }
        return $succes;
    }

    /** Variabilele pentru randarea `sectiune/contact.twig`, aceleași chei ca în `PaginiController::pagina()`. */
    private function variabileContact(array $s, array $arbore, array $g, array $erori, array $valori, bool $trimis): array
    {
        $nod = $g['nod'];
        $rand = $this->container['meniu']->gaseste((int) $nod['id']);
        $vars = $this->ctx->variabile($s, '/' . $s['slug'] . '/' . $nod['slug'], [
            'nod' => $nod, 'rand' => $rand, 'stramosi' => $g['stramosi'], 'ramura' => $g['stramosi'][0] ?? $nod,
            'activ' => array_map(fn($n) => (int) $n['id'], [...$g['stramosi'], $nod]),
            'galerii' => [], 'form' => ['erori' => $erori, 'valori' => $valori], 'time_token' => TimeToken::mint(), 'trimis' => $trimis,
        ]);
        $vars['arbore'] = $arbore;
        return $vars;
    }
}
