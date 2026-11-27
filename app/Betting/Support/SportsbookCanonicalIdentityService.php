<?php

namespace App\Betting\Support;

use Illuminate\Support\Str;

class SportsbookCanonicalIdentityService
{
    public function __construct(private ?SportsbookCatalogService $catalog = null)
    {
        $this->catalog = $this->catalog ?: app(SportsbookCatalogService::class);
    }

    public function competition(string $sportCode, ?string $competitionName, array $context = []): array
    {
        $sportGroupKey = $this->sportGroupKey((string) ($context['sport_group_key'] ?? $sportCode));
        $rawLabel = trim((string) ($competitionName ?? ''));
        $countryKey = $this->countryKey((string) ($context['country'] ?? $context['country_key'] ?? ''));
        $aliasKey = $this->competitionAliasKey($sportGroupKey, $countryKey, $rawLabel);
        $definition = $aliasKey !== '' ? $this->competitionDefinitions()[$aliasKey] ?? null : null;

        if (is_array($definition)) {
            [$definitionSport, $definitionCountry, $definitionCompetition] = $this->splitScopedKey($aliasKey);
            $legacyKey = (string) ($definition['legacy_key'] ?? $this->legacyCompetitionKey($definitionCountry, $definitionCompetition));

            return [
                'canonical_key' => $aliasKey,
                'legacy_key' => $legacyKey,
                'sport_group_key' => $definitionSport !== '' ? $definitionSport : $sportGroupKey,
                'country_key' => $definitionCountry,
                'country_label' => (string) ($definition['country_label'] ?? $this->countryLabel($definitionCountry)),
                'competition_key' => $definitionCompetition,
                'label' => (string) ($definition['label'] ?? $rawLabel),
                'tier' => (string) ($definition['identity_tier'] ?? $definition['tier'] ?? ''),
                'catalog_tier' => (string) ($definition['tier'] ?? ''),
                'public_surface' => (string) ($definition['public_surface'] ?? ''),
                'known' => true,
                'source_label' => $rawLabel,
            ];
        }

        $fallbackKey = $this->normalizeKey($rawLabel);
        if ($fallbackKey === '') {
            $fallbackKey = $this->normalizeKey($sportCode) ?: 'unknown_competition';
        }

        $fallbackCountry = $countryKey !== '' ? $countryKey : $this->countryFromCompetitionKey($fallbackKey);
        $canonicalKey = implode(':', [
            $sportGroupKey !== '' ? $sportGroupKey : 'other',
            $fallbackCountry !== '' ? $fallbackCountry : 'unknown',
            $fallbackKey,
        ]);

        return [
            'canonical_key' => $canonicalKey,
            'legacy_key' => $fallbackKey,
            'sport_group_key' => $sportGroupKey !== '' ? $sportGroupKey : 'other',
            'country_key' => $fallbackCountry,
            'country_label' => $fallbackCountry !== '' ? $this->countryLabel($fallbackCountry) : '',
            'competition_key' => $fallbackKey,
            'label' => $rawLabel !== '' ? $rawLabel : $this->sportLabel($sportGroupKey),
            'tier' => '',
            'known' => false,
            'source_label' => $rawLabel,
        ];
    }

    public function team(string $sportCode, ?string $teamName, array $context = []): array
    {
        $sportGroupKey = $this->sportGroupKey((string) ($context['sport_group_key'] ?? $sportCode));
        $rawLabel = trim((string) ($teamName ?? ''));
        $countryKey = $this->countryKey((string) ($context['country'] ?? $context['country_key'] ?? ''));
        $aliasKey = $this->teamAliasKey($sportGroupKey, $countryKey, $rawLabel);
        $definition = $aliasKey !== '' ? $this->teamDefinitions()[$aliasKey] ?? null : null;

        if (is_array($definition)) {
            [$definitionSport, $definitionCountry, $definitionTeam] = $this->splitScopedKey($aliasKey);

            return [
                'canonical_key' => $aliasKey,
                'sport_group_key' => $definitionSport !== '' ? $definitionSport : $sportGroupKey,
                'country_key' => $definitionCountry,
                'team_key' => $definitionTeam,
                'label' => (string) ($definition['label'] ?? $rawLabel),
                'known' => true,
                'source_label' => $rawLabel,
            ];
        }

        $fallbackKey = $this->normalizeKey($rawLabel);
        if ($fallbackKey === '') {
            $fallbackKey = 'unknown_team';
        }

        return [
            'canonical_key' => implode(':', [
                $sportGroupKey !== '' ? $sportGroupKey : 'other',
                $countryKey !== '' ? $countryKey : 'unknown',
                $fallbackKey,
            ]),
            'sport_group_key' => $sportGroupKey !== '' ? $sportGroupKey : 'other',
            'country_key' => $countryKey,
            'team_key' => $fallbackKey,
            'label' => $rawLabel,
            'known' => false,
            'source_label' => $rawLabel,
        ];
    }

