<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

final class ProductDescriptionHtml
{
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'blockquote'];
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'head'];

    public static function sanitize(?string $html): ?string
    {
        if ($html === null || $html === '') return $html;

        // Rebuild only inert formatting elements. No input attributes, URLs, styles,
        // namespaces or markup from text nodes ever reach the rendered output.
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>', LIBXML_NONET);
            if (!$loaded) return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $body = $document->getElementsByTagName('body')->item(0);
            return $body ? self::children($body) : '';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function children(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $html .= htmlspecialchars($child->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            } elseif ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, self::DROP, true)) continue;
                $content = self::children($child);
                $html .= in_array($tag, self::TAGS, true)
                    ? ($tag === 'br' ? '<br>' : '<' . $tag . '>' . $content . '</' . $tag . '>')
                    : $content;
            }
        }
        return $html;
    }
}
