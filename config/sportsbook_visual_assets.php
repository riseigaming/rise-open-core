<?php

return [
    'allowed_roots' => [
        '/assets/cms/img/sportsbook/',
        '/assets/cms/img/sportsbook-visual-assets/',
        '/assets/cms/img/sportsbook-catalog/',
        '/cms-assets/',
    ],

    'defaults' => [
        'color' => '#425466',
        'icon_key' => 'shield',
    ],

    'entity_defaults' => [
        'sport' => ['color' => '#0ea5e9', 'icon_key' => 'activity'],
        'region' => ['color' => '#64748b', 'icon_key' => 'globe'],
        'country' => ['color' => '#475569', 'icon_key' => 'flag'],
        'competition' => ['color' => '#2563eb', 'icon_key' => 'trophy'],
        'team' => ['color' => '#16a34a', 'icon_key' => 'shield'],
        'market_family' => ['color' => '#7c3aed', 'icon_key' => 'chart'],
    ],

    'sports' => [
        'soccer' => ['path' => '/assets/cms/img/sportsbook/sports/soccer.svg', 'status' => 'approved', 'placeholder_label' => 'FT', 'icon_key' => 'football', 'color' => '#0f9f6e', 'source_type' => 'original_internal_pictogram', 'source_reference' => 'sportsbook-phase-2b-seed-pack', 'license_status' => 'owned', 'reviewed_at' => '2026-07-28', 'notes' => 'Brand-free generic sport pictogram; not a league, team, federation, or competition logo.'],
        'basketball' => ['path' => '/assets/cms/img/sportsbook/sports/basketball.svg', 'status' => 'approved', 'placeholder_label' => 'BK', 'icon_key' => 'basketball', 'color' => '#d97706', 'source_type' => 'original_internal_pictogram', 'source_reference' => 'sportsbook-phase-2b-seed-pack', 'license_status' => 'owned', 'reviewed_at' => '2026-07-28', 'notes' => 'Brand-free generic sport pictogram; not a league, team, federation, or competition logo.'],
        'tennis' => ['path' => '/assets/cms/img/sportsbook/sports/tennis.svg', 'status' => 'approved', 'placeholder_label' => 'TN', 'icon_key' => 'tennis', 'color' => '#65a30d', 'source_type' => 'original_internal_pictogram', 'source_reference' => 'sportsbook-phase-2b-seed-pack', 'license_status' => 'owned', 'reviewed_at' => '2026-07-28', 'notes' => 'Brand-free generic sport pictogram; not a league, team, federation, or competition logo.'],
        'baseball' => ['path' => '/assets/cms/img/sportsbook/sports/baseball.svg', 'status' => 'approved', 'placeholder_label' => 'BB', 'icon_key' => 'baseball', 'color' => '#dc2626', 'source_type' => 'original_internal_pictogram', 'source_reference' => 'sportsbook-phase-2b-seed-pack', 'license_status' => 'owned', 'reviewed_at' => '2026-07-28', 'notes' => 'Brand-free generic sport pictogram; not a league, team, federation, or competition logo.'],
        'icehockey' => ['path' => '/assets/cms/img/sportsbook/sports/icehockey.svg', 'status' => 'approved', 'placeholder_label' => 'HK', 'icon_key' => 'hockey', 'color' => '#0284c7', 'source_type' => 'original_internal_pictogram', 'source_reference' => 'sportsbook-phase-2b-seed-pack', 'license_status' => 'owned', 'reviewed_at' => '2026-07-28', 'notes' => 'Brand-free generic sport pictogram; not a league, team, federation, or competition logo.'],
        'volleyball' => ['path' => '/assets/cms/img/sportsbook/sports/volleyball.svg', 'status' => 'approved', 'placeholder_label' => 'VB', 'icon_key' => 'volleyball', 'color' => '#ca8a04', 'source_type' => 'original_internal_pictogram', 'source_reference' => 'sportsbook-phase-2b-seed-pack', 'license_status' => 'owned', 'reviewed_at' => '2026-07-28', 'notes' => 'Brand-free generic sport pictogram; not a league, team, federation, or competition logo.'],
        'snooker' => ['path' => '/assets/cms/img/sportsbook/sports/snooker.svg', 'status' => 'approved', 'placeholder_label' => 'SN', 'icon_key' => 'circle', 'color' => '#047857', 'source_type' => 'original_internal_pictogram', 'source_reference' => 'sportsbook-phase-2b-seed-pack', 'license_status' => 'owned', 'reviewed_at' => '2026-07-28', 'notes' => 'Brand-free generic sport pictogram; not a league, team, federation, or competition logo.'],
        'table_tennis' => ['path' => '/assets/cms/img/sportsbook/sports/table_tennis.svg', 'status' => 'approved', 'placeholder_label' => 'TT', 'icon_key' => 'table-tennis', 'color' => '#0891b2', 'source_type' => 'original_internal_pictogram', 'source_reference' => 'sportsbook-phase-2b-seed-pack', 'license_status' => 'owned', 'reviewed_at' => '2026-07-28', 'notes' => 'Brand-free generic sport pictogram; not a league, team, federation, or competition logo.'],
        'dota2' => ['path' => '/assets/cms/img/sportsbook/sports/dota2.svg', 'status' => 'approved', 'placeholder_label' => 'D2', 'icon_key' => 'gamepad', 'color' => '#b91c1c', 'source_type' => 'original_internal_pictogram', 'source_reference' => 'sportsbook-phase-2b-seed-pack', 'license_status' => 'owned', 'reviewed_at' => '2026-07-28', 'notes' => 'Brand-free generic esport pictogram; not a publisher, team, federation, or competition logo.'],
        'cs2' => ['path' => '/assets/cms/img/sportsbook/sports/cs2.svg', 'status' => 'approved', 'placeholder_label' => 'CS', 'icon_key' => 'crosshair', 'color' => '#334155', 'source_type' => 'original_internal_pictogram', 'source_reference' => 'sportsbook-phase-2b-seed-pack', 'license_status' => 'owned', 'reviewed_at' => '2026-07-28', 'notes' => 'Brand-free generic esport pictogram; not a publisher, team, federation, or competition logo.'],
        'valorant' => ['placeholder_label' => 'VL', 'icon_key' => 'crosshair', 'color' => '#be123c'],
        'lol' => ['placeholder_label' => 'LOL', 'icon_key' => 'gamepad', 'color' => '#7c2d12'],
    ],

    'regions' => [],
    'countries' => [],
    'competitions' => [],
    'teams' => [],
    'market_families' => [],
];