    private function competitionAliasKey(string $sportGroupKey, string $countryKey, string $rawLabel): string
    {
        return $this->lookupAlias($this->competitionAliasMap(), $sportGroupKey, $countryKey, $rawLabel);
    }

    private function teamAliasKey(string $sportGroupKey, string $countryKey, string $rawLabel): string
    {
        return $this->lookupAlias($this->teamAliasMap(), $sportGroupKey, $countryKey, $rawLabel);
    }

    private function lookupAlias(array $aliases, string $sportGroupKey, string $countryKey, string $rawLabel): string
    {
        $normalized = $this->normalizeKey($rawLabel);
        if ($normalized === '') {
            return '';
        }

        $candidates = array_values(array_filter([
            $sportGroupKey !== '' && $countryKey !== '' ? $sportGroupKey . ':' . $countryKey . ':' . $normalized : '',
            $sportGroupKey !== '' ? $sportGroupKey . ':' . $normalized : '',
            $normalized,
        ]));

        foreach ($candidates as $candidate) {
            if (isset($aliases[$candidate])) {
                return $aliases[$candidate];
            }
        }

        return '';
    }

    private function competitionAliasMap(): array
    {
        return $this->catalog->competitionAliasMap();
    }

    private function teamAliasMap(): array
    {
        return $this->catalog->teamAliasMap();
    }

    private function competitionDefinitions(): array
    {
        return $this->catalog->competitions();
    }

    private function teamDefinitions(): array
    {
        return $this->catalog->teams();
    }

    private function splitScopedKey(string $key): array
    {
        $parts = explode(':', $key);

        return [
            (string) ($parts[0] ?? ''),
            (string) ($parts[1] ?? ''),
            (string) ($parts[2] ?? ''),
        ];
    }

    private function legacyCompetitionKey(string $countryKey, string $competitionKey): string
    {
        if ($competitionKey === '') {
            return $countryKey;
        }

        if ($countryKey === '' || in_array($countryKey, ['fifa', 'uefa', 'conmebol'], true)) {
            return $competitionKey;
        }

        return $countryKey . '_' . $competitionKey;
    }

    private function countryFromCompetitionKey(string $competitionKey): string
    {
        $first = (string) Str::before($competitionKey, '_');

        return array_key_exists($first, $this->countryAliases()) ? (string) $this->countryAliases()[$first] : '';
    }

    private function countryKey(string $value): string
    {
        $normalized = $this->normalizeKey($value);
        if ($normalized === '') {
            return '';
        }

        return (string) ($this->countryAliases()[$normalized] ?? $normalized);
    }

    private function countryAliases(): array
    {
        return $this->catalog->countryAliases();
    }

    private function sportGroupKey(string $sportCode): string
    {
        $sportCode = trim($sportCode);

        return match (true) {
            str_starts_with($sportCode, 'soccer') => 'soccer',
            str_starts_with($sportCode, 'tennis') => 'tennis',
            str_starts_with($sportCode, 'basketball') => 'basketball',
            str_starts_with($sportCode, 'snooker') => 'snooker',
            str_contains($sportCode, 'table_tennis') => 'table_tennis',
            str_contains($sportCode, 'dota') => 'dota2',
            str_contains($sportCode, 'cs2') => 'cs2',
            str_starts_with($sportCode, 'volleyball') => 'volleyball',
            str_starts_with($sportCode, 'baseball') => 'baseball',
            str_starts_with($sportCode, 'icehockey') => 'icehockey',
            default => $this->normalizeKey($sportCode),
        };
    }

    private function sportLabel(string $sportGroupKey): string
    {
        return match ($sportGroupKey) {
            'soccer' => 'Futbol',
            'basketball' => 'Basketbol',
            'tennis' => 'Tenis',
            'snooker' => 'Snooker',
            'volleyball' => 'Voleybol',
            'baseball' => 'Beyzbol',
            'icehockey' => 'Buz Hokeyi',
            'table_tennis' => 'Masa Tenisi',
            'dota2' => 'Dota 2',
            'cs2' => 'CS2',
            default => 'Diğer',
        };
    }

    private function countryLabel(string $countryKey): string
    {
        return $this->catalog->countryLabel($countryKey);
    }

    private function normalizeKey(string $value): string
    {
        return trim(Str::slug(Str::ascii(trim($value)), '_'));
    }
}
