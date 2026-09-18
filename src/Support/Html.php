<?php
declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMText;

final class Html
{
    private const PERMISE = [
        'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'a' => ['href', 'target', 'rel'], 'img' => ['src', 'alt'],
        'iframe' => ['src', 'width', 'height', 'allowfullscreen'],
    ];

    /** Scheme acceptate în href/src: http(s), mailto, tel, cale absolută (nu „//”), ancoră, cale relativă. */
    private const URL_PERMIS = '#^(https?://|mailto:|tel:|/(?!/)|[\#?]|[^/:?\#]+(?:[?\#/]|$))#i';

    public static function curata(string $html): string
    {
        if (trim($html) === '') { return ''; }
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="radacina">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $rad = $dom->getElementById('radacina');
        if ($rad === null) { return ''; }
        self::parcurge($rad);
        $out = '';
        foreach ($rad->childNodes as $c) { $out .= $dom->saveHTML($c); }
        return trim($out);
    }

    private static function parcurge(DOMElement $el): void
    {
        for ($i = $el->childNodes->length - 1; $i >= 0; $i--) {
            $c = $el->childNodes->item($i);
            if (!$c instanceof DOMElement) {
                // Listă albă de noduri: doar text. Comentariile, instrucțiunile de
                // procesare (`<?x …`) și CDATA sunt scoase — altfel ar fi emise
                // verbatim de saveHTML() și ar putea rupe structura documentului.
                if (!$c instanceof DOMText) { $el->removeChild($c); }
                continue;
            }
            $tag = strtolower($c->tagName);
            if (in_array($tag, ['script', 'style', 'object', 'embed'], true)) {
                $el->removeChild($c);
                continue;
            }
            if (!isset(self::PERMISE[$tag])) {
                // Tag necunoscut (div, span, font…): păstrăm doar copiii.
                self::parcurge($c);
                while ($c->firstChild) { $el->insertBefore($c->firstChild, $c); }
                $el->removeChild($c);
                continue;
            }
            foreach (iterator_to_array($c->attributes) as $atr) {
                $n = strtolower($atr->name);
                $v = trim($atr->value);
                $ok = in_array($n, self::PERMISE[$tag], true)
                    && !preg_match('/^\s*(javascript|data|vbscript):/i', $v);
                // href/src: listă albă de scheme. `/(?!/)` respinge URL-urile
                // protocol-relative (`//evil.tld/x`), care altfel ar trece.
                if ($ok && ($n === 'href' || $n === 'src') && !preg_match(self::URL_PERMIS, $v)) { $ok = false; }
                if ($ok && $tag === 'iframe' && $n === 'src' && !preg_match('#^https://(www\.)?(youtube\.com|youtube-nocookie\.com)/embed/#', $v)) { $ok = false; }
                if (!$ok) { $c->removeAttribute($atr->name); }
            }
            if ($tag === 'iframe' && !$c->hasAttribute('src')) { $el->removeChild($c); continue; }
            if ($tag === 'a' && strtolower($c->getAttribute('target')) === '_blank') {
                // Păstrăm valorile puse de redactor (nofollow…) și adăugăm ce lipsește.
                $rel = preg_split('/\s+/', strtolower(trim($c->getAttribute('rel'))), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach (['noopener', 'noreferrer'] as $r) {
                    if (!in_array($r, $rel, true)) { $rel[] = $r; }
                }
                $c->setAttribute('rel', implode(' ', $rel));
            }
            self::parcurge($c);
        }
    }
}
