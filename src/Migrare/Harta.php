<?php
declare(strict_types=1);

namespace App\Migrare;

/**
 * Regulile de mapare dintre meniul WordPress vechi și structura nouă:
 * ce intrare devine ce tip, ce trece în perioada 2021-2027 și cum arată
 * meniul fix al perioadei noi.
 */
final class Harta
{
    /** Intrările vechi care trec în secțiunea 2021-2027 (Noutăți + Strategie). */
    public const SET_2021 = [3622, 3615, 3617, 3604, 3607, 3609, 3611, 3613, 3620];

    /**
     * Intrări din Noutăți 2014-2020 mutate în Arhiva 2021-2027 (cerința clientului,
     * 2026-09-22), în această ordine: DIGICO, recrutare personal, DigiWork,
     * recrutare GT 313133, FOCUS, recrutare GT 313141, TPIC.
     */
    public const ARHIVA_2021_COPII = [3601, 3598, 3595, 3586, 3583, 3577, 3580];

    /** Pagina Acasă nu devine intrare de meniu — textul ei merge în `sectiuni.acasa_html`. */
    public const PAGINI_SARITE = [75];

    public const LEGACY_CONTACT_VECHI = 175; // intrarea de meniu a paginii 156

    /** Pagini la care `post_content` e doar spam, dar `_variant_page_builder_html` are conținutul real. */
    public const PAGINI_BUILDER = [277];

    public const NOUTATI_2021 = 9001;
    public const STRATEGIE_2021 = 9002;
    public const ARHIVA_2021 = 9005;
    public const UTILE_2021 = 9008;
    public const CONTACT_2021 = 9009;
    public const RETETE_2021 = 9082; // pagina „Rețete” (fostul conținut al lui „Utile”)

    /** @var array<int, array{legacy:int,titlu:string,tip:string,sablon?:string,copii?:array}> */
    public const MENIU_2021 = [
        ['legacy' => 9001, 'titlu' => 'Noutăți', 'tip' => 'dosar'],
        ['legacy' => 9002, 'titlu' => 'Strategie', 'tip' => 'dosar'],
        ['legacy' => 9003, 'titlu' => 'Acțiuni', 'tip' => 'dosar'],
        ['legacy' => 9004, 'titlu' => 'Apel lansare', 'tip' => 'dosar'],
        ['legacy' => 9006, 'titlu' => 'Proceduri operaționale FLAG', 'tip' => 'dosar'],
        ['legacy' => 9007, 'titlu' => 'Media', 'tip' => 'dosar', 'copii' => [
            ['legacy' => 9071, 'titlu' => 'Comunicate de presă', 'tip' => 'dosar'],
            ['legacy' => 9072, 'titlu' => 'Animări', 'tip' => 'dosar'],
            ['legacy' => 9073, 'titlu' => 'Galerie', 'tip' => 'dosar'],
        ]],
        ['legacy' => 9008, 'titlu' => 'Utile', 'tip' => 'dosar', 'copii' => [
            ['legacy' => 9081, 'titlu' => 'Documente', 'tip' => 'dosar'],
            ['legacy' => 9082, 'titlu' => 'Rețete', 'tip' => 'pagina'],
        ]],
        ['legacy' => 9005, 'titlu' => 'Arhivă', 'tip' => 'dosar'],
        ['legacy' => 9009, 'titlu' => 'Contact', 'tip' => 'pagina', 'sablon' => 'contact'],
    ];

    public static function sectiuneaPentru(int $legacyId): string
    {
        return in_array($legacyId, self::SET_2021, true) || in_array($legacyId, self::ARHIVA_2021_COPII, true) ? '2021-2027' : '2014-2020';
    }

    /** @return array{tip:string,url:?string,cale:?string,motiv:?string} */
    public static function clasifica(array $item, ?array $pagina, bool $areCopii, callable $existaFisier): array
    {
        $out = ['tip' => 'sari', 'url' => null, 'cale' => null, 'motiv' => null];
        if ($item['tip'] === 'post_type') {
            if ($pagina === null) {
                $out['motiv'] = 'pagina inexistenta';
                return $out;
            }
            if (trim((string) $pagina['continut']) !== '') {
                $out['tip'] = 'pagina';
                return $out;
            }
            if ($areCopii) {
                $out['tip'] = 'dosar';
                return $out;
            }
            $out['motiv'] = 'pagina goala';
            return $out;
        }
        $url = (string) $item['url'];
        $cale = Legacy::caleDinUrl($url);
        if ($cale !== null) {
            if ($existaFisier($cale)) {
                return ['tip' => 'document', 'url' => null, 'cale' => $cale, 'motiv' => null];
            }
            $out['motiv'] = 'fisier lipsa: ' . $cale;
            return $out;
        }
        // `http://ab`, `http://#`, `http:///wp-content/…` — host fără punct: nu e un sit extern real.
        if (preg_match('#^https?://([^/]+)#i', $url, $m) && !preg_match('#(^|\.)flagprahova\.ro$#i', $m[1]) && str_contains($m[1], '.')) {
            return ['tip' => 'link', 'url' => $url, 'cale' => null, 'motiv' => null];
        }
        if ($areCopii) {
            $out['tip'] = 'dosar';
            return $out;
        }
        $out['motiv'] = 'placeholder fara copii';
        return $out;
    }
}
