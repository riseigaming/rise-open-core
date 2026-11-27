{{--
    Blackjack Classic surface.

    Thin theme wrapper around `internal-blackjack-shell`.
    View name preserved so existing controller + surface tests bind to
    `game-provider-front.internal-blackjack`.

    Classic = traditional emerald felt, warm gold accent, casino-house feel.
--}}
@include('game-provider-front.internal-blackjack-shell', [
    'payload' => $payload,
    'session' => $session,
    'bjTheme' => 'classic',
    'bjGameLabel' => $payload['gameName'] ?? 'Blackjack',
    'bjGameTagline' => trans('cms_public.games.blackjack.tagline_classic'),
    'bjThemeTokens' => [
        '--bj-felt-1' => '#0e4d34',
        '--bj-felt-2' => '#052e1d',
        '--bj-felt-edge' => '#03180e',
        '--bj-felt-pattern' => 'rgba(255, 255, 255, 0.025)',
        '--bj-accent' => '#f0c869',
        '--bj-accent-warm' => '#e89f4a',
        '--bj-accent-soft' => 'rgba(240, 200, 105, 0.18)',
        '--bj-accent-glow' => 'rgba(240, 200, 105, 0.34)',
        '--bj-chip-rim' => '#f6dd96',
        '--bj-crest-line' => 'rgba(240, 200, 105, 0.36)',
    ],
])
