<?php

namespace App\Support;

use App\Models\Site;
use DOMDocument;
use DOMElement;
use DOMXPath;

class RemoteMediaUrlResolver
{
    /**
     * @var array<int, string>
     */
    private const URL_ATTRIBUTES = ['src', 'poster', 'href'];

    public function resolve(?string $url, Site $site): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if ($this->isAbsoluteUrl($url)) {
            return $url;
        }

        $origin = $site->remoteOrigin();

        if ($origin === null) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return $origin.$url;
        }

        return $origin.'/'.$url;
    }

    public function resolveInHtml(string $html, Site $site): string
    {
        $html = trim($html);

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

        foreach (self::URL_ATTRIBUTES as $attribute) {
            foreach ($xpath->query('//*[@'.$attribute.']') ?: [] as $element) {
                if (! $element instanceof DOMElement) {
                    continue;
                }

                $resolved = $this->resolve($element->getAttribute($attribute), $site);

                if ($resolved !== null) {
                    $element->setAttribute($attribute, $resolved);
                }
            }
        }

        $formatted = '';

        foreach ($root->childNodes as $child) {
            $formatted .= $document->saveHTML($child);
        }

        return $formatted;
    }

    public function resolveArticle(RemoteRecord $article, Site $site): RemoteRecord
    {
        $attributes = $article->attributes;

        $content = $article->string('content');

        if ($content !== null) {
            $attributes['content'] = $this->resolveInHtml($content, $site);
        }

        $featuredImageUrl = $article->string('featured_image_url');

        if ($featuredImageUrl !== null) {
            $attributes['featured_image_url'] = $this->resolve($featuredImageUrl, $site);
        }

        return new RemoteRecord($article->key, $attributes);
    }

    private function isAbsoluteUrl(string $url): bool
    {
        if (str_starts_with($url, 'data:')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, '#')) {
            return true;
        }

        return preg_match('#^https?://#i', $url) === 1
            || str_starts_with($url, '//');
    }
}
