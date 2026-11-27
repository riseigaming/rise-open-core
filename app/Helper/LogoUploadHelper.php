<?php

namespace App\Helper;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Static vector logos only. SVG is deliberately not enabled for other uploads. */
class LogoUploadHelper
{
    private const SVG_NS = 'http://www.w3.org/2000/svg';
    private const TAGS = ['svg', 'g', 'defs', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'linearGradient', 'radialGradient', 'stop', 'clipPath', 'mask', 'use', 'title', 'desc', 'text', 'tspan'];
    private const ATTRIBUTES = ['id', 'version', 'viewBox', 'preserveAspectRatio', 'x', 'y', 'x1', 'y1', 'x2', 'y2',
        'width', 'height', 'd', 'points', 'cx', 'cy', 'r', 'rx', 'ry', 'dx', 'dy', 'rotate', 'transform',
        'fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'opacity',
        'clip-path', 'clip-rule', 'mask', 'maskUnits', 'maskContentUnits', 'clipPathUnits',
        'gradientUnits', 'gradientTransform', 'spreadMethod', 'offset', 'stop-color', 'stop-opacity', 'fx', 'fy', 'fr',
        'color', 'vector-effect', 'paint-order', 'font-family', 'font-size', 'font-weight', 'font-style',
        'text-anchor', 'dominant-baseline', 'letter-spacing', 'word-spacing', 'display', 'visibility', 'style', 'href'];
    private const STYLE_PROPERTIES = ['fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-linecap',
        'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity',
        'opacity', 'clip-path', 'clip-rule', 'mask', 'stop-color', 'stop-opacity', 'color', 'vector-effect',
        'paint-order', 'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor', 'dominant-baseline',
        'letter-spacing', 'word-spacing', 'display', 'visibility'];

    public static function upload(UploadedFile $file, string $title, string $path, int $width = 256, int $height = 131): string
    {
        $mime = strtolower((string) $file->getMimeType());
        if (strtolower($file->getClientOriginalExtension()) !== 'svg' && $mime !== 'image/svg+xml') {
            return UploadHelper::upload($file, $title, $path, $width, $height);
        }
        if (! $file->isValid() || $file->getSize() > 2 * 1024 * 1024) {
            throw new InvalidArgumentException('SVG logo geçerli olmalı ve 2 MB sınırını aşmamalı.');
        }
        $contents = file_get_contents($file->getRealPath());
        $safeSvg = self::validateSvg($contents === false ? '' : $contents);
        $name = $title.'-'.bin2hex(random_bytes(12)).'.svg';
        if (! Storage::disk('public')->put(trim($path, '/').'/'.$name, $safeSvg)) {
            throw new InvalidArgumentException('Logo kaydedilemedi.');
        }
        return $name;
    }

    public static function validateSvg(string $source): string
    {
        if ($source === '' || strlen($source) > 2 * 1024 * 1024 || preg_match('/<!DOCTYPE|<!ENTITY/i', $source)) {
            throw new InvalidArgumentException('SVG logo DTD veya entity içermemeli.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            $loaded = $document->loadXML($source, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded || ! $document->documentElement || $document->doctype
            || $document->documentElement->localName !== 'svg'
            || $document->documentElement->namespaceURI !== self::SVG_NS) {
            throw new InvalidArgumentException('Geçerli bir SVG logo yükleyin.');
        }
        $count = 0;
        self::validateNode($document, 0, $count);
        // Save only the validated root: no declarations, comments or processing instructions outside it.
        return $document->saveXML($document->documentElement);
    }

    private static function validateNode(DOMNode $node, int $depth, int &$count): void
    {
        if (++$count > 10000 || $depth > 64 || $node->nodeType === XML_PI_NODE) {
            throw new InvalidArgumentException('SVG logo fazla karmaşık veya etkin içerik içeriyor.');
        }
        if ($node instanceof DOMElement) {
            if ($node->namespaceURI !== self::SVG_NS || ! in_array($node->localName, self::TAGS, true)) {
                throw new InvalidArgumentException('SVG yalnız statik vektör içermeli; script, style, görsel veya animasyon öğeleri desteklenmiyor.');
            }
            foreach ($node->attributes as $attribute) {
                if ($attribute->namespaceURI === 'http://www.w3.org/2000/xmlns/') continue;
                $name = $attribute->localName;
                $value = $attribute->value;
                $namespace = $attribute->namespaceURI;
                if (($namespace && ! ($namespace === 'http://www.w3.org/1999/xlink' && $name === 'href'))
                    || ! in_array($name, self::ATTRIBUTES, true)) {
                    throw new InvalidArgumentException('SVG logo desteklenmeyen veya etkin bir özellik içeriyor.');
                }
                if ($name === 'href' && ! preg_match('/^#[a-zA-Z_][a-zA-Z0-9_.:-]*$/D', $value)) {
                    throw new InvalidArgumentException('SVG logo dış kaynak bağlantısı içeremez.');
                }
                if ($name === 'style') {
                    foreach (explode(';', $value) as $declaration) {
                        if (trim($declaration) === '') continue;
                        $parts = explode(':', $declaration, 2);
                        if (count($parts) !== 2 || ! in_array(strtolower(trim($parts[0])), self::STYLE_PROPERTIES, true)) {
                            throw new InvalidArgumentException('SVG logo desteklenmeyen bir stil içeriyor.');
                        }
                        self::validateValue($parts[1]);
                    }
                } else {
                    self::validateValue($value);
                }
            }
        }
        foreach ($node->childNodes as $child) self::validateNode($child, $depth + 1, $count);
    }

    private static function validateValue(string $value): void
    {
        $withoutLocalRefs = preg_replace('/url\(\s*[\'"]?#[a-zA-Z_][a-zA-Z0-9_.:-]*[\'"]?\s*\)/i', '', $value);
        if (preg_match('/[\\\\<>@{}]|\/\*|url\s*\(|expression\s*\(|javascript\s*:|data\s*:|https?\s*:|[\x00-\x08\x0B\x0C\x0E-\x1F]/i', $withoutLocalRefs)) {
            throw new InvalidArgumentException('SVG logo dış kaynak veya etkin içerik içeremez.');
        }
    }
}
