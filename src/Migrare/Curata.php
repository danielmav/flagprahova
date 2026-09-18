<?php
declare(strict_types=1);

namespace App\Migrare;

use App\Support\Html;
use DOMElement;
use DOMDocument;
use DOMNode;
use DOMText;
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

    /** Etichete de bloc: între ele spațiile albe nu contează la serializare. */
    private const BLOCURI = ['p', 'div', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'section', 'article', 'blockquote', 'iframe', 'figure', 'hr'];

    /** Blocuri care pot conține alte blocuri; un candidat la spam care le conține e „ambalaj”, nu frunză. */
    private const CONTAINERE = ['p', 'div', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'section', 'article', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

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
        $html = preg_replace_callback('#<div\b[^>]*class="[^"]*\bwp-block-file\b[^"]*"[^>]*>(.*?)</div>#s', static function (array $m): string {
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

        // 4a. Spam pe blocuri, ÎNAINTE de a scoate ancorele: altfel textul spam
        // dispare odată cu ancora și blocul rămâne mutilat, dar viu.
        foreach (iterator_to_array($xp->query('.//p|.//li|.//div', $rad)) as $el) {
            /** @var DOMElement $el */
            // Doar blocuri-frunză: un ambalaj cu blocuri înăuntru ar lua cu el
            // și conținutul bun din jurul spamului.
            if (!$this->atasat($el, $rad) || !$this->esteFrunza($el)) {
                continue;
            }
            if (preg_match(self::SPAM_TEXT, $el->textContent)) {
                $el->parentNode?->removeChild($el);
                $spam++;
            }
        }
        // 4b. Ancore spam rămase: scoatem linkul, păstrăm textul (poate fi un
        // cuvânt legitim ancorat de spammer în mijlocul unei fraze reale).
        foreach (iterator_to_array($xp->query('.//a[@href]', $rad)) as $a) {
            /** @var DOMElement $a */
            if (!$this->atasat($a, $rad) || !preg_match(self::SPAM, $a->getAttribute('href'))) {
                continue;
            }
            $this->inlocuiesteCuText($dom, $a, $a->textContent);
            $spam++;
        }

        // 5–6. href/src
        foreach (iterator_to_array($xp->query('.//a[@href]|.//img[@src]', $rad)) as $el) {
            /** @var DOMElement $el */
            if (!$this->atasat($el, $rad)) {
                continue;
            }
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
                        // `<a><img></a>` fără text: cădem pe alt/title, altfel
                        // scoatem tot (ca să nu rămână un `<p></p>` gol).
                        $this->inlocuiesteCuText($dom, $el, $this->textAncora($el));
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

        // 8. Blocuri rămase goale după eliminări (ex. `<p><a><img></a></p>` cu
        // fișier lipsă). În ordine inversă, ca să cadă și ambalajele.
        $goale = iterator_to_array($xp->query('.//p|.//li|.//div|.//blockquote|.//h1|.//h2|.//h3|.//h4|.//h5|.//h6', $rad));
        foreach (array_reverse($goale) as $el) {
            /** @var DOMElement $el */
            if (!$this->atasat($el, $rad) || trim($el->textContent) !== '') {
                continue;
            }
            if ($el->getElementsByTagName('img')->length > 0 || $el->getElementsByTagName('iframe')->length > 0) {
                continue;
            }
            $el->parentNode?->removeChild($el);
        }

        $out = '';
        foreach ($rad->childNodes as $c) {
            if ($c instanceof DOMElement || $c instanceof DOMText) { $out .= (string) $dom->saveHTML($c); }
        }

        return [
            // wpautop se aplică DUPĂ `Html::curata`: acesta despachetează
            // `div`/`span`, expunând text care până atunci era învelit.
            'html'          => $this->paragrafeaza(Html::curata($this->colapseazaSpatii(trim($out)))),
            'galerii'       => $galerii,
            'linkuri_rupte' => array_values(array_unique($rupte)),
            'externe'       => $externe,
            'spam_eliminat' => $spam,
        ];
    }

    /** Nodul mai e în arbore sub rădăcină? (un strămoș poate fi fost deja scos) */
    private function atasat(DOMNode $nod, DOMElement $rad): bool
    {
        for ($p = $nod->parentNode; $p !== null; $p = $p->parentNode) {
            if ($p === $rad) { return true; }
        }
        return false;
    }

    /** Bloc fără alte blocuri înăuntru (deci textul lui îi aparține în întregime). */
    private function esteFrunza(DOMElement $el): bool
    {
        foreach ($el->getElementsByTagName('*') as $d) {
            if (in_array(strtolower($d->tagName), self::CONTAINERE, true)) { return false; }
        }
        return true;
    }

    /** Textul de afișat pentru o ancoră ruptă: textul ei, altfel alt/title de pe imaginea din ea. */
    private function textAncora(DOMElement $a): string
    {
        $text = trim($a->textContent);
        if ($text !== '') { return $a->textContent; }
        foreach ($a->getElementsByTagName('img') as $img) {
            foreach (['alt', 'title'] as $atr) {
                $v = trim($img->getAttribute($atr));
                if ($v !== '') { return $v; }
            }
        }
        return '';
    }

    /** Înlocuiește elementul cu textul dat; dacă textul e gol, scoate elementul. */
    private function inlocuiesteCuText(DOMDocument $dom, DOMElement $el, string $text): void
    {
        $parinte = $el->parentNode;
        if ($parinte === null) { return; }
        if (trim($text) === '') {
            $parinte->removeChild($el);
            return;
        }
        $parinte->replaceChild($dom->createTextNode($text), $el);
    }

    /**
     * Echivalentul lui `wpautop()`: împachetează în `<p>` secvențele de text/inline
     * de la nivelul rădăcinii (paragrafe separate doar prin `\n` în HTML-ul vechi).
     */
    private function paragrafeaza(string $html): string
    {
        if (trim($html) === '') { return ''; }
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="radacina">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $rad = $dom->getElementById('radacina');
        if ($rad === null) { return $html; }

        $out = '';
        $tampon = '';
        $goleste = static function () use (&$out, &$tampon): void {
            $t = trim($tampon);
            $tampon = '';
            if ($t === '') { return; }
            $parti = preg_split('/\n\s*\n/', $t) ?: [$t];
            if (count($parti) < 2) { $parti = preg_split('/\n/', $t) ?: [$t]; }
            foreach ($parti as $p) {
                $p = trim($p);
                if ($p !== '') { $out .= '<p>' . $p . '</p>'; }
            }
        };
        foreach ($rad->childNodes as $c) {
            $bucata = (string) $dom->saveHTML($c);
            if ($c instanceof DOMElement && in_array(strtolower($c->tagName), self::BLOCURI, true)) {
                $goleste();
                $out .= $bucata;
                continue;
            }
            if (!$c instanceof DOMElement && !$c instanceof DOMText) {
                continue; // comentarii, instrucțiuni de procesare
            }
            $tampon .= $bucata;
        }
        $goleste();

        return $this->colapseazaSpatii(trim($out));
    }

    /** `</p>\n<p>` → `</p><p>`, dar `</strong> <em>` rămâne cu spațiul lui. */
    private function colapseazaSpatii(string $html): string
    {
        return preg_replace_callback(
            '#(</?)([a-z0-9]+)([^>]*)>\s+(?=</?([a-z0-9]+))#i',
            static function (array $m): string {
                $eticheta = $m[1] . $m[2] . $m[3] . '>';
                $bloc = in_array(strtolower($m[2]), self::BLOCURI, true)
                    || in_array(strtolower($m[4]), self::BLOCURI, true);
                return $bloc ? $eticheta : $eticheta . ' ';
            },
            $html
        ) ?? $html;
    }
}
