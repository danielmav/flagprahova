<?php
declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;

final class Html
{
    private const PERMISE = [
        'p' => [], 'br' => [], 'h2' => [], 'h3' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
        'a' => ['href', 'target', 'rel'], 'img' => ['src', 'alt'],
        'iframe' => ['src', 'width', 'height', 'allowfullscreen'],
    ];

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
                if ($c->nodeType === XML_COMMENT_NODE) { $el->removeChild($c); }
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
                    && !str_starts_with($n, 'on')
                    && !preg_match('/^\s*(javascript|data|vbscript):/i', $v);
                if ($ok && $tag === 'iframe' && $n === 'src' && !preg_match('#^https://(www\.)?(youtube\.com|youtube-nocookie\.com)/embed/#', $v)) { $ok = false; }
                if (!$ok) { $c->removeAttribute($atr->name); }
            }
            if ($tag === 'iframe' && !$c->hasAttribute('src')) { $el->removeChild($c); continue; }
            if ($tag === 'a' && strtolower($c->getAttribute('target')) === '_blank') { $c->setAttribute('rel', 'noopener'); }
            self::parcurge($c);
        }
    }
}
