<?php

declare(strict_types=1);

namespace Bareapi\Controller;

use Bareapi\Service\SchemaServiceInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController
{
    public function __construct(
        private SchemaServiceInterface $schemaService,
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function __invoke(): Response
    {
        $types = $this->schemaService->listObjectTypes();

        $html = '<!DOCTYPE html>';
        $html .= '<html lang="en"><head><meta charset="utf-8"><title>Metastore API</title>';
        $html .= '<style>body{font-family:system-ui,sans-serif;max-width:800px;margin:2rem auto;padding:0 1rem}';
        $html .= 'code{background:#f4f4f4;padding:2px 6px;border-radius:3px}</style></head><body>';
        $html .= '<h1>Metastore API</h1>';

        if ($types !== []) {
            $html .= '<h2>Available Object Types</h2><ul>';
            foreach ($types as $type) {
                $html .= sprintf(
                    '<li><a href="/api/v1/schema/%s">%s</a></li>',
                    htmlspecialchars($type),
                    htmlspecialchars($type)
                );
            }
            $html .= '</ul>';
        } else {
            $html .= '<p>No schemas registered yet. Use the schema import command to load schemas.</p>';
        }

        $html .= '<h2>API Endpoints</h2>';

        $html .= '<h3>Repository (Authenticated)</h3><ul>';
        $html .= '<li><code>GET    /api/v1/repository/{objectType}</code> - List objects</li>';
        $html .= '<li><code>POST   /api/v1/repository/{objectType}</code> - Create object</li>';
        $html .= '<li><code>GET    /api/v1/repository/{objectType}/{uuid}</code> - Get object</li>';
        $html .= '<li><code>PATCH  /api/v1/repository/{objectType}/{uuid}</code> - Partial update</li>';
        $html .= '<li><code>PUT    /api/v1/repository/{objectType}/{uuid}</code> - Full replacement</li>';
        $html .= '<li><code>DELETE /api/v1/repository/{objectType}/{uuid}</code> - Soft delete</li>';
        $html .= '</ul>';

        $html .= '<h3>Revisions</h3><ul>';
        $html .= '<li><code>GET    /api/v1/repository/{objectType}/revisions</code> - List revisions</li>';
        $html .= '<li><code>GET    /api/v1/repository/{objectType}/{uuid}/revisions/{revision}</code> - Get revision</li>';
        $html .= '<li><code>DELETE /api/v1/repository/{objectType}/{uuid}/revisions/{revision}</code> - Delete revision</li>';
        $html .= '</ul>';

        $html .= '<h3>Schema (Public)</h3><ul>';
        $html .= '<li><code>GET    /api/v1/schema</code> - List object types</li>';
        $html .= '<li><code>GET    /api/v1/schema/{objectType}</code> - Get default schema</li>';
        $html .= '<li><code>GET    /api/v1/schema/{objectType}/{version}</code> - Get specific version</li>';
        $html .= '</ul>';

        $html .= '<h3>Health Check (Public)</h3><ul>';
        $html .= '<li><code>GET    /health-check</code> - Overall health status</li>';
        $html .= '<li><code>GET    /health-check/liveness</code> - Liveness probe</li>';
        $html .= '<li><code>GET    /health-check/readiness</code> - Readiness probe</li>';
        $html .= '</ul>';

        $html .= '<h2>Authentication</h2>';
        $html .= '<p>Repository endpoints require the <code>X-API-Key</code> header with a valid API key.</p>';

        $html .= '<h2>Headers</h2><ul>';
        $html .= '<li><code>X-API-Key</code> - API key for authentication (required for repository endpoints)</li>';
        $html .= '<li><code>X-Organization-ID</code> - Organization context</li>';
        $html .= '<li><code>X-Project-ID</code> - Project context (optional)</li>';
        $html .= '</ul>';

        $html .= '</body></html>';

        return new Response($html);
    }
}
