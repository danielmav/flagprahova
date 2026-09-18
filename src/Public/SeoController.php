<?php
declare(strict_types=1);

namespace App\Public;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/** sitemap.xml și robots.txt, generate din arborele public. */
final class SeoController
{
    private Context $ctx;

    public function __construct(private Twig $twig, private array $container)
    {
        $this->ctx = $container['context'];
    }

    public function sitemap(Request $request, Response $response): Response
    {
        // `href` include `base_path`, iar `APP_URL` îl include și el (staging în
        // subfolder). Regula stă într-un singur loc: `Context::urlPublic()`.
        $loc = fn(string $cale): string => $this->ctx->urlPublic($cale);

        $intrari = [['loc' => $loc('/'), 'lastmod' => null]];
        $aduna = function (array $noduri) use (&$aduna, &$intrari, $loc): void {
            foreach ($noduri as $n) {
                if (in_array($n['tip'], ['pagina', 'dosar', 'galerie'], true)) {
                    $intrari[] = [
                        'loc'     => $loc($n['href']),
                        'lastmod' => substr((string) $n['modificat_la'], 0, 10) ?: null,
                    ];
                }
                $aduna($n['copii']);
            }
        };
        foreach ($this->ctx->sectiuni() as $s) {
            $intrari[] = ['loc' => $loc('/' . $s['slug'] . '/'), 'lastmod' => null];
            $aduna($this->ctx->arbore($s));
        }

        $response = $this->twig->render($response, 'sitemap.twig', ['intrari' => $intrari]);
        return $response->withHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    public function robots(Request $request, Response $response): Response
    {
        // Căile din `Disallow` sunt relative la host, deci poartă `base_path`;
        // calea adminului vine din setări (`ADMIN_PATH`), nu e hardcodată.
        $baza  = $this->ctx->base();
        $admin = $baza . (string) $this->container['settings']['admin']['path'];
        $mini  = $baza . (string) $this->container['settings']['upload']['url'] . '/mini/';
        $harta = $this->ctx->urlPublic('/sitemap.xml');
        $response->getBody()->write("User-agent: *\nDisallow: {$admin}\nDisallow: {$mini}\nSitemap: {$harta}\n");
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
