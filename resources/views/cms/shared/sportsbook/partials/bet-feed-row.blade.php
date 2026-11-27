@php
    $item = $item ?? [];
    $canAdd = (bool) ($item['add_to_coupon_available'] ?? false);
    $text = fn ($key) => __('cms_public.sportsbook.bet_feed.' . $key);
@endphp
<article class="sb2-feed-item sb2-feed-contract" data-item-id="{{ $item['feed_item_id'] ?? '' }}" data-feed-status="{{ $item['status'] ?? 'unavailable' }}" data-expires-at="{{ $item['expires_at'] ?? '' }}">
    <div class="sb2-feed-contract-meta">
        <span data-role="source-label">{{ $item['source_label'] ?? '' }}</span>
        <span data-role="display-name">{{ $item['creator_display'] ?? '' }}</span>
        <strong data-role="status-label">{{ $item['status_label'] ?? '' }}</strong>
    </div>
    <div class="sb2-feed-contract-main">
        <div class="sb2-feed-cell">
            <strong class="sb2-feed-event" data-role="event-label">{{ $item['event_label'] ?? '' }}</strong>
            <span data-role="league-label">{{ $item['league_name'] ?? '' }}</span>
            <button type="button" class="sb2-feed-summary" data-role="summary-button" aria-expanded="false">{{ $item['short_coupon_label'] ?? '' }}</button>
        </div>
        <div class="sb2-feed-cell">
            <strong data-role="outcome-label">{{ $item['outcome_label'] ?? '' }}</strong>
            <span data-role="odds-value">{{ isset($item['odds_total']) ? number_format($item['odds_total'], 2, '.', '') : '—' }}</span>
        </div>
        <div class="sb2-feed-cell sb2-feed-times">
            <span>{{ $text('created') }} <time data-role="created-time" datetime="{{ $item['created_at'] ?? '' }}">{{ $item['created_at'] ?? '—' }}</time></span>
            <span>{{ $text('checked') }} <time data-role="checked-time" datetime="{{ $item['checked_at'] ?? '' }}">{{ $item['checked_at'] ?? '—' }}</time></span>
        </div>
        <div class="sb2-feed-cell">
            <button type="button" class="sb2-feed-add" data-role="betslip-button" data-bet-feed-add-selection='@json($item["selection_reference"] ?? null)' data-default-label="{{ $text('add_to_betslip') }}" @if(!$canAdd) hidden disabled @endif>
                <span class="sb2-feed-add-label">{{ $text('add_to_betslip') }}</span>
            </button>
            <small data-role="disabled-reason" role="status" @if($canAdd) hidden @endif>{{ $item['disabled_reason_label'] ?? $text('not_addable') }}</small>
        </div>
    </div>
    <div class="sb2-feed-contract-actions">
        <button type="button" data-role="native-share" data-feed-share-url="{{ $item['share_url'] ?? '' }}"
                @unless(filled($item['share_url'] ?? null)) disabled @endunless><i class="fas fa-share-alt" aria-hidden="true"></i> {{ $text('share') }}</button>
        <button type="button" data-role="sports-room-share" data-feed-chat-share-route="{{ $item['chat_share_route'] ?? '' }}"
                @unless(filled($item['chat_share_route'] ?? null)) disabled @endunless><i class="fas fa-comment" aria-hidden="true"></i> {{ $text('share_chat') }}</button>
        <button type="button" disabled data-feed-unsupported="translate" title="{{ $text('coming_soon') }}"><i class="fas fa-language" aria-hidden="true"></i> {{ $text('translate') }} <small>{{ $text('coming_soon') }}</small></button>
    </div>
    <div class="sb2-feed-detail"><span>{{ $text('read_model_checked') }}</span> <time data-role="generation-time">{{ $item['read_model_generated_at'] ?? '—' }}</time></div>
</article>
