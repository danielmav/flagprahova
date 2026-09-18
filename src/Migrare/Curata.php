<?php
declare(strict_types=1);

namespace App\Migrare;

use App\Support\Html;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Transformă HTML-ul vechi din WordPress (shortcode-uri Visual Composer, blocuri
 * Gutenberg, spam injectat, căi `/wp-content/uploads/`) în HTML curat și extrage
 * galeriile ca date separate.
 */
final class Curata
{
    public const SPAM = '#ghostwriter|essay|paper[- ]?writ|payday|casino|brides|dating|hookup|cbd|resume[- ]help|loans?\b#i';
    public const SPAM_TEXT = '#ghostwriter|essay|paper writing|write my|payday|casino|brides|hookup|informationen angeordnet#i';
    private const DOMENII_PROPRII = ['flagprahova.ro', 'www.flagprahova.ro', 'youtube.com', 'www.youtube.com', 'youtu.be', 'www.youtube-nocookie.com'];

    /** @var callable(string):?string */
    private $rezolvaCale;

    public function __construct(callable $rezolvaCale)
    {
        $this->rezolvaCale = $rezolvaCale;
    }

    /**
     * @return array{html: string, galerii: array<int, array{titlu: string, ids: int[]}>, linkuri_rupte: string[], externe: string[], spam_eliminat: int}
     */
    public function proceseaza(string $html): array
    {
        $galerii = [];
        $rupte = [];
        $externe = [];
        $spam = 0;

        // 1. Gutenberg
        $html = preg_replace('#<!--\s*/?wp:[^>]*-->#s', '', $html) ?? $html;
        $html = preg_replace_callback('#<div class="wp-block-file">(.*?)</div>#s', static function (array $m): string {
            if (preg_match('#<a\b[^>]*href="([^"]+)"[^>]*>(.*?)</a>#s', $m[1], $a)) {
                return '<p><a href="' . $a[1] . '">' . strip_tags($a[2]) . '</a></p>';
            }
            return '';
        }, $html) ?? $html;

        // 2. VC
        $html = preg_replace_callback('#\[vc_tta_section\b([^\]]*)\](.*?)\[/vc_tta_section\]#s', static function (array $m) use (&$galerii): string {
            $titlu = preg_match('#title="([^"]*)"#', $m[1], $t) ? html_entity_decode($t[1], ENT_QUOTES, 'UTF-8') : 'Galerie';
            if (preg_match('#\[vc_gallery\b[^\]]*images="([0-9,\s]+)"#', $m[2], $g)) {
                $ids = array_values(array_filter(array_map('intval', explode(',', $g[1]))));
                $galerii[] = ['titlu' => $titlu, 'ids' => $ids];
                return '';
            }
            return $m[2];
        }, $html) ?? $html;
        $html = preg_replace_callback('#\[vc_video\b[^\]]*link="([^"]+)"[^\]]*\]#', static function (array $m): string {
            if (preg_match('#(?:v=|youtu\.be/|embed/)([A-Za-z0-9_-]{6,})#', $m[1], $v)) {
                return '<iframe src="https://www.youtube.com/embed/' . $v[1] . '" width="560" height="315" allowfullscreen></iframe>';
            }
            return '';
        }, $html) ?? $html;
        $html = preg_replace('#\[/?vc_[a-z_]+\b[^\]]*\]#', '', $html) ?? $html;
        // 3. alte shortcode-uri
        $html = preg_replace('#\[/?[a-z][a-z0-9_-]*(?:\s[^\]]*)?\]#', '', $html) ?? $html;
        $html = str_replace(['&nbsp;', "\u{A0}"], ' ', $html);

        // 4–7 pe DOM
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="radacina">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);
        $rad = $dom->getElementById('radacina');
        if ($rad === null) {
            return ['html' => '', 'galerii' => $galerii, 'linkuri_rupte' => [], 'externe' => [], 'spam_eliminat' => 0];
        }

        // 4. spam: linkuri, apoi blocuri
        foreach (iterator_to_array($xp->query('.//a[@href]', $rad)) as $a) {
            if (preg_match(self::SPAM, $a->getAttribute('href'))) {
                $a->parentNode?->removeChild($a);
                $spam++;
            }
        }
        foreach (iterator_to_array($xp->query('.//p|.//li|.//div', $rad)) as $el) {
            if ($el->parentNode !== null && preg_match(self::SPAM_TEXT, $el->textContent)) {
                $el->parentNode->removeChild($el);
                $spam++;
            }
        }

        // 5–6. href/src
        foreach (iterator_to_array($xp->query('.//a[@href]|.//img[@src]', $rad)) as $el) {
            /** @var DOMElement $el */
            $atr = $el->tagName === 'img' ? 'src' : 'href';
            $url = $el->getAttribute($atr);
            $cale = Legacy::caleDinUrl($url);
            if ($cale !== null) {
                $noua = ($this->rezolvaCale)($cale);
                if ($noua !== null) {
                    $el->setAttribute($atr, '/fisiere/' . $noua);
                } else {
                    $rupte[] = $url;
                    if ($el->tagName === 'img') {
                        $el->parentNode?->removeChild($el);
                    } else {
                        $text = $dom->createTextNode($el->textContent);
                        $el->parentNode?->replaceChild($text, $el);
                    }
                }
                continue;
            }
            if (preg_match('#^https?://([^/]+)#i', $url, $h)) {
                $host = strtolower($h[1]);
                if (!in_array($host, self::DOMENII_PROPRII, true) && !in_array($host, $externe, true)) {
                    $externe[] = $host;
                }
            }
        }

        // 7. h1 → h2 (pagina are un singur h1, titlul)
        foreach (iterator_to_array($xp->query('.//h1', $rad)) as $h1) {
            $h2 = $dom->createElement('h2');
            while ($h1->firstChild) { $h2->appendChild($h1->firstChild); }
            $h1->parentNode?->replaceChild($h2, $h1);
        }

        $out = '';
        foreach ($rad->childNodes as $c) { $out .= $dom->saveHTML($c); }
        $out = preg_replace('/>\s+</', '><', trim($out)) ?? $out;

        return [
            'html'          => Html::curata($out),
            'galerii'       => $galerii,
            'linkuri_rupte' => $rupte,
            'externe'       => $externe,
            'spam_eliminat' => $spam,
        ];
    }
}
