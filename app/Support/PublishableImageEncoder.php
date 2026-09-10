<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublishableImageEncoder
{
    /**
     * @return array{featured_image_base64: string, featured_image_mime: string, featured_image_filename: string}|null
     */
    public function encodeFeaturedImage(?string $url): ?array
    {
        $encoded = $this->encodeFromUrl($url);

        if ($encoded === null) {
            return null;
        }

        return [
            'featured_image_base64' => $encoded['base64'],
            'featured_image_mime' => $encoded['mime'],
            'featured_image_filename' => $encoded['filename'],
        ];
    }

    /**
     * @return array{base64: string, mime: string, filename: string}|null
     */
    public function encodeFromUrl(?string $url): ?array
    {
        if (! filled($url)) {
            return null;
        }

        $path = $this->resolveLocalPath($url);

        if ($path === null || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return [
            'base64' => base64_encode($contents),
            'mime' => $mime,
            'filename' => basename($path),
        ];
    }

    public function resolveLocalPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (preg_match('#/storage/(.+)$#', $path, $matches) === 1) {
            $relativePath = $matches[1];

            if (Storage::disk('public')->exists($relativePath)) {
                return Storage::disk('public')->path($relativePath);
            }
        }

        $storagePath = Str::after($url, '/storage/');

        if ($storagePath !== $url && Storage::disk('public')->exists($storagePath)) {
            return Storage::disk('public')->path($storagePath);
        }

        return null;
    }
}
