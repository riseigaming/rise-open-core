<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FrameAncestorsPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $ancestors = $this->allowedAncestors();

        if ($ancestors === []) {
            return $response;
        }

        $response->headers->set(
            'Content-Security-Policy',
            $this->replaceFrameAncestors((string) $response->headers->get('Content-Security-Policy', ''), $ancestors)
        );

        if ((bool) config('frame_ancestors.remove_x_frame_options', true)) {
            $response->headers->remove('X-Frame-Options');
        }

        return $response;
    }

    private function allowedAncestors(): array
    {
        if (!(bool) config('frame_ancestors.enabled', false)) {
            return [];
        }

        $ancestors = array_values(array_filter(array_map(
            fn (string $origin): ?string => $this->normalizeSource($origin),
            (array) config('frame_ancestors.ancestors', [])
        )));

        if ((bool) config('frame_ancestors.include_self', true)) {
            array_unshift($ancestors, "'self'");
        }

        return array_values(array_unique($ancestors));
    }

    private function normalizeSource(string $source): ?string
    {
        $source = trim($source);

        if ($source === '' || $source === '*') {
            return null;
        }

        if ($source === "'self'") {
            return $source;
        }

        if (!preg_match('#^https?://(?:\*\.)?[a-z0-9.-]+(?::\d{1,5})?$#i', $source)) {
            return null;
        }

        return strtolower($source);
    }

    private function replaceFrameAncestors(string $existingPolicy, array $ancestors): string
    {
        $directive = 'frame-ancestors ' . implode(' ', $ancestors);
        $parts = array_values(array_filter(array_map('trim', explode(';', $existingPolicy))));
        $replaced = false;

        foreach ($parts as $index => $part) {
            if (preg_match('/^frame-ancestors\b/i', $part)) {
                $parts[$index] = $directive;
                $replaced = true;
            }
        }

        if (!$replaced) {
            $parts[] = $directive;
        }

        return implode('; ', $parts);
    }
}
