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

    /**
     * URL public ABSOLUT pentru o cale internă. Convenție: `APP_URL` include deja
     * `BASE_PATH` (staging în subfolder: `APP_URL=https://exemplu.ro/nou`,
     * `BASE_PATH=/nou`), iar `href()`/`arbore()` întorc căi CU bază — deci baza se
     * scoate din cale înainte de concatenare, altfel prefixul s-ar dubla.
     * Singurul loc cu regula asta: funcția Twig `url_public()` și `SeoController`
     * o apelează, nu o rescriu.
     */
    public function urlPublic(string $cale): string
    {
        $baza = $this->base();
        if ($baza !== '' && ($cale === $baza || str_starts_with($cale, $baza . '/'))) {
            $cale = substr($cale, strlen($baza));
        }
        return rtrim((string) $this->settings['app']['url'], '/') . ($cale === '' ? '/' : $cale);
    }

    /**
     * Rezumatul de ~155 de caractere folosit ca `meta description`: text curat,
     * spațiile colapsate, tăiat pe cuvânt.
     */
    public static function rezumat(string $html, int $max = 155): string
    {
        // Tag-urile de BLOC devin spațiu înainte de `strip_tags`, altfel „…proiect:"
        // s-ar lipi de titlul paragrafului următor; cele inline (`strong`, `a`) cad
        // fără spațiu, ca să nu rupă cuvintele din interiorul unei fraze.
        $bloc = '/<\s*\/?\s*(p|div|br|li|ul|ol|h[1-6]|tr|td|th|table|section|article|blockquote)\b[^>]*>/i';
        $text = html_entity_decode(strip_tags((string) preg_replace($bloc, ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $text)));
        if ($text === '' || mb_strlen($text) <= $max) {
            return $text;
        }
        $taiat = mb_substr($text, 0, $max);
        $spatiu = mb_strrpos($taiat, ' ');
        if ($spatiu !== false && $spatiu > 0) {
            $taiat = mb_substr($taiat, 0, $spatiu);
        }
        return rtrim($taiat, " ,.;:–-") . '…';
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
        // `meta description` din primele ~155 de caractere ale conținutului, când
        // există; șabloanele cad pe textul generic dacă rămâne gol.
        if (!isset($extra['descriere']) && ($extra['rand']['continut_html'] ?? '') !== '') {
            $extra['descriere'] = self::rezumat((string) $extra['rand']['continut_html']);
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
