<?php

namespace Bareapi\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController
{
    public function __construct(private string $projectDir)
    {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function __invoke(): Response
    {
        $schemaDir = $this->projectDir . '/config/schemas';
        $files = glob($schemaDir . '/*.json') ?: [];
        $types = array_map(fn(string $f): string => basename($f, '.json'), $files);

        $html = '<!DOCTYPE html>';
        $html .= '<html lang="en"><head><meta charset="utf-8"><title>BareAPI</title></head><body>';
        $html .= '<h1>BareAPI</h1>';
        $html .= '<h2>Available Schemas</h2><ul>';
        foreach ($types as $type) {
            $html .= sprintf('<li><a href="/data/%s">%s</a></li>', htmlspecialchars($type), htmlspecialchars($type));
        }
        $html .= '</ul>';

        $html .= '<h2>Generic CRUD Endpoints</h2><ul>';
        $html .= '<li>GET    /data/{type}</li>';
        $html .= '<li>POST   /data/{type}</li>';
        $html .= '<li>GET    /data/{type}/{id}</li>';
        $html .= '<li>PUT    /data/{type}/{id}</li>';
        $html .= '<li>DELETE /data/{type}/{id}</li>';
        $html .= '</ul>';

        $html .= '<p>You can also apply simple filtering on the collection endpoint via query parameters, e.g. <code>?field=value</code>.</p>';
        $html .= '</body></html>';

        return new Response($html);
    }
}
