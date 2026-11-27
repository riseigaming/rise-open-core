<section data-guest-preview="{{ $payload['gameCode'] ?? 'dice' }}" style="position:relative;z-index:10;padding:12px 20px;background:#111923;color:#f4f7fb">
    <a style="color:inherit" href="{{ route('cms.games.index') }}">← {{ __('catalog.back') }}</a>
    <details>
        <summary>{{ __('catalog.guest_preview') }} · {{ $payload['gameName'] }}</summary>
        <p>{{ __('catalog.guest_preview_note') }}</p>
        @if(isset($payload['rules']['blackjackPayoutMultiplier']))
            <p>{{ __('catalog.game_rules') }} · ×{{ $payload['rules']['blackjackPayoutMultiplier'] }}</p>
        @endif
        @if(!empty($payload['symbolAssets']))
            <div>
                @foreach(array_slice($payload['symbolAssets'], 1, 5) as $symbol => $assetPath)
                    <img src="{{ $assetPath }}" alt="{{ $symbol }}" width="32" height="32">
                @endforeach
            </div>
        @endif
    </details>
</section>
