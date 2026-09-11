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
            'integration-guide' => [
                'title' => 'Integration guide',
                'file' => 'integration-guide.md',
            ],
            'authentication' => [
                'title' => 'Authentication',
                'file' => 'authentication.md',
            ],
            'remote-site-receiver-api' => [
                'title' => 'Remote site receiver API',
                'file' => 'remote-site-receiver-api.md',
            ],
            'outbound-publishing-api' => [
                'title' => 'Outbound traffic from CMS',
                'file' => 'outbound-publishing-api.md',
            ],
            'id-mapping' => [
                'title' => 'Client ID mapping',
                'file' => 'id-mapping.md',
            ],
            'deployment' => [
                'title' => 'Deployment',
                'file' => 'deployment.md',
            ],
            'data-reference' => [
                'title' => 'Data reference',
                'file' => 'data-reference.md',
            ],
            // 'cms-internal-api' => [
            //     'title' => 'CMS internal API',
            //     'file' => 'cms-internal-api.md',
            // ],
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
