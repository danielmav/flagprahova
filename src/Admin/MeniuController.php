<?php
declare(strict_types=1);

namespace App\Admin;

use App\Fisiere\Repository as Fisiere;
use App\Meniu\GalerieRepository;
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
    private GalerieRepository $galerie;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth     = $container['auth'];
        $this->settings = $container['settings'];
        $this->meniu    = $container['meniu'];
        $this->fisiere  = $container['fisiere'];
        $this->galerie  = $container['galerie'];
    }

    private function sectiuneCurenta(Request $request, ?int $id = null): array
    {
        $sectiuni = $this->meniu->sectiuni();
        if ($sectiuni === []) {
            throw new \RuntimeException('Nu există secțiuni. Rulează database/seed.php.');
        }
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
            'galerie'  => isset($intrare['id']) ? $this->galerie->imagini((int) $intrare['id']) : [],
            'eroare'   => $eroare,
            'tipuri'   => Meniu::TIPURI,
        ]);
    }

    /** Lista plată „— — Titlu” pentru <select>, excluzând intrarea editată și descendenții ei. */
    private function optiuniParinte(int $sectiuneId, ?int $exclude): array
    {
        $out = [];
        $parcurge = function (array $noduri, int $nivel) use (&$parcurge, &$out, $exclude): void {
            foreach ($noduri as $n) {
                // `continue` sare peste tot subarborele, deci descendenții intrării
                // editate sunt excluși odată cu ea.
                if ($exclude !== null && (int) $n['id'] === $exclude) {
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
        // La editare, secțiunea e cea a intrării existente — câmpul din POST se ignoră.
        if ($id !== null) {
            $curent = $this->meniu->gaseste($id);
            if ($curent === null) {
                $this->flash('eroare', 'Intrarea nu există.');
                return $this->redirect($response, '/meniu');
            }
            $sec = $this->sectiuneCurenta($request, (int) $curent['sectiune_id']);
        } else {
            $sec = $this->sectiuneCurenta($request, (int) ($in['sectiune_id'] ?? 0));
        }
        $date = [
            'id' => $id, 'sectiune_id' => (int) $sec['id'],
            'parent_id' => ($in['parent_id'] ?? '') === '' ? null : (int) $in['parent_id'],
            // Tăiem la lungimile coloanelor: un câmp mai lung ar face INSERT-ul să
            // arunce și cererea s-ar termina în 500, în loc de o salvare curată.
            'titlu' => mb_substr(trim((string) ($in['titlu'] ?? '')), 0, 255),
            'slug'  => mb_substr(trim((string) ($in['slug'] ?? '')), 0, 160),
            'tip'   => in_array($in['tip'] ?? '', Meniu::TIPURI, true) ? $in['tip'] : 'document',
            'url'   => mb_substr(trim((string) ($in['url'] ?? '')), 0, 500),
            'fisier_id' => (int) ($in['fisier_id'] ?? 0) ?: null,
            'sablon' => in_array($in['sablon'] ?? '', Meniu::SABLOANE, true) ? $in['sablon'] : 'standard',
            'vizibil' => (int) (($in['vizibil'] ?? '0') === '1'),
            'continut_html' => (string) ($in['continut_html'] ?? ''),
        ];
        // Sanitizăm ÎNAINTE de orice `formular()`: la re-randarea cu eroare (CSRF
        // expirat, titlu gol, link invalid, document fără fișier) conținutul se
        // întoarce în editor prin `|raw`, deci HTML-ul brut din POST ar ajunge
        // executabil în pagina de admin.
        if ($date['tip'] === 'pagina') {
            $date['continut_html'] = \App\Support\Html::curata($date['continut_html']);
        }
        // Părintele trebuie să existe și să fie din aceeași secțiune; altfel intrarea
        // ar deveni o rădăcină „fantomă”, invizibilă în arborele oricărei secțiuni.
        if ($date['parent_id'] !== null) {
            $p = $this->meniu->gaseste($date['parent_id']);
            if ($p === null || (int) $p['sectiune_id'] !== (int) $sec['id']) {
                $date['parent_id'] = null;
            }
        }
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
        $this->dupaSalvare($id, (string) $date['tip'], $request); // Task 8: galerie
        return $this->redirect($response, '/meniu?sectiune=' . $sec['slug']);
    }

    /**
     * Scrie imaginile galeriei trimise de formular (ordinea câmpurilor = ordinea din galerie).
     * `$tip` e tipul deja normalizat, nu cel brut din POST. Dacă intrarea nu (mai) e
     * galerie, setăm o listă goală: altfel imaginile ar rămâne orfane după schimbarea tipului.
     */
    private function dupaSalvare(int $id, string $tip, Request $request): void
    {
        if ($tip !== 'galerie') {
            $this->galerie->seteaza($id, []);
            return;
        }
        $in  = (array) $request->getParsedBody();
        $ids = (array) ($in['galerie_fisier_id'] ?? []);
        $leg = (array) ($in['galerie_legenda'] ?? []);
        $set = [];
        foreach (array_values($ids) as $i => $fid) {
            $set[] = ['fisier_id' => (int) $fid, 'legenda' => (string) ($leg[$i] ?? '')];
        }
        $this->galerie->seteaza($id, $set);
    }

    public function sterge(Request $request, Response $response, array $args): Response
    {
        $intrare = $this->meniu->gaseste((int) $args['id']);
        if ($intrare === null) {
            $this->flash('eroare', 'Intrarea nu există.');
        } elseif (!$this->csrfOk($request)) {
            $this->flash('eroare', 'Sesiunea a expirat. Reîncarcă pagina.');
        } else {
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
