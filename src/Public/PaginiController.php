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

    /** O intrare de meniu: pagină, dosar, galerie, sau redirect (document/link). */
    public function pagina(Request $request, Response $response, array $args): Response
    {
        $s = $this->ctx->sectiune($args['perioada']) ?? throw new HttpNotFoundException($request);
        $arbore = $this->ctx->arbore($s);
        $g = $this->ctx->gaseste($arbore, $args['slug']) ?? throw new HttpNotFoundException($request);
        $nod = $g['nod'];
        if ($nod['tip'] === 'document' || $nod['tip'] === 'link') {
            if ($nod['href'] === '') { throw new HttpNotFoundException($request); }
            return $response->withHeader('Location', $nod['href'])->withStatus(302);
        }
        $rand = $this->container['meniu']->gaseste((int) $nod['id']);
        $activ = array_map(fn($n) => (int) $n['id'], [...$g['stramosi'], $nod]);
        $vars = $this->ctx->variabile($s, '/' . $s['slug'] . '/' . $nod['slug'], [
            'nod' => $nod, 'rand' => $rand, 'stramosi' => $g['stramosi'], 'activ' => $activ,
            // Ramura de nivel 1 a nodului curent: rădăcina lui, sau el însuși.
            'ramura' => $g['stramosi'][0] ?? $nod,
        ]);
        $vars['arbore'] = $arbore;
        /** @var \App\Meniu\GalerieRepository $galerii */
        $galerii = $this->container['galerie'];
        if ($nod['tip'] === 'galerie') {
            $vars['imagini'] = $galerii->imagini((int) $nod['id']);
            if ($vars['imagini']) {
                $vars['og_image'] = $this->container['settings']['upload']['url'] . '/mini/1600/'
                    . preg_replace('/\.[^.\/]+$/', '.webp', $vars['imagini'][0]['cale']);
            }
            return $this->twig->render($response, 'sectiune/galerie.twig', $vars);
        }
        if ($nod['tip'] === 'dosar') {
            return $this->twig->render($response, 'sectiune/dosar.twig', $vars);
        }
        // Galeriile-copil ale unei pagini se randează sub conținut, în acordeon.
        $vars['galerii'] = [];
        foreach ($nod['copii'] as $c) {
            if ($c['tip'] === 'galerie') { $c['imagini'] = $galerii->imagini((int) $c['id']); $vars['galerii'][] = $c; }
        }
        $sablon = ($rand['sablon'] ?? 'standard') === 'contact' ? 'sectiune/contact.twig' : 'sectiune/pagina.twig';
        return $this->twig->render($response, $sablon, $vars);
    }
}
