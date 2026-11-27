@php
    $sportsbookUi = (array) __('cms_public.sportsbook');
    $sbText = static fn (string $key, string $fallback = ''): string => (string) data_get($sportsbookUi, $key, $fallback);
    $panelKey = (string) ($panelKey ?? 'social-market');
    $panelTitle = (string) ($panelTitle ?? $sbText('tabs.social_market', 'Sosyal Market'));
    $panelActive = (bool) ($panelActive ?? false);
    $renderInitialCards = (bool) ($renderInitialCards ?? false);
    $categories = collect($categories ?? [])->values();
    $items = collect($items ?? [])->values();
    $categoryAriaLabel = (string) ($categoryAriaLabel ?? $sbText('social_market.category_aria', 'Sosyal market kategorileri'));
    $loadedEmptyText = (string) ($loadedEmptyText ?? $sbText('empty.social_empty', 'Gösterilecek sosyal market bulunamadı.'));
    $lazyEmptyText = (string) ($lazyEmptyText ?? $sbText('empty.social_lazy', 'Sosyal Market bu sekmeye geçtiğinizde yüklenecek.'));
    $modalTitleId = (string) ($modalTitleId ?? ($panelKey . 'DetailTitle'));
    $staticPayload = (bool) ($staticPayload ?? false);
    $categoryScope = trim((string) ($categoryScope ?? ''));
@endphp

