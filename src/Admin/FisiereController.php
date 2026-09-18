<?php
declare(strict_types=1);

namespace App\Admin;

use App\Fisiere\Repository as Fisiere;
use App\Fisiere\Upload;
use App\Meniu\Repository as Meniu;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

final class FisiereController
{
    use Helpers;

    private Auth $auth;
    private array $settings;
    private Fisiere $fisiere;
    private Upload $upload;
    private Meniu $meniu;

    public function __construct(private Twig $twig, array $container)
    {
        $this->auth     = $container['auth'];
        $this->settings = $container['settings'];
        $this->fisiere  = $container['fisiere'];
        $this->upload   = $container['upload'];
        $this->meniu    = $container['meniu'];
    }

    public function index(Request $request, Response $response): Response
    {
        $q      = $request->getQueryParams();
        $picker = ($q['picker'] ?? '') === '1';
        $an     = (string) ($q['an'] ?? '');
        $luna   = (string) ($q['luna'] ?? '');
        $cauta  = trim((string) ($q['q'] ?? ''));
        $imagini = ($q['imagini'] ?? '') === '1';
        return $this->render($response, 'admin/fisiere.twig', [
            'fisiere'  => $this->fisiere->lista($an ?: null, $luna ?: null, $cauta, $imagini),
            'ani_luni' => $this->fisiere->aniLuni(),
            'filtru'   => ['an' => $an, 'luna' => $luna, 'q' => $cauta, 'imagini' => $imagini, 'picker' => $picker],
            'picker'   => $picker,
            'utilizator' => $picker ? null : $this->auth->user(), // fără sidebar în picker
        ]);
    }

    public function incarca(Request $request, Response $response): Response
    {
        $inapoi = '/fisiere' . $this->queryInapoi($request);
        if (!$this->csrfOk($request)) {
            $this->flash('eroare', 'Sesiunea a expirat. Reîncarcă pagina.');
            return $this->redirect($response, $inapoi);
        }
        $files = $request->getUploadedFiles()['fisiere'] ?? [];
        if (!is_array($files)) { $files = [$files]; }
        $ok = 0; $erori = [];
        foreach ($files as $f) {
            $r = $this->upload->salveaza($f);
            if ($r['motiv'] === 'gol') { continue; }
            if ($r['motiv'] !== null) { $erori[] = $r['nume_afisat'] . ' (' . self::motiv($r['motiv']) . ')'; continue; }
            $this->fisiere->inregistreaza($r + ['incarcat_de' => $this->auth->user()['id'] ?? null]);
            $ok++;
        }
        $this->flash($erori ? 'eroare' : 'ok', "$ok fișier(e) încărcat(e)." . ($erori ? ' Respinse: ' . implode(', ', $erori) : ''));
        return $this->redirect($response, $inapoi);
    }

    public function redenumeste(Request $request, Response $response, array $args): Response
    {
        if ($this->csrfOk($request)) {
            $nume = trim((string) (((array) $request->getParsedBody())['nume_afisat'] ?? ''));
            if ($nume !== '') { $this->fisiere->redenumeste((int) $args['id'], $nume); $this->flash('ok', 'Nume actualizat.'); }
        }
        return $this->redirect($response, '/fisiere' . $this->queryInapoi($request));
    }

    public function sterge(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if (!$this->csrfOk($request)) {
            return $this->redirect($response, '/fisiere');
        }
        $folosit = $this->meniu->fisierFolosit($id);
        if ($folosit !== []) {
            $this->flash('eroare', 'Fișierul nu poate fi șters: este folosit de „' . implode('”, „', array_column($folosit, 'titlu')) . '”.');
            return $this->redirect($response, '/fisiere' . $this->queryInapoi($request));
        }
        $this->fisiere->sterge($id, $this->settings['upload']['dir']);
        $this->flash('ok', 'Fișier șters.');
        return $this->redirect($response, '/fisiere' . $this->queryInapoi($request));
    }

    /** Upload de imagine din editorul Quill. JSON. */
    public function editor(Request $request, Response $response): Response
    {
        if (!$this->csrfOk($request)) {
            return $this->json($response, ['eroare' => 'CSRF'], 403);
        }
        $f = $request->getUploadedFiles()['imagine'] ?? null;
        if ($f === null) { return $this->json($response, ['eroare' => 'Lipsește imaginea.'], 422); }
        $r = $this->upload->salveaza($f);
        if ($r['motiv'] !== null || !Upload::esteImagine($r['mime'])) {
            if ($r['cale'] !== null) { @unlink($this->settings['upload']['dir'] . '/' . $r['cale']); }
            return $this->json($response, ['eroare' => 'Doar imagini jpg/png/webp, maxim ' . (int) ($this->settings['upload']['max_bytes'] / 1048576) . ' MB.'], 422);
        }
        $this->fisiere->inregistreaza($r + ['incarcat_de' => $this->auth->user()['id'] ?? null]);
        return $this->json($response, ['url' => $this->settings['app']['base_path'] . $this->settings['upload']['url'] . '/' . $r['cale']]);
    }

    private function queryInapoi(Request $request): string
    {
        $q = array_intersect_key((array) $request->getParsedBody() + $request->getQueryParams(), array_flip(['an', 'luna', 'q', 'picker', 'imagini']));
        return $q ? '?' . http_build_query($q) : '';
    }

    public static function motiv(string $m): string
    {
        return ['prea_mare' => 'prea mare', 'tip_nepermis' => 'tip de fișier nepermis', 'eroare' => 'eroare la încărcare'][$m] ?? $m;
    }
}
