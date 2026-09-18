<?php
declare(strict_types=1);

namespace App\Admin;

use App\Fisiere\Repository as Fisiere;
use App\Meniu\Repository as Meniu;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class MeniuController
{
    use Helpers;

    private Auth $auth;
    private array $settings;
    private Meniu $meniu;
    private Fisiere $fisiere;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth     = $container['auth'];
        $this->settings = $container['settings'];
        $this->meniu    = $container['meniu'];
        $this->fisiere  = $container['fisiere'];
    }

    private function sectiuneCurenta(Request $request, ?int $id = null): array
    {
        $sectiuni = $this->meniu->sectiuni();
        $slug = (string) ($request->getQueryParams()['sectiune'] ?? '');
        foreach ($sectiuni as $s) {
            if (($id !== null && (int) $s['id'] === $id) || ($id === null && $s['slug'] === $slug)) {
                return $s;
            }
        }
        return $sectiuni[0];
    }

    public function index(Request $request, Response $response): Response
    {
        $sec = $this->sectiuneCurenta($request);
        return $this->render($response, 'admin/meniu.twig', [
            'sectiuni' => $this->meniu->sectiuni(),
            'sectiune' => $sec,
            'arbore'   => $this->meniu->arbore((int) $sec['id']),
        ]);
    }

    public function nou(Request $request, Response $response): Response
    {
        $sec = $this->sectiuneCurenta($request);
        $parent = (int) ($request->getQueryParams()['parent'] ?? 0) ?: null;
        return $this->formular($response, [
            'id' => null, 'sectiune_id' => (int) $sec['id'], 'parent_id' => $parent, 'titlu' => '', 'slug' => '',
            'tip' => 'document', 'url' => '', 'fisier_id' => null, 'sablon' => 'standard', 'vizibil' => 1, 'continut_html' => '',
        ], $sec);
    }

    public function editeaza(Request $request, Response $response, array $args): Response
    {
        $intrare = $this->meniu->gaseste((int) $args['id']);
        if ($intrare === null) {
            $this->flash('eroare', 'Intrarea nu există.');
            return $this->redirect($response, '/meniu');
        }
        return $this->formular($response, $intrare, $this->sectiuneCurenta($request, (int) $intrare['sectiune_id']));
    }

    private function formular(Response $response, array $intrare, array $sec, ?string $eroare = null): Response
    {
        $fisier = !empty($intrare['fisier_id']) ? $this->fisiere->gaseste((int) $intrare['fisier_id']) : null;
        return $this->render($response, 'admin/intrare.twig', [
            'intrare'  => $intrare,
            'sectiune' => $sec,
            'parinti'  => $this->optiuniParinte((int) $sec['id'], isset($intrare['id']) ? (int) $intrare['id'] : null),
            'fisier'   => $fisier,
            'galerie'  => [],
            'eroare'   => $eroare,
            'tipuri'   => Meniu::TIPURI,
        ]);
    }

    /** Lista plată „— — Titlu” pentru <select>, excluzând intrarea editată și descendenții ei. */
    private function optiuniParinte(int $sectiuneId, ?int $exclude): array
    {
        $out = [];
        $meniu = $this->meniu;
        $parcurge = function (array $noduri, int $nivel) use (&$parcurge, &$out, $exclude, $meniu): void {
            foreach ($noduri as $n) {
                // `continue` sare peste tot subarborele, deci descendenții sunt excluși
                // și structural; `esteDescendent` rămâne plasa de siguranță dacă arborele
                // ar fi construit altfel (de ex. un părinte lipsă care rupe lanțul).
                if ($exclude !== null && ((int) $n['id'] === $exclude || $meniu->esteDescendent($exclude, (int) $n['id']))) {
                    continue;
                }
                $out[] = ['id' => (int) $n['id'], 'eticheta' => str_repeat('— ', $nivel) . $n['titlu']];
                $parcurge($n['copii'], $nivel + 1);
            }
        };
        $parcurge($this->meniu->arbore($sectiuneId), 0);
        return $out;
    }

    public function salveaza(Request $request, Response $response): Response
    {
        $in  = (array) $request->getParsedBody();
        $id  = (int) ($in['id'] ?? 0) ?: null;
        $sid = (int) ($in['sectiune_id'] ?? 0);
        $sec = $this->sectiuneCurenta($request, $sid);
        $date = [
            'id' => $id, 'sectiune_id' => (int) $sec['id'],
            'parent_id' => ($in['parent_id'] ?? '') === '' ? null : (int) $in['parent_id'],
            'titlu' => trim((string) ($in['titlu'] ?? '')),
            'slug'  => trim((string) ($in['slug'] ?? '')),
            'tip'   => in_array($in['tip'] ?? '', Meniu::TIPURI, true) ? $in['tip'] : 'document',
            'url'   => trim((string) ($in['url'] ?? '')),
            'fisier_id' => (int) ($in['fisier_id'] ?? 0) ?: null,
            'sablon' => in_array($in['sablon'] ?? '', Meniu::SABLOANE, true) ? $in['sablon'] : 'standard',
            'vizibil' => (int) (($in['vizibil'] ?? '0') === '1'),
            'continut_html' => (string) ($in['continut_html'] ?? ''),
        ];
        if (!$this->csrfOk($request)) {
            return $this->formular($response, $date, $sec, 'Sesiunea a expirat. Trimite din nou.');
        }
        if ($date['titlu'] === '') {
            return $this->formular($response, $date, $sec, 'Titlul este obligatoriu.');
        }
        if ($date['tip'] === 'link' && !preg_match('#^https?://#i', $date['url'])) {
            return $this->formular($response, $date, $sec, 'Linkul trebuie să înceapă cu http:// sau https://.');
        }
        if ($date['tip'] === 'document' && ($date['fisier_id'] === null || $this->fisiere->gaseste($date['fisier_id']) === null)) {
            return $this->formular($response, $date, $sec, 'Alege un fișier pentru această intrare.');
        }
        if ($date['tip'] !== 'document') { $date['fisier_id'] = null; }
        if ($date['tip'] !== 'link') { $date['url'] = ''; }
        if ($date['tip'] !== 'pagina') { $date['continut_html'] = ''; $date['sablon'] = 'standard'; }

        if ($id === null) {
            $id = $this->meniu->creeaza($date);
            $this->flash('ok', 'Intrare adăugată.');
        } else {
            $this->meniu->actualizeaza($id, $date);
            $this->flash('ok', 'Intrare salvată.');
        }
        $this->dupaSalvare($id, $request); // Task 8: galerie
        return $this->redirect($response, '/meniu?sectiune=' . $sec['slug']);
    }

    /** Extins în Task 8 (imaginile galeriei). */
    private function dupaSalvare(int $id, Request $request): void {}

    public function sterge(Request $request, Response $response, array $args): Response
    {
        $intrare = $this->meniu->gaseste((int) $args['id']);
        if ($intrare !== null && $this->csrfOk($request)) {
            $n = $this->meniu->sterge((int) $intrare['id']);
            $this->flash('ok', "$n intrare(i) ștearsă(e).");
        }
        $sec = $intrare ? $this->sectiuneCurenta($request, (int) $intrare['sectiune_id']) : $this->sectiuneCurenta($request);
        return $this->redirect($response, '/meniu?sectiune=' . $sec['slug']);
    }

    public function reordoneaza(Request $request, Response $response): Response
    {
        if (!$this->csrfOk($request)) {
            return $this->json($response, ['eroare' => 'CSRF'], 403);
        }
        $in = (array) $request->getParsedBody();
        $sid = (int) ($in['sectiune_id'] ?? 0);
        $arbore = $in['arbore'] ?? null;
        if ($sid <= 0 || !is_array($arbore)) {
            return $this->json($response, ['eroare' => 'Date lipsă.'], 422);
        }
        try {
            $this->meniu->reordoneaza($sid, $arbore);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['eroare' => $e->getMessage()], 422);
        }
        return $this->json($response, ['ok' => true]);
    }
}
