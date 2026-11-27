<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/** Preserve basic editorial formatting without executable markup or unsafe links. */
final class CmsContentHtml
{
    public static function render(?string $html): string
    {
        if (!$html) return '';
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><div id="rise-content-root">'.$html.'</div>', LIBXML_NONET);
            $root = $document->getElementById('rise-content-root');
            if (!$root) return '';
            self::clean($root);
            $result = '';
            foreach ($root->childNodes as $node) $result .= $document->saveHTML($node);
            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function clean(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_COMMENT_NODE) { $parent->removeChild($node); continue; }
            if (!$node instanceof DOMElement) continue;
            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math'], true)) {
                $parent->removeChild($node); continue;
            }
            self::clean($node);
            if (!in_array($tag, ['p','br','strong','b','em','i','u','ul','ol','li','h2','h3','h4','blockquote','a','span','code','pre'], true)) {
                while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                $parent->removeChild($node); continue;
            }
            $href = $tag === 'a' ? $node->getAttribute('href') : null;
            foreach (iterator_to_array($node->attributes) as $attribute) $node->removeAttribute($attribute->name);
            if ($href && B1ContentUrl::allowed($href)) {
                $node->setAttribute('href', $href);
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }
}