<div class="sb2-tab-panel {{ $panelActive ? 'is-active' : '' }}" data-tab-panel="{{ $panelKey }}" aria-hidden="{{ $panelActive ? 'false' : 'true' }}" @if(!$panelActive) hidden @endif>
    <div
        class="sb2-social-market-shell"
        data-social-market-root
        @if($staticPayload) data-social-market-static="true" @endif
        @if($categoryScope !== '') data-social-market-category-scope="{{ $categoryScope }}" @endif
    >
        <div class="sb2-social-market-board">
            <div class="sb2-social-market-head">
                <div>
                    <h3>{{ $panelTitle }}</h3>
                </div>
            </div>

            <div class="sb2-social-market-chip-rail" role="tablist" aria-label="{{ $categoryAriaLabel }}" data-social-market-chip-rail>
                @foreach(($renderInitialCards ? $categories : collect()) as $index => $category)
                    @php($categoryKey = (string) data_get($category, 'key'))
                    @php($categoryTitle = (string) data_get($category, 'title'))
                    @php($categoryCount = (int) data_get($category, 'count', 0))
                    @php($categoryIcon = trim((string) data_get($category, 'icon', '')))
                    <button
                        type="button"
                        class="sb2-social-market-chip {{ $index === 0 ? 'is-active' : '' }}"
                        data-social-market-category-filter="{{ $categoryKey }}"
                        aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                    >
                        <span class="sb2-social-market-chip-icon">
                            @if(str_starts_with($categoryIcon, 'fa'))
                                <i class="{{ $categoryIcon }}" aria-hidden="true"></i>
                            @else
                                {{ strtoupper(substr($categoryIcon !== '' ? $categoryIcon : $categoryTitle, 0, 2)) }}
                            @endif
                        </span>
                        <span>{{ $categoryTitle }}</span>
                        <strong data-count-raw="{{ $categoryCount }}">{{ $formatCountLabel($categoryCount) }}</strong>
                    </button>
                @endforeach
            </div>

            <div class="sb2-social-market-grid" data-social-market-grid>
                @forelse(($renderInitialCards ? $items : collect()) as $market)
                    @php($marketCategoryKey = (string) data_get($market, 'category_key'))
                    @php($marketHidden = $categories->isNotEmpty() && $marketCategoryKey !== (string) data_get($categories->first(), 'key'))
                    @php($marketRender = (array) data_get($market, 'render', []))
                    @php($marketBadge = trim((string) data_get($marketRender, 'badge', 'SM')))
                    @php($marketIcon = trim((string) data_get($marketRender, 'thumbnail_icon', 'fas fa-chart-line')))
                    @php($marketTopic = trim((string) data_get($marketRender, 'topic', data_get($market, 'subtitle', ''))))
                    @php($marketVariant = trim((string) data_get($marketRender, 'card_variant', 'standard')))
                    @php($marketSearchText = \Illuminate\Support\Str::lower(collect([
                        data_get($market, 'title'),
                        data_get($market, 'subtitle'),
                        data_get($market, 'category_title'),
                        $marketTopic,
                        $marketBadge,
                        collect(data_get($market, 'outcomes', []))->pluck('label')->implode(' '),
                    ])->filter()->implode(' ')))
                    <article class="sb2-social-market-card" data-social-market-card data-social-market-category="{{ $marketCategoryKey }}" data-social-market-search="{{ $marketSearchText }}" @if($marketHidden) hidden @endif>
                        <button type="button" class="sb2-social-market-card-head" data-social-market-detail-trigger aria-label="{{ str_replace(':title', (string) data_get($market, 'title'), $sbText('social_market.detail_open', ':title detayını aç')) }}">
                            <span class="sb2-social-market-thumb" data-variant="{{ $marketVariant }}">
                                @if(str_starts_with($marketIcon, 'fa'))
                                    <i class="{{ $marketIcon }}" aria-hidden="true"></i>
                                @endif
                                <strong>{{ $marketBadge !== '' ? $marketBadge : strtoupper(substr((string) data_get($market, 'category_title'), 0, 2)) }}</strong>
                            </span>
                            <div class="sb2-social-market-title-block">
                                <h3>{{ data_get($market, 'title') }}</h3>
                                <span>{{ $marketTopic !== '' ? $marketTopic : data_get($market, 'category_title') }}</span>
                            </div>
                            <span class="sb2-social-market-chevron" aria-hidden="true">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        </button>

                        @if(data_get($market, 'subtitle'))
                            <p class="sb2-social-market-subtitle">{{ data_get($market, 'subtitle') }}</p>
                        @endif

                        <div class="sb2-social-market-outcomes">
                            @foreach(collect(data_get($market, 'outcomes', []))->values() as $outcome)
                                @php($selectionPayload = (array) $outcome)
                                @php($outcomeTone = (string) data_get($outcome, 'render.tone', 'neutral'))
                                @php($priceLabel = 'x' . rtrim(rtrim(number_format((float) data_get($outcome, 'price', 0), 2, '.', ''), '0'), '.'))
                                <button
                                    type="button"
                                    class="sb2-social-market-outcome sb2-odd-box is-{{ in_array($outcomeTone, ['yes', 'no', 'neutral'], true) ? $outcomeTone : 'neutral' }}"
                                    data-selection-trigger
                                    data-selection='@json($selectionPayload)'
                                >
                                    <span class="sb2-odd-box-label">{{ data_get($outcome, 'label') }}</span>
                                    <span class="sb2-odd-box-value">{{ $priceLabel }}</span>
                                </button>
                            @endforeach
                        </div>

                        <div class="sb2-social-market-metrics" aria-label="{{ $sbText('social_market.stats_aria', 'Sosyal market istatistikleri') }}">
                            <span><i class="fas fa-chart-bar" aria-hidden="true"></i> {{ $sbText('social_market.interest_score', 'İlgi skoru') }}: {{ data_get($marketRender, 'volume_label', '-') }}</span>
                            <span><i class="fas fa-users" aria-hidden="true"></i> {{ $sbText('social_market.community_interest', 'Topluluk ilgisi') }}: {{ data_get($marketRender, 'bettors_label', '-') }}</span>
                        </div>
                    </article>
                @empty
                    <div class="sb2-feed-empty">{{ $renderInitialCards ? $loadedEmptyText : $lazyEmptyText }}</div>
                @endforelse
            </div>

            <div class="sb2-social-market-modal" data-social-market-detail-modal hidden aria-hidden="true">
                <button type="button" class="sb2-social-market-modal-backdrop" data-social-market-detail-close aria-label="{{ $sbText('social_market.detail_close', 'Detayı kapat') }}"></button>
                <section class="sb2-social-market-modal-panel" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}">
                    <div class="sb2-social-market-modal-head">
                        <span class="sb2-social-market-modal-thumb" data-social-market-modal-thumb></span>
                        <div class="sb2-social-market-modal-kicker">
                            <span data-social-market-modal-topic></span>
                            <strong>{{ $sbText('social_market.single_bet', 'Tekli bahis') }}</strong>
                        </div>
                        <button type="button" class="sb2-social-market-modal-close" data-social-market-detail-close aria-label="{{ $sbText('social_market.detail_close', 'Detayı kapat') }}">
                            <i class="fas fa-times" aria-hidden="true"></i>
                        </button>
                    </div>
                    <h3 id="{{ $modalTitleId }}" data-social-market-modal-title></h3>
                    <p data-social-market-modal-subtitle></p>
                    <div class="sb2-social-market-modal-outcomes" data-social-market-modal-outcomes></div>
                    <div class="sb2-social-market-modal-metrics" data-social-market-modal-metrics></div>
                </section>
            </div>
        </div>
    </div>
</div>
