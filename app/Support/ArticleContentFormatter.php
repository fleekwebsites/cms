<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

class ArticleContentFormatter
{
    public function __construct(private PublishableImageEncoder $imageEncoder) {}

    /**
     * @var array<string, string>
     */
    private const ALIGNMENT_CLASSES = [
        'ql-align-center' => 'text-align:center',
        'ql-align-right' => 'text-align:right',
        'ql-align-justify' => 'text-align:justify',
    ];

    /**
     * @var array<string, string>
     */
    private const SIZE_CLASSES = [
        'ql-size-small' => 'font-size:0.75em',
        'ql-size-large' => 'font-size:1.5em',
        'ql-size-huge' => 'font-size:2.5em',
    ];

    /**
     * @var array<string, string>
     */
    private const FONT_CLASSES = [
        'ql-font-serif' => 'font-family:Georgia, Times New Roman, serif',
        'ql-font-monospace' => 'font-family:Monaco, Courier New, monospace',
        'ql-font-arial' => 'font-family:Arial, Helvetica, sans-serif',
        'ql-font-georgia' => 'font-family:Georgia, serif',
        'ql-font-tahoma' => 'font-family:Tahoma, sans-serif',
        'ql-font-impact' => 'font-family:Impact, Charcoal, sans-serif',
    ];

    /**
     * @var array<int, string>
     */
    private const EMBED_TAGS = ['iframe', 'video', 'audio', 'picture', 'table', 'figure'];

    public function normalize(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return $html;
        }

        $html = $this->normalizeBreakingSpacesInHtml($html);

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="article-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('article-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $xpath = new DOMXPath($document);

        $this->wrapOrphanInlineContent($root, $document);
        $this->unwrapHtmlBlocks($document, $xpath);
        $this->unwrapHtmlInlines($document, $xpath);
        $this->restoreEscapedHtmlInDocument($document, $xpath);

