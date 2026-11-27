<?php

namespace App\Betting\Support;

class BettingPublicSurfaceTierService
{
    private const PREMIUM_TIERS = ['S', 'A', 'B'];
    private const SURFACE_RANKS = [
        'premium_surface' => 0,
        'standard_surface' => 1,
        'secondary_surface' => 2,
        'long_tail_surface' => 3,
    ];

    public function __construct(private ?BettingCompetitionPriorityService $competitionPriority = null)
    {
        $this->competitionPriority = $this->competitionPriority ?: app(BettingCompetitionPriorityService::class);
    }

    public function describe(array $subject): array
    {
        $resolved = $this->competitionPriority->resolveForEvent($subject);
        $sportGroupKey = (string) data_get($resolved, 'sport_group_key', data_get($subject, 'sport_group_key', 'other'));
        $known = (bool) data_get($resolved, 'competition_identity_known', false);
        $tier = (string) data_get($resolved, 'competition_identity_tier', data_get($subject, 'competition_identity_tier', ''));
        $catalogSurface = (string) data_get($resolved, 'competition_public_surface', data_get($subject, 'competition_public_surface', ''));

        if (isset(self::SURFACE_RANKS[$catalogSurface])) {
            $surface = $catalogSurface;
        } elseif ($sportGroupKey !== 'soccer') {
            $surface = 'standard_surface';
        } elseif (!$known) {
            $surface = 'long_tail_surface';
        } elseif (in_array($tier, self::PREMIUM_TIERS, true)) {
            $surface = 'premium_surface';
        } else {
            $surface = 'standard_surface';
        }

        return [
            'public_surface_tier' => $surface,
            'public_surface_rank' => $this->rank($surface),
            'competition_identity_known' => $known,
            'competition_identity_tier' => $tier,
            'competition_catalog_tier' => (string) data_get($resolved, 'competition_catalog_tier', ''),
            'canonical_competition_key' => (string) data_get($resolved, 'canonical_competition_key', ''),
        ];
    }

    public function tier(array $subject): string
    {
        return (string) $this->describe($subject)['public_surface_tier'];
    }

    public function rank(string $surface): int
    {
        return self::SURFACE_RANKS[$surface] ?? self::SURFACE_RANKS['long_tail_surface'];
    }

    public function isPremium(array $subject): bool
    {
        return $this->tier($subject) === 'premium_surface';
    }

    public function isLongTail(array $subject): bool
    {
        return $this->tier($subject) === 'long_tail_surface';
    }
}
