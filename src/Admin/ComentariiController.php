<?php
declare(strict_types=1);

namespace App\Admin;

use App\Comentarii\Repository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/** Moderarea întrebărilor și comentariilor la ghiduri: aprobare, răspuns, ștergere. */
final class ComentariiController
{
    use Helpers;

    private Auth $auth;
    private array $settings;
    private Repository $repo;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth     = $container['auth'];
        $this->settings = $container['settings'];
        $this->repo     = $container['comentarii'];
    }

    public function index(Request $request, Response $response): Response
    {
        $stare = (string) ($request->getQueryParams()['stare'] ?? '');
        $stare = in_array($stare, Repository::STARI, true) ? $stare : null;
        return $this->render($response, 'admin/comentarii.twig', ['comentarii' => $this->repo->lista($stare), 'stare' => $stare]);
    }

    public function editeaza(Request $request, Response $response, array $args): Response
    {
        $c = $this->repo->gaseste((int) $args['id']);
        if ($c === null) {
            $this->flash('eroare', 'Mesajul nu există.');
            return $this->redirect($response, '/comentarii');
        }
        return $this->render($response, 'admin/comentariu.twig', ['c' => $c, 'eroare' => null]);
    }

    public function salveaza(Request $request, Response $response, array $args): Response
    {
        $c = $this->repo->gaseste((int) $args['id']);
        if ($c === null) {
            $this->flash('eroare', 'Mesajul nu există.');
            return $this->redirect($response, '/comentarii');
        }
        $in = (array) $request->getParsedBody();
        $date = [
            'stare'   => in_array($in['stare'] ?? '', Repository::STARI, true) ? (string) $in['stare'] : $c['stare'],
            'nume'    => mb_substr(trim((string) ($in['nume'] ?? '')), 0, 120),
            'mesaj'   => trim((string) ($in['mesaj'] ?? '')),
            'raspuns' => trim((string) ($in['raspuns'] ?? '')),
        ];
        $eroare = match (true) {
            !$this->csrfOk($request) => 'Sesiunea a expirat. Trimite din nou.',
            $date['nume'] === ''     => 'Numele nu poate fi gol.',
            $date['mesaj'] === ''    => 'Mesajul nu poate fi gol.',
            default                  => null,
        };
        if ($eroare !== null) {
            return $this->render($response, 'admin/comentariu.twig', ['c' => array_merge($c, $date), 'eroare' => $eroare]);
        }
        $this->repo->actualizeaza((int) $c['id'], $date['stare'], $date['nume'], $date['mesaj'], $date['raspuns']);
        $this->flash('ok', $date['stare'] === 'publicat' ? 'Salvat. Mesajul e publicat pe site.' : 'Salvat.');
        return $this->redirect($response, '/comentarii');
    }

    /** Aprobare rapidă din listă. */
    public function aproba(Request $request, Response $response, array $args): Response
    {
        $c = $this->repo->gaseste((int) $args['id']);
        if ($c === null || !$this->csrfOk($request)) {
            $this->flash('eroare', $c === null ? 'Mesajul nu există.' : 'Sesiunea a expirat. Reîncarcă pagina.');
        } else {
            $this->repo->actualizeaza((int) $c['id'], 'publicat', (string) $c['nume'], (string) $c['mesaj'], (string) ($c['raspuns'] ?? ''));
            $this->flash('ok', 'Mesajul e publicat pe site.');
        }
        return $this->redirect($response, '/comentarii');
    }

    public function sterge(Request $request, Response $response, array $args): Response
    {
        $c = $this->repo->gaseste((int) $args['id']);
        if ($c === null || !$this->csrfOk($request)) {
            $this->flash('eroare', $c === null ? 'Mesajul nu există.' : 'Sesiunea a expirat. Reîncarcă pagina.');
        } else {
            $this->repo->sterge((int) $c['id']);
            $this->flash('ok', 'Mesaj șters.');
        }
        return $this->redirect($response, '/comentarii');
    }
}