        $xpath = new DOMXPath($document);
        $this->unwrapNoiseSpans($document, $xpath);
        $this->mergeEmptyStyledSpans($document);
        $this->stripDefaultCanvasStyles($document, $xpath);
        $this->stripLayoutBreakingStyles($document, $xpath);
        $this->normalizeBreakingSpacesInDocument($document);
        $this->unwrapExportNoiseElements($document, $xpath);
        $this->normalizeQuillLists($document, $xpath);

        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//*[@class]') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $this->applyAlignmentStyles($element);
            $this->applyIndentStyles($element);
            $this->applySizeStyles($element);
            $this->applyFontStyles($element);
            $this->applyColorAndBackgroundStyles($element);
            $this->applyDirectionStyles($element);
            $this->removeQuillClasses($element);
        }

        $formatted = '';

        foreach ($root->childNodes as $child) {
            $formatted .= $document->saveHTML($child);
        }

        return $formatted;
    }

    public function forPublish(string $html): string
    {
        $html = $this->normalize($html);

        if ($html === '') {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="article-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('article-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//img[@src]') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $this->embedLocalImage($element);
        }

        $formatted = '';

        foreach ($root->childNodes as $child) {
            $formatted .= $document->saveHTML($child);
        }

        return $formatted;
    }

    /**
     * @var array<int, string>
     */
    private const BLOCK_TAGS = [
        'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'td', 'th',
        'blockquote', 'pre', 'hr', 'iframe', 'video', 'audio', 'picture', 'figure',
        'dl', 'dt', 'dd', 'details', 'section', 'article', 'aside', 'header', 'footer', 'main', 'nav',
    ];

    private function wrapOrphanInlineContent(DOMElement $root, DOMDocument $document): void
    {
        $nodes = [];

        foreach ($root->childNodes as $child) {
            $nodes[] = $child;
        }

        while ($root->firstChild !== null) {
            $root->removeChild($root->firstChild);
        }

        $groups = [];
        $current = [];

        $flush = function () use (&$groups, &$current): void {
            if ($current !== []) {
                $groups[] = $current;
                $current = [];
            }
        };

        foreach ($nodes as $node) {
            if ($node instanceof DOMElement && in_array(strtolower($node->tagName), self::BLOCK_TAGS, true)) {
                $flush();
                $groups[] = [$node];

                continue;
            }

            if ($node instanceof \DOMText && trim($node->textContent) === '') {
                continue;
            }

            $current[] = $node;
        }

        $flush();

        foreach ($groups as $group) {
            if (count($group) === 1 && $group[0] instanceof DOMElement && in_array(strtolower($group[0]->tagName), self::BLOCK_TAGS, true)) {
                $root->appendChild($group[0]);

                continue;
            }

            $paragraph = $document->createElement('p');

            foreach ($group as $node) {
                $paragraph->appendChild($node);
            }

            $root->appendChild($paragraph);
        }
    }

    /**
     * @var array<int, string>
     */
    private const RESTORABLE_CONTAINER_TAGS = [
        'p', 'div', 'li', 'td', 'th', 'dd', 'dt', 'figcaption', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    ];

    private function restoreEscapedHtmlInDocument(DOMDocument $document, DOMXPath $xpath): void
    {
        foreach (self::RESTORABLE_CONTAINER_TAGS as $tag) {
            foreach ($xpath->query('//'.$tag) ?: [] as $element) {
                if (! $element instanceof DOMElement) {
                    continue;
                }

                $innerHtml = $this->elementInnerHtml($element);

                if (! str_contains($innerHtml, '&lt;') && ! str_contains($innerHtml, '&#60;') && ! str_contains($innerHtml, '&#x3c;')) {
                    continue;
                }

                $restored = $this->decodeEscapedTags($innerHtml);

                if ($restored === $innerHtml) {
                    continue;
                }

                $this->setElementInnerHtml($document, $element, $restored);

                if ($tag !== 'p' || $element->childNodes->length !== 1) {
                    continue;
                }

                $child = $element->firstChild;

                if ($child instanceof DOMElement && in_array(strtolower($child->tagName), self::EMBED_TAGS, true)) {
                    $this->replaceElementWithHtml($document, $element, $document->saveHTML($child));
                }
            }
        }
    }

    private function decodeEscapedTags(string $html): string
    {
        $patterns = [
            '/&lt;((?:\/\s*)?[\w-]+(?:\s+(?:[^<&]|&(?:quot|amp|lt|gt|#\d+);)*)?\s*(?:\/\s*)?)&gt;/i',
            '/&#(?:x3c|60);((?:\/\s*)?[\w-]+(?:\s+(?:[^<&]|&(?:quot|amp|lt|gt|#\d+);)*)?\s*(?:\/\s*)?)&#(?:x3e|62);/i',
        ];

        $previous = null;

        while ($previous !== $html) {
            $previous = $html;

            foreach ($patterns as $pattern) {
                $html = preg_replace_callback(
                    $pattern,
                    static fn (array $matches): string => html_entity_decode('<'.$matches[1].'>', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    $html,
                ) ?? $html;
            }
        }

        return $html;
    }

    private function setElementInnerHtml(DOMDocument $document, DOMElement $element, string $html): void
    {
        while ($element->firstChild !== null) {
            $element->removeChild($element->firstChild);
        }

        $fragment = $document->createDocumentFragment();
        $temporary = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $temporary->loadHTML(
            '<?xml encoding="UTF-8"><div id="inner-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $temporary->getElementById('inner-root');

        if (! $root instanceof DOMElement) {
            return;
        }

        foreach ($root->childNodes as $child) {
            $fragment->appendChild($document->importNode($child, true));
        }

        $element->appendChild($fragment);
    }

    private function normalizeBreakingSpacesInHtml(string $html): string
    {
        return str_replace(
            ["\u{00A0}", '&#160;', '&#xA0;', '&nbsp;'],
            ' ',
            $html,
        );
    }

    private function normalizeBreakingSpacesInDocument(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//text()') ?: [] as $textNode) {
            if (! $textNode instanceof \DOMText) {
                continue;
            }

            $value = $textNode->nodeValue;

            if (! str_contains($value, "\u{00A0}")) {
                continue;
            }

            $textNode->nodeValue = str_replace("\u{00A0}", ' ', $value);
        }
    }

    private function unwrapExportNoiseElements(DOMDocument $document, DOMXPath $xpath): void
    {
        foreach ($xpath->query('//*[contains(@class, "qwen-markdown")]') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $element->removeAttribute('class');
            $element->removeAttribute('dir');
        }
    }

    private function unwrapNoiseSpans(DOMDocument $document, DOMXPath $xpath): void
    {
        $spans = [];

        foreach ($xpath->query('//span[contains(@class, "qwen-markdown")]') ?: [] as $span) {
            if ($span instanceof DOMElement) {
                $spans[] = $span;
            }
        }

        usort($spans, fn (DOMElement $left, DOMElement $right): int => $this->nodeDepth($right) <=> $this->nodeDepth($left));

        foreach ($spans as $span) {
            if ($span->parentNode === null) {
                continue;
            }

            $this->replaceElementWithHtml($document, $span, $this->elementInnerHtml($span));
        }
    }

    private function mergeEmptyStyledSpans(DOMDocument $document): void
    {
        $spans = [];

        foreach ($document->getElementsByTagName('span') as $span) {
            if (! $span instanceof DOMElement) {
                continue;
            }

            if (! $span->hasAttribute('style') || trim($span->textContent) !== '') {
                continue;
            }

            $spans[] = $span;
        }

        foreach ($spans as $span) {
            if ($span->parentNode === null || trim($span->textContent) !== '') {
                continue;
            }

            $next = $span->nextSibling;

            while ($next instanceof \DOMText && trim($next->textContent) === '') {
                $next = $next->nextSibling;
            }

            if ($next instanceof \DOMText && ! $this->isSeparatorText($next->textContent)) {
                $span->appendChild($next);

                continue;
            }

            if ($next instanceof DOMElement && trim($next->textContent) !== '' && ! $this->isSeparatorText($next->textContent)) {
                while ($next->firstChild !== null) {
                    $span->appendChild($next->firstChild);
                }

                $next->parentNode?->removeChild($next);
            }
        }
    }

    private function stripDefaultCanvasStyles(DOMDocument $document, DOMXPath $xpath): void
    {
        foreach ($xpath->query('//*[@style]') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $cleaned = $this->removeDefaultCanvasDeclarations($element->getAttribute('style'));

            if ($cleaned === '') {
                $element->removeAttribute('style');
            } else {
                $element->setAttribute('style', $cleaned);
            }

            if (
                strtolower($element->tagName) === 'span'
                && ! $element->hasAttribute('style')
                && ! $element->hasAttribute('class')
                && $element->attributes->length === 0
            ) {
                $this->replaceElementWithHtml($document, $element, $this->elementInnerHtml($element));
            }
        }
    }

    private function removeDefaultCanvasDeclarations(string $style): string
    {
        $declarations = array_values(array_filter(array_map('trim', explode(';', $style))));
        $kept = [];

        foreach ($declarations as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = strtolower(trim($property));
            $value = strtolower(trim($value));

            if ($property === 'color' && $this->isDefaultCanvasColor($value)) {
                continue;
            }

            if (in_array($property, ['background', 'background-color'], true) && $this->isDefaultCanvasBackground($value)) {
                continue;
            }

            $kept[] = $declaration;
        }

        return implode('; ', $kept);
    }

    private function stripLayoutBreakingStyles(DOMDocument $document, DOMXPath $xpath): void
    {
        foreach ($xpath->query('//*[@style]') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            $cleaned = $this->removeLayoutBreakingDeclarations(
                $element->getAttribute('style'),
                strtolower($element->tagName),
            );

            if ($cleaned === '') {
                $element->removeAttribute('style');
            } else {
                $element->setAttribute('style', $cleaned);
            }
        }
    }

    private function removeLayoutBreakingDeclarations(string $style, string $tag): string
    {
        $declarations = array_values(array_filter(array_map('trim', explode(';', $style))));
        $kept = [];

        foreach ($declarations as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = strtolower(trim($property));
            $value = strtolower(trim(str_replace(' ', '', $value)));

            if ($property === 'white-space' && in_array($value, ['nowrap', 'pre'], true) && $tag !== 'pre') {
                continue;
            }

            if ($property === 'overflow' && $tag !== 'pre') {
                continue;
            }

            if ($property === 'display' && $value === 'inline-block' && in_array($tag, ['div', 'p', 'section', 'article', 'aside', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'dd', 'dt'], true)) {
                continue;
            }

            if (in_array($property, ['width', 'min-width', 'max-width'], true) && in_array($tag, ['div', 'p', 'span', 'section', 'article', 'aside', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'dd', 'dt', 'td', 'th'], true)) {
                continue;
            }

            if (in_array($property, ['margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'box-sizing', 'border-width', 'border-style', 'border-color', 'unicode-bidi'], true)) {
                continue;
            }

            $kept[] = $declaration;
        }

        return implode('; ', $kept);
    }

    private function isDefaultCanvasColor(string $value): bool
    {
        $value = str_replace(' ', '', $value);

        return in_array($value, ['#222', '#222222', 'rgb(34,34,34)', 'rgb(34,34,34,1)', '#222222ff'], true);
    }

    private function isDefaultCanvasBackground(string $value): bool
    {
        $value = str_replace(' ', '', $value);

        return in_array($value, ['#fff', '#ffffff', 'white', 'rgb(255,255,255)', 'rgb(255,255,255,1)', '#ffffffff'], true);
    }

    private function isSeparatorText(string $text): bool
    {
        return preg_match('/^[\s|\\/,;·•\-–—]*$/u', trim($text)) === 1;
    }

    private function nodeDepth(DOMElement $element): int
    {
        $depth = 0;
        $current = $element->parentNode;

        while ($current !== null) {
            $depth++;
            $current = $current->parentNode;
        }

        return $depth;
    }

    private function unwrapHtmlInlines(DOMDocument $document, DOMXPath $xpath): void
    {
        foreach ($xpath->query('//span[@data-raw-html]') ?: [] as $inline) {
            if (! $inline instanceof DOMElement) {
                continue;
            }

            $rawHtml = $inline->getAttribute('data-raw-html') ?: $this->elementInnerHtml($inline);

            if ($rawHtml === '') {
                continue;
            }

            $this->replaceElementWithHtml($document, $inline, $rawHtml);
        }
    }

    private function unwrapHtmlBlocks(DOMDocument $document, DOMXPath $xpath): void
    {
        foreach ($xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " ql-html-block ")]') ?: [] as $block) {
            if (! $block instanceof DOMElement) {
                continue;
            }

            $rawHtml = $block->getAttribute('data-raw-html') ?: $this->elementInnerHtml($block);

            if ($rawHtml === '') {
                continue;
            }

            $this->replaceElementWithHtml($document, $block, $rawHtml);
        }
    }

    private function normalizeQuillLists(DOMDocument $document, DOMXPath $xpath): void
    {
        $listContainers = $xpath->query('//ol[li[@data-list]]') ?: [];

        foreach ($listContainers as $listContainer) {
            if (! $listContainer instanceof DOMElement) {
                continue;
            }

            $items = [];

            foreach ($listContainer->childNodes as $child) {
                if (! $child instanceof DOMElement || $child->tagName !== 'li') {
                    continue;
                }

                $items[] = [
                    'html' => $this->listItemInnerHtml($child),
                    'indent' => $this->quillIndentLevel($child),
                    'type' => $child->getAttribute('data-list') ?: 'bullet',
                ];
            }

            if ($items === []) {
                continue;
            }

            $this->replaceElementWithHtml($document, $listContainer, $this->buildListHtml($items));
        }
    }

    /**
     * @param  array<int, array{html: string, indent: int, type: string}>  $items
     */
    private function buildListHtml(array $items): string
    {
        return $this->convertListHtml($items, -1, []);
    }

    /**
     * @param  array<int, array{html: string, indent: int, type: string}>  $items
     * @param  array<int, string>  $types
     */
    private function convertListHtml(array $items, int $lastIndent, array $types): string
    {
        if ($items === []) {
            $endTag = $this->listTagForType(array_pop($types) ?? 'bullet')[0];

            if ($lastIndent <= 0) {
                return "</li></{$endTag}>";
            }

            return "</li></{$endTag}>".$this->convertListHtml([], $lastIndent - 1, $types);
        }

        $current = array_shift($items);
        [$tag, $attribute] = $this->listTagForType($current['type']);

        if ($current['indent'] > $lastIndent) {
            $types[] = $current['type'];

            if ($current['indent'] === $lastIndent + 1) {
                return "<{$tag}><li{$attribute}>{$current['html']}".$this->convertListHtml($items, $current['indent'], $types);
            }

            return "<{$tag}><li>".$this->convertListHtml([$current, ...$items], $lastIndent + 1, $types);
        }

        $previousType = $types[array_key_last($types)] ?? null;

        if ($current['indent'] === $lastIndent && $current['type'] === $previousType) {
            return "</li><li{$attribute}>{$current['html']}".$this->convertListHtml($items, $current['indent'], $types);
        }

        $endTag = $this->listTagForType(array_pop($types) ?? 'bullet')[0];

        return "</li></{$endTag}>".$this->convertListHtml([$current, ...$items], $lastIndent - 1, $types);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function listTagForType(string $type): array
    {
        $tag = $type === 'ordered' ? 'ol' : 'ul';

        $attribute = match ($type) {
            'checked' => ' data-list="checked"',
            'unchecked' => ' data-list="unchecked"',
            default => '',
        };

        return [$tag, $attribute];
    }

    private function quillIndentLevel(DOMElement $element): int
    {
        $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];

        foreach ($classes as $class) {
            if (preg_match('/^ql-indent-(\d+)$/', $class, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function listItemInnerHtml(DOMElement $element): string
    {
        $document = $element->ownerDocument;

        if ($document === null) {
            return '';
        }

        $clone = $element->cloneNode(true);

        if (! $clone instanceof DOMElement) {
            return '';
        }

        $clone->removeAttribute('data-list');
        $this->removeQuillClasses($clone);

        $uiSpans = [];

        foreach ($clone->getElementsByTagName('span') as $span) {
            if ($span instanceof DOMElement && $this->hasClass($span, 'ql-ui')) {
                $uiSpans[] = $span;
            }
        }

        foreach ($uiSpans as $span) {
            $span->parentNode?->removeChild($span);
        }

        $html = '';

        foreach ($clone->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }

    private function elementInnerHtml(DOMElement $element): string
    {
        $document = $element->ownerDocument;

        if ($document === null) {
            return '';
        }

        $html = '';

        foreach ($element->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }

    private function replaceElementWithHtml(DOMDocument $document, DOMElement $element, string $html): void
    {
        $fragment = $document->createDocumentFragment();
        $temporary = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $temporary->loadHTML(
            '<?xml encoding="UTF-8"><div id="replacement-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $temporary->getElementById('replacement-root');

        if (! $root instanceof DOMElement) {
            return;
        }

        foreach ($root->childNodes as $child) {
            $fragment->appendChild($document->importNode($child, true));
        }

        $element->parentNode?->replaceChild($fragment, $element);
    }

    private function applyAlignmentStyles(DOMElement $element): void
    {
        foreach (self::ALIGNMENT_CLASSES as $class => $style) {
            if (! $this->hasClass($element, $class)) {
                continue;
            }

            $this->appendStyle($element, $style);
        }
    }

    private function applyIndentStyles(DOMElement $element): void
    {
        $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];

        foreach ($classes as $class) {
            if (! preg_match('/^ql-indent-(\d+)$/', $class, $matches)) {
                continue;
            }

            $level = (int) $matches[1];
            $this->appendStyle($element, 'padding-left:'.($level * 3).'em');
        }
    }

    private function applySizeStyles(DOMElement $element): void
    {
        foreach (self::SIZE_CLASSES as $class => $style) {
            if (! $this->hasClass($element, $class)) {
                continue;
            }

            $this->appendStyle($element, $style);
        }

        $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];

        foreach ($classes as $class) {
            if (isset(self::SIZE_CLASSES[$class]) || ! preg_match('/^ql-size-(.+)$/', $class, $matches)) {
                continue;
            }

            $this->appendStyle($element, 'font-size:'.$this->cssIdentifier($matches[1]));
        }
    }

    private function applyFontStyles(DOMElement $element): void
    {
        foreach (self::FONT_CLASSES as $class => $style) {
            if (! $this->hasClass($element, $class)) {
                continue;
            }

            $this->appendStyle($element, $style);
        }

        $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];

        foreach ($classes as $class) {
            if (isset(self::FONT_CLASSES[$class]) || ! preg_match('/^ql-font-(.+)$/', $class, $matches)) {
                continue;
            }

            $this->appendStyle($element, 'font-family:'.$this->cssIdentifier($matches[1]));
        }
    }

    private function applyColorAndBackgroundStyles(DOMElement $element): void
    {
        $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];

        foreach ($classes as $class) {
            if (preg_match('/^ql-color-(.+)$/', $class, $matches)) {
                $this->appendStyle($element, 'color:'.$this->normalizeCssColor($matches[1]));
            }

            if (preg_match('/^ql-bg-(.+)$/', $class, $matches)) {
                $this->appendStyle($element, 'background-color:'.$this->normalizeCssColor($matches[1]));
            }
        }
    }

    private function applyDirectionStyles(DOMElement $element): void
    {
        if (! $this->hasClass($element, 'ql-direction-rtl')) {
            return;
        }

        $this->appendStyle($element, 'direction:rtl');
    }

    private function normalizeCssColor(string $value): string
    {
        $value = trim($value);

        if ($value === '' || str_starts_with($value, '#') || str_contains($value, '(')) {
            return $value;
        }

        if (preg_match('/^[0-9a-fA-F]{3,8}$/', $value) === 1) {
            return '#'.$value;
        }

        return $value;
    }

    private function cssIdentifier(string $value): string
    {
        return str_replace(['_', '+'], [' ', ' '], trim($value));
    }

    private function removeQuillClasses(DOMElement $element): void
    {
        $classes = array_values(array_filter(
            preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [],
            fn (string $class): bool => ! str_starts_with($class, 'ql-'),
        ));

        if ($classes === []) {
            $element->removeAttribute('class');
        } else {
            $element->setAttribute('class', implode(' ', $classes));
        }
    }

    private function hasClass(DOMElement $element, string $class): bool
    {
        return in_array($class, preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [], true);
    }

    private function embedLocalImage(DOMElement $element): void
    {
        $encoded = $this->imageEncoder->encodeFromUrl($element->getAttribute('src'));

        if ($encoded === null) {
            return;
        }

        $element->setAttribute(
            'src',
            'data:'.$encoded['mime'].';base64,'.$encoded['base64'],
        );
    }

    private function appendStyle(DOMElement $element, string $style): void
    {
        $existing = trim($element->getAttribute('style'));

        if ($existing !== '' && ! str_ends_with($existing, ';')) {
            $existing .= ';';
        }

        $element->setAttribute('style', trim($existing.' '.$style));
    }
}
