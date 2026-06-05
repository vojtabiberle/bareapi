<?php

declare(strict_types=1);

namespace Bareapi\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController
{
    public function __construct(
        private string $projectDir
    ) {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function __invoke(): Response
    {
        $schemaDir = $this->projectDir . '/config/schemas';
        $files = glob($schemaDir . '/*.json') ?: [];
        $types = array_map(fn (string $f): string => basename($f, '.json'), $files);

        $html = '<!DOCTYPE html>';
        $html .= '<html lang="en"><head><meta charset="utf-8"><title>BareAPI</title></head><body>';
        $html .= '<h1>BareAPI</h1>';
        $html .= '<h2>Available Schemas</h2><ul>';
        foreach ($types as $type) {
            $html .= sprintf('<li><a href="/api/%s">%s</a></li>', htmlspecialchars($type), htmlspecialchars($type));
        }
        $html .= '</ul>';

        $html .= '<h2>Repository API</h2><ul>';
        $html .= '<li>GET    /api/v1/repository/{objectType}</li>';
        $html .= '<li>POST   /api/v1/repository/{objectType}</li>';
        $html .= '<li>GET    /api/v1/repository/{objectType}/{id}</li>';
        $html .= '<li>PATCH  /api/v1/repository/{objectType}/{id}</li>';
        $html .= '<li>PUT    /api/v1/repository/{objectType}/{id}</li>';
        $html .= '<li>DELETE /api/v1/repository/{objectType}/{id}</li>';
        $html .= '<li>GET    /api/v1/schema/{objectType}</li>';
        $html .= '<li>GET    /api/v1/schema/{objectType}/{version}</li>';
        $html .= '<li>GET    /health-check</li>';
        $html .= '<li>GET    /api/v1/documentation/openapi.json</li>';
        $html .= '</ul>';

        $html .= '<h2>Legacy CRUD Endpoints</h2><ul>';
        $html .= '<li>GET    /api/{type}</li>';
        $html .= '<li>POST   /api/{type}</li>';
        $html .= '<li>GET    /api/{type}/{id}</li>';
        $html .= '<li>PUT    /api/{type}/{id}</li>';
        $html .= '<li>DELETE /api/{type}/{id}</li>';
        $html .= '</ul>';

        $html .= '<p>You can also apply simple filtering on the collection endpoint via query parameters, e.g. <code>?field=value</code>.</p>';
        $html .= '</body></html>';

        return new Response($html);
    }
}
