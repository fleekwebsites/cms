<?php

namespace App\Support\Documentation;

use Illuminate\Support\Str;
use RuntimeException;

class MarkdownDocument
{
    public function render(string $filename): string
    {
        $path = base_path('documentation/'.$filename);

        if (! is_file($path)) {
            throw new RuntimeException("Documentation file [{$filename}] was not found.");
        }

        $markdown = file_get_contents($path);

        if ($markdown === false) {
            throw new RuntimeException("Documentation file [{$filename}] could not be read.");
        }

        return Str::markdown($this->rewriteLinks($markdown));
    }

    private function rewriteLinks(string $markdown): string
    {
        return (string) preg_replace_callback(
            '/\]\(([^)]+\.md)(#[^)]*)?\)/',
            function (array $matches): string {
                $slug = str_replace('.md', '', basename($matches[1]));
                $fragment = $matches[2] ?? '';

                if ($slug === 'README') {
                    $url = route('documentation.index');
                } else {
                    $url = route('documentation.show', $slug);
                }

                return ']('.$url.$fragment.')';
            },
            $markdown,
        );
    }
}
