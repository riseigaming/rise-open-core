@php
    $promotionImage = $promotion->getDetailImage();

    if ($promotionImage === 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"></svg>') {
        $promotionImage = $promotion->getThumbnail();
    }

    $promotionTitle = data_get($promotion, 'display_detail_title')
        ?: data_get($promotion, 'display_listing_title')
        ?: data_get($promotion, 'translation.detail_title')
        ?: data_get($promotion, 'translation.listing_title');
    $promotionSummary = data_get($promotion, 'display_short_description')
        ?: data_get($promotion, 'translation.short_description');
    $promotionDescription = data_get($promotion, 'display_description')
        ?: data_get($promotion, 'translation.description');
    $promotionActionUrl = \App\Support\B1ContentUrl::allowed($promotion->redirect_url) ? $promotion->redirect_url : null;
    $promotionActionLabel = data_get($promotion, 'display_redirect_button_text')
        ?: data_get($promotion, 'translation.redirect_button_text')
        ?: trans('cms_public.promotions.use_promotion');
    $hasTimedCountdown = filled($promotion->end_date) && \Illuminate\Support\Carbon::parse($promotion->end_date)->isFuture();
@endphp

<div class="promotionDetailModalContent">
    <div class="promotionDetailModalHero">
        <div class="promotionDetailModalVisual">
            @if($promotionImage)
                <img src="{{ $promotionImage }}" alt="{{ $promotionTitle }}">
            @endif
        </div>
    </div>
    <div class="promotionDetailModalCopy">
        @if($hasTimedCountdown)
            <div class="promotionDetailModalBadgeRow">
                <span class="promotionDetailModalBadge is-countdown" data-promo-countdown data-end-date="{{ $promotion->end_date }}">
                    {{ \Illuminate\Support\Carbon::parse($promotion->end_date)->locale(app()->getLocale())->diffForHumans() ?: trans('cms_public.promotions.remaining_fallback') }}
                </span>
            </div>
        @endif
        <div class="promotionDetailModalHeroCopy">
            <h3>{{ $promotionTitle }}</h3>
            @if($promotionSummary)
                <p class="promotionDetailModalSummary">{{ $promotionSummary }}</p>
            @endif
            <div class="promotionDetailModalActions">
                @if($promotionActionUrl)
                <a href="{{ $promotionActionUrl }}" class="promotionDetailModalPrimary" @if($promotion->redirect_url) target="_blank" rel="noopener noreferrer" @endif>
                    {{ $promotionActionLabel }}
                </a>
                @endif
            </div>
        </div>
        @if($promotionDescription)
            <h4 class="promotionDetailModalSectionTitle">{{ trans('cms_public.promotions.terms') }}</h4>
            <div class="promotionDetailModalDescription">{!! \App\Support\CmsContentHtml::render($promotionDescription) !!}</div>
        @endif
    </div>
</div>
