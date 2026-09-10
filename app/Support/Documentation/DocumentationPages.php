<?php

namespace App\Support\Documentation;

class DocumentationPages
{
    /**
     * @return array<string, array{title: string, file: string}>
     */
    public static function all(): array
    {
        return [
            'overview' => [
                'title' => 'Overview',
                'file' => 'README.md',
            ],
            'authentication' => [
                'title' => 'Authentication',
                'file' => 'authentication.md',
            ],
            // 'cms-internal-api' => [
            //     'title' => 'CMS internal API',
            //     'file' => 'cms-internal-api.md',
            // ],
            'outbound-publishing-api' => [
                'title' => 'Outbound publishing API',
                'file' => 'outbound-publishing-api.md',
            ],
            'remote-site-receiver-api' => [
                'title' => 'Remote site receiver API',
                'file' => 'remote-site-receiver-api.md',
            ],
            'data-reference' => [
                'title' => 'Data reference',
                'file' => 'data-reference.md',
            ],
        ];
    }

    /**
     * @return array{title: string, file: string}|null
     */
    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }
}
