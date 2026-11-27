{{--
    Blackjack Salon surface.

    Thin theme wrapper around `internal-blackjack-shell`.
    View name preserved so existing controller + surface tests bind to
    `game-provider-front.internal-blackjack-salon`.

    Salon = deeper jewel-tone felt, copper-rose accent, brushed-metal VIP feel.
    Visually distinct from Classic, not just relabeled.
--}}
@include('game-provider-front.internal-blackjack-shell', [
    'payload' => $payload,
    'session' => $session,
    'bjTheme' => 'salon',
    'bjGameLabel' => $payload['gameName'] ?? 'Salon Privé',
    'bjGameTagline' => trans('cms_public.games.blackjack.tagline_salon'),
    'bjThemeTokens' => [
        '--bj-felt-1' => '#143e4a',
        '--bj-felt-2' => '#06222c',
        '--bj-felt-edge' => '#031018',
        '--bj-felt-pattern' => 'rgba(229, 184, 122, 0.045)',
        '--bj-accent' => '#e5b87a',
        '--bj-accent-warm' => '#c98058',
        '--bj-accent-soft' => 'rgba(229, 184, 122, 0.20)',
        '--bj-accent-glow' => 'rgba(229, 184, 122, 0.34)',
        '--bj-chip-rim' => '#ead7a8',
        '--bj-crest-line' => 'rgba(229, 184, 122, 0.32)',
    ],
])
