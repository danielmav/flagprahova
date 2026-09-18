<?php
declare(strict_types=1);

namespace App\Public;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Views\Twig;

final class PaginiController
{
    private Context $ctx;

    public function __construct(private Twig $twig, private array $container)
    {
        $this->ctx = $container['context'];
    }

    public function landing(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'landing.twig', $this->ctx->variabile(null, '/'));
    }

    /** Redirect 301 de la /{perioada} la /{perioada}/ (o singură formă canonică). */
    public function slash(Request $request, Response $response, array $args): Response
    {
        return $response
            ->withHeader('Location', $this->ctx->base() . '/' . $args['perioada'] . '/')
            ->withStatus(301);
    }

    public function acasa(Request $request, Response $response, array $args): Response
    {
        $s = $this->ctx->sectiune($args['perioada']) ?? throw new HttpNotFoundException($request);
        $vars = $this->ctx->variabile($s, '/' . $s['slug'] . '/');
        $noutati = []; $contact = '';
        foreach ($vars['arbore'] as $n) {
            if ($n['slug'] === 'noutati') { $noutati = array_slice($n['copii'], 0, 3); }
            if ($n['slug'] === 'contact') { $contact = $n['href']; }
        }
        $vars['noutati'] = $noutati;
        $vars['nivel1'] = $vars['arbore'];
        $vars['contact_href'] = $contact;
        return $this->twig->render($response, 'sectiune/acasa.twig', $vars);
    }
}
