<?php

namespace App\Betting\Support;

use Illuminate\Support\Str;

class SportsbookAssetResolver
{
    private const VALID_STATUSES = ['approved', 'verified', 'needs_review', 'placeholder', 'missing'];

    public function sport(string $sportGroupKey, ?string $label = null): array
    {
        return $this->resolve('sport', $sportGroupKey, $label ?: $sportGroupKey);
    }

    public function region(string $regionKey, ?string $label = null): array
    {
        return $this->resolve('region', $regionKey, $label ?: $regionKey);
    }

    public function country(string $countryKey, ?string $label = null): array
    {
        return $this->resolve('country', $countryKey, $label ?: $countryKey);
    }

    public function organization(string $canonicalKey, ?string $label = null): array
    {
        return $this->resolve('organization', $canonicalKey, $label ?: $canonicalKey);
    }

    public function competition(string $canonicalKey, array $definition = []): array
    {
        return $this->resolve(
            'competition',
            $canonicalKey,
            (string) ($definition['label'] ?? $definition['short_label'] ?? $canonicalKey),
            (array) ($definition['asset_meta'] ?? [])
        );
    }

    public function team(string $canonicalKey, array $definition = []): array
    {
        return $this->resolve(
            'team',
            $canonicalKey,
            (string) ($definition['label'] ?? $definition['short_label'] ?? $canonicalKey),
            (array) ($definition['asset_meta'] ?? [])
        );
    }

    public function marketFamily(string $sportGroupKey, string $marketType, ?string $label = null): array
    {
        $key = trim($sportGroupKey . ':' . $marketType, ':');

        return $this->resolve('market_family', $key, $label ?: $marketType);
    }

    public function resolve(string $entityType, string $canonicalKey, string $label, array $overlay = []): array
    {
        $entityType = $this->normalizeEntityType($entityType);
        $canonicalKey = trim($canonicalKey);
        $registry = $this->registryOverlay($entityType, $canonicalKey);

        return $this->normalize(array_replace($registry, $overlay), $entityType, $canonicalKey, $label);
    }

    public function normalize(array $asset, string $type, string $key, string $label): array
    {
        $type = $this->normalizeEntityType($type);
        $key = trim($key);
        $label = trim($label) !== '' ? trim($label) : $key;
        $safePath = $this->safeLocalPublicPath((string) ($asset['path'] ?? $asset['official_logo_path'] ?? ''));
        $status = $this->normalizeStatus((string) ($asset['status'] ?? $asset['asset_status'] ?? 'placeholder'), $safePath !== null);
        $entityDefaults = (array) config('sportsbook_visual_assets.entity_defaults.' . $type, []);
        $globalDefaults = (array) config('sportsbook_visual_assets.defaults', []);
        $placeholderLabel = $this->placeholderLabel((string) ($asset['placeholder_label'] ?? ''), $label, $key);
        $color = $this->normalizeColor((string) ($asset['color'] ?? $entityDefaults['color'] ?? $globalDefaults['color'] ?? '#425466'));
        $iconKey = $this->normalizeIconKey((string) ($asset['icon_key'] ?? $entityDefaults['icon_key'] ?? $globalDefaults['icon_key'] ?? 'shield'));

        $descriptor = [
            'canonical_key' => $key,
            'entity_type' => $type,
            'status' => $safePath !== null ? $status : 'placeholder',
            'path' => $safePath,
            'placeholder' => $safePath === null,
            'placeholder_label' => $placeholderLabel,
            'icon_key' => $iconKey,
            'color' => $color,
            'asset_status' => $safePath !== null ? $status : 'placeholder',
            'official_logo_path' => $safePath,
        ];

        foreach (['flag_code', 'source_url', 'license_note', 'source_type', 'source_reference', 'license_status', 'reviewed_at', 'notes'] as $metadataKey) {
            if (array_key_exists($metadataKey, $asset)) {
                $descriptor[$metadataKey] = $asset[$metadataKey];
            }
        }

        return $descriptor;
    }

    private function registryOverlay(string $entityType, string $canonicalKey): array
    {
        $registryKey = match ($entityType) {
            'sport' => 'sports',
            'region' => 'regions',
            'country' => 'countries',
            'organization' => 'organizations',
            'competition' => 'competitions',
            'team' => 'teams',
            'market_family' => 'market_families',
            default => '',
        };

        if ($registryKey === '' || $canonicalKey === '') {
            return [];
        }

        return (array) config('sportsbook_visual_assets.' . $registryKey . '.' . $canonicalKey, []);
    }

    private function safeLocalPublicPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path) || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path)) {
            return null;
        }

        if (!str_starts_with($path, '/') || str_contains($path, '\\')) {
            return null;
        }

        $segments = explode('/', $path);
        if (in_array('..', $segments, true)) {
            return null;
        }

        $allowedRoots = array_values(array_filter((array) config('sportsbook_visual_assets.allowed_roots', [])));
        $underAllowedRoot = collect($allowedRoots)->contains(fn (string $root): bool => str_starts_with($path, $root));
        if (!$underAllowedRoot) {
            return null;
        }

        $publicPath = public_path(ltrim($path, '/'));

        if (!is_file($publicPath)) {
            return null;
        }

        if (Str::lower(pathinfo($publicPath, PATHINFO_EXTENSION)) === 'svg' && !$this->safeSvgFile($publicPath)) {
            return null;
        }

        return $path;
    }

    private function safeSvgFile(string $publicPath): bool
    {
        $contents = file_get_contents($publicPath);
        if (!is_string($contents) || trim($contents) === '') {
            return false;
        }

        if (!preg_match('/<svg\b[^>]*\bviewBox\s*=/i', $contents)) {
            return false;
        }

        return !preg_match('/<\s*(script|foreignObject)\b|<[^>]+\son[a-z]+\s*=|(?:href|src|xlink:href)\s*=\s*["\']\s*(?:https?:|\/\/|javascript:)/i', $contents);
    }

    private function normalizeStatus(string $status, bool $hasPath): string
    {
        $status = trim(Str::lower($status));
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return $hasPath ? 'needs_review' : 'placeholder';
        }

        return $status;
    }

    private function normalizeEntityType(string $type): string
    {
        $type = trim(Str::lower($type));

        return in_array($type, ['sport', 'region', 'country', 'organization', 'competition', 'team', 'market_family'], true)
            ? $type
            : 'competition';
    }

    private function placeholderLabel(string $explicitLabel, string $label, string $key): string
    {
        $explicitLabel = trim($explicitLabel);
        if ($explicitLabel !== '') {
            return mb_strtoupper(mb_substr($explicitLabel, 0, 4));
        }

        $source = trim($label) !== '' ? $label : $key;
        $words = collect(preg_split('/[\s\-_:.]+/u', $source) ?: [])
            ->filter(fn (string $word): bool => trim($word) !== '')
            ->values();

        $letters = $words
            ->map(fn (string $word): string => mb_substr($word, 0, 1))
            ->implode('');

        if ($letters === '') {
            $letters = preg_replace('/[^A-Za-z0-9]+/', '', $source) ?: 'SB';
        }

        return mb_strtoupper(mb_substr($letters, 0, 3));
    }

    private function normalizeColor(string $color): string
    {
        $color = trim($color);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#425466';
    }

    private function normalizeIconKey(string $iconKey): string
    {
        $iconKey = trim(Str::slug($iconKey, '-'));

        return $iconKey !== '' ? $iconKey : 'shield';
    }
}
