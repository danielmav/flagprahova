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
        $url = $this->url();
        $baza = $this->ctx->base();
        // `href` include `base_path`; `app.url` poate deja conține baza (staging
        // în subfolder), deci scoatem baza din href ca să nu se dubleze.
        $loc = static fn(string $href): string => $url . ($baza !== '' && str_starts_with($href, $baza)
            ? substr($href, strlen($baza))
            : $href);

        $intrari = [['loc' => $url . '/', 'lastmod' => null]];
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
            $intrari[] = ['loc' => $url . '/' . $s['slug'] . '/', 'lastmod' => null];
            $aduna($this->ctx->arbore($s));
        }

        $response = $this->twig->render($response, 'sitemap.twig', ['intrari' => $intrari]);
        return $response->withHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    public function robots(Request $request, Response $response): Response
    {
        $url = $this->url();
        $response->getBody()->write("User-agent: *\nDisallow: /admin\nDisallow: /fisiere/mini/\nSitemap: {$url}/sitemap.xml\n");
        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    private function url(): string
    {
        return rtrim((string) $this->container['settings']['app']['url'], '/');
    }
}
