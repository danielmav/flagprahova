<?php
declare(strict_types=1);

namespace App\Public;

use App\Fisiere\Miniatura;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

final class MiniaturaController
{
    public function __construct(private Miniatura $mini) {}

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        // URL-ul cere .webp; sursa are extensia originală — o căutăm între cele acceptate.
        $ceruta = (string) $args['cale'];
        $latime = (int) $args['latime'];
        $baza = preg_replace('/\.webp$/i', '', $ceruta);
        $abs = null;
        // Doar minuscule: `Upload::numeSigur()` scrie extensiile lowercase pe disc.
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $abs = $this->mini->asigura($baza . '.' . $ext, $latime);
            if ($abs !== null) { break; }
        }
        if ($abs === null) { throw new HttpNotFoundException($request); }
        $response->getBody()->write((string) file_get_contents($abs));
        return $response->withHeader('Content-Type', 'image/webp')
            ->withHeader('Content-Length', (string) filesize($abs))
            ->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
    }
}
