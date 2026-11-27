@php
    $item = $item ?? [];
@endphp

<article class="sb2-prediction-card" data-prediction-card data-prediction-id="{{ data_get($item, 'id') }}">
    <div class="sb2-prediction-top">
        <div>
            <div class="sb2-prediction-league">{{ data_get($item, 'league_name') ?: 'League' }}</div>
            <h3 class="sb2-prediction-fixture">{{ data_get($item, 'home_team') }} vs {{ data_get($item, 'away_team') }}</h3>
        </div>
    </div>

    <button type="button" class="sb2-prediction-detail-toggle" data-role="prediction-detail-toggle" aria-expanded="false">
        <span data-role="prediction-detail-toggle-label">Detayı aç</span>
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="m7 10 5 5 5-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
        </svg>
    </button>

    <div class="sb2-prediction-detail" data-role="prediction-detail" hidden>
        <span class="sb2-prediction-provider">{{ str_replace('API-Football', 'Football', (string) data_get($item, 'provider_name')) }}</span>
        <div class="sb2-prediction-meta">
            <span class="sb2-prediction-pill">{{ data_get($item, 'kickoff_label') }}</span>
            <span class="sb2-prediction-pill">{{ data_get($item, 'predictable_label') }}</span>
            <span class="sb2-prediction-pill is-muted">Observed {{ data_get($item, 'observed_label') }}</span>
            <span class="sb2-prediction-pill is-muted">Updated {{ data_get($item, 'updated_label') }}</span>
        </div>

        <div class="sb2-prediction-stats">
            <div class="sb2-prediction-stat">
                <span>Maç sonucu tahmini</span>
                <strong>{{ data_get($item, 'winner_pick_label') }}</strong>
                <small>{{ data_get($item, 'winner_breakdown_label') ?: '-' }}</small>
            </div>
            <div class="sb2-prediction-stat">
                <span>Beraberlik olasılığı</span>
                <strong>{{ data_get($item, 'draw_probability_label') }}</strong>
                <small>Provider: {{ str_replace('API-Football', 'Football', (string) data_get($item, 'provider_name')) }}</small>
            </div>
            <div class="sb2-prediction-stat">
                <span>Toplam gol tahmini</span>
                <strong>{{ data_get($item, 'totals_pick_label') }}</strong>
                <small>{{ data_get($item, 'totals_breakdown_label') ?: '-' }}</small>
            </div>
            <div class="sb2-prediction-stat">
                <span>Confidence</span>
                <strong>{{ data_get($item, 'confidence_label') }}</strong>
                <small>{{ data_get($item, 'predictable_label') }}</small>
            </div>
        </div>

        @if(data_get($item, 'short_summary') || data_get($item, 'editor_note'))
            <div class="sb2-prediction-copy">
                @if(data_get($item, 'short_summary'))
                    <p>{{ data_get($item, 'short_summary') }}</p>
                @endif
                @if(data_get($item, 'editor_note'))
                    <p>{{ data_get($item, 'editor_note') }}</p>
                @endif
            </div>
        @endif
    </div>
</article>
