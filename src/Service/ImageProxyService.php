<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ImageProxyService
{
    public function __construct(private string $projectDir) {}

    public function serve(string $path, string $title = 'Image', string $type = 'apartment'): Response
    {
        $fullPath = $this->projectDir . '/public/uploads/' . ltrim($path, '/');

        if (file_exists($fullPath) && is_readable($fullPath)) {
            $response = new BinaryFileResponse($fullPath);
            $response->setPublic();
            $response->setMaxAge(86400);
            $response->headers->set('Cache-Control', 'public, max-age=86400');
            return $response;
        }

        // Return SVG placeholder
        $colors = [
            'apartment'   => '#6C5CE7',
            'house'       => '#00CEC9',
            'studio'      => '#FD79A8',
            'shared_room' => '#FDCB6E',
        ];
        $color = $colors[$type] ?? '#6C5CE7';
        $label = htmlspecialchars($title);

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300">
  <rect width="400" height="300" fill="{$color}" opacity="0.15"/>
  <rect width="400" height="300" fill="none" stroke="{$color}" stroke-width="2"/>
  <text x="200" y="140" font-family="Inter,sans-serif" font-size="18" fill="{$color}" text-anchor="middle" font-weight="600">{$label}</text>
  <text x="200" y="170" font-family="Inter,sans-serif" font-size="13" fill="{$color}" text-anchor="middle" opacity="0.7">{$type}</text>
</svg>
SVG;

        return new Response($svg, 200, [
            'Content-Type'  => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
