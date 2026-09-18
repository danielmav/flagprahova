<?php
declare(strict_types=1);

namespace App\Public;

use App\Fisiere\Repository as Fisiere;
use App\Meniu\Repository as Meniu;
use App\Setari\Repository as Setari;

/** Datele comune ale layout-ului public, calculate o dată per cerere. */
final class Context
{
    private ?array $sectiuni = null;

    public function __construct(
        private Meniu $meniu,
        private Fisiere $fisiere,
        private Setari $setari,
        private array $settings
    ) {
    }

    public function sectiuni(): array
    {
        return $this->sectiuni ??= $this->meniu->sectiuni();
    }

    public function sectiune(string $slug): ?array
    {
        foreach ($this->sectiuni() as $s) {
            if ($s['slug'] === $slug) {
                return $s;
            }
        }
        return null;
    }

    public function base(): string
    {
        return (string) $this->settings['app']['base_path'];
    }

    /** Arborele vizibil al secțiunii, cu `href` și `extern` pe fiecare nod. */
    public function arbore(array $sectiune): array
    {
        $decoreaza = function (array $lista) use (&$decoreaza, $sectiune): array {
            foreach ($lista as &$n) {
                $n['extern'] = $n['tip'] === 'link';
                $n['href']   = match ($n['tip']) {
                    'document' => $n['fisier_cale'] !== null
                        ? $this->base() . $this->settings['upload']['url'] . '/' . $n['fisier_cale']
                        : '',
                    'link'     => (string) $n['url'],
                    default    => $this->base() . '/' . $sectiune['slug'] . '/' . $n['slug'],
                };
                $n['copii'] = $decoreaza($n['copii']);
            }
            unset($n);
            return $lista;
        };
        return $decoreaza($this->meniu->arborePublic((int) $sectiune['id']));
    }

    /**
     * Aceeași regulă de link, pentru un rând brut din `meniu` (are `fisier_id`,
     * nu `fisier_cale`). Întoarce '' pentru un document fără fișier.
     */
    public function href(array $rand, string $slugSectiune): string
    {
        if ($rand['tip'] === 'link') {
            return (string) $rand['url'];
        }
        if ($rand['tip'] === 'document') {
            $f = ($rand['fisier_id'] ?? null) !== null ? $this->fisiere->gaseste((int) $rand['fisier_id']) : null;
            return $f !== null ? $this->base() . $this->settings['upload']['url'] . '/' . $f['cale'] : '';
        }
        return $this->base() . '/' . $slugSectiune . '/' . $rand['slug'];
    }

    /**
     * Caută un slug în arborele deja decorat.
     * @return array{nod: array, stramosi: array}|null null dacă nu e în arbore
     *         (invizibil, sau cu un strămoș invizibil).
     */
    public function gaseste(array $arbore, string $slug, array $stramosi = []): ?array
    {
        foreach ($arbore as $n) {
            if ($n['slug'] === $slug) {
                return ['nod' => $n, 'stramosi' => $stramosi];
            }
            $g = $this->gaseste($n['copii'], $slug, [...$stramosi, $n]);
            if ($g !== null) {
                return $g;
            }
        }
        return null;
    }

    /**
     * Variabilele pe care le așteaptă `layout.twig`. `$extra` are prioritate,
     * deci un controller poate suprascrie orice cheie (ex. `activ`).
     */
    public function variabile(?array $sectiune, string $canonicalPath, array $extra = []): array
    {
        $cealalta = null;
        if ($sectiune !== null) {
            foreach ($this->sectiuni() as $s) {
                if ((int) $s['id'] !== (int) $sectiune['id']) {
                    $cealalta = $s;
                }
            }
        }
        return $extra + [
            'sectiuni'       => $this->sectiuni(),
            'sectiune'       => $sectiune,
            'arbore'         => $sectiune !== null ? $this->arbore($sectiune) : [],
            'cealalta'       => $cealalta,
            'setari'         => $this->setari->toate(),
            'canonical_path' => $canonicalPath,
            'activ'          => [],
        ];
    }
}
