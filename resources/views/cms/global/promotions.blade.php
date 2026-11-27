@php
    $disableDepositAlert = true;
    $disableSocialPanel = false;
    $frontLayoutMode = 'default';
    $frontLayoutFlags = [
        'hide_footer' => true,
        'hide_preloader' => true,
        'hide_top_campaign_line' => true,
    ];
    $pageTitle = trans('cms_public.promotions.title');
@endphp
@extends('cms.global.index')

@push('head')
    <link rel="stylesheet" href="{{ asset('cms-assets/themes/global/promotions.css') }}?v={{ filemtime(public_path('cms-assets/themes/global/promotions.css')) }}">
@endpush

@section('pageContent')
    @php
        $promotions = collect($promotions ?? [])->filter()->values();
    @endphp
    <section class="globalSurfacePage promotionsPageOuter" id="promotions-top" data-promotions-page>
        @include('cms.global.partials.journey.breadcrumb',['crumbs'=>[__('cms_public.promotions.title')=>null]])

            <nav class="promotionsTabs winxSubpageTabs" data-winx-subpage-tabs aria-label="{{ trans('cms_public.promotions.title') }}">
                <a href="{{ route('cms.promotions') }}" class="promotionsTab is-active" aria-current="page">
                    <i class="fas fa-gift" aria-hidden="true"></i>
                    <span>{{ trans('cms_public.promotions.title') }}</span>
                </a>
            </nav>

        <div class="container">
          <div class="promotionsContent winxSubpageContent">
            <h1 class="visually-hidden">{{ $pageTitle }}</h1>
            @if($promotions->isNotEmpty())
                <div class="promotionsGrid">
                    @foreach($promotions as $promotion)
                        @php
                            $promotionModalId = 'promotionPageTimedPromotionModal-' . $promotion->id;
                            $promotionTitle = data_get($promotion, 'translation.listing_title', trans('cms_public.promotions.fallback_title'));
                        @endphp
                        <article class="promotionCard">
                            <button type="button"
                                    class="promotionCardVisual"
                                    data-navbar-timed-promotion-trigger
                                    data-navbar-timed-promotion-placement="viewport"
                                    data-bs-target="#{{ $promotionModalId }}"
                                    aria-label="{{ trans('cms_public.promotions.details') }}: {{ $promotionTitle }}"
                                    aria-haspopup="dialog"
                                    aria-expanded="false">
                                <img src="{{ $promotion->getThumbnail() }}" alt="" loading="lazy">
                            </button>
                            <div class="promotionCardBody">
                                <h2 class="promotionCardTitle">
                                    <button type="button"
                                            class="promotionCardTitleTrigger"
                                            data-navbar-timed-promotion-trigger
                                            data-navbar-timed-promotion-placement="viewport"
                                            data-bs-target="#{{ $promotionModalId }}"
                                            aria-haspopup="dialog"
                                            aria-expanded="false">{{ $promotionTitle }}</button>
                                </h2>
                                <p class="promotionCardText">{{ data_get($promotion, 'translation.short_description', trans('cms_public.promotions.listing_fallback')) }}</p>
                                @if(filled($promotion->end_date) && \Illuminate\Support\Carbon::parse($promotion->end_date)->isFuture())
                                    <div class="promotionCardText promotionCardRemaining">
                                        <i class="fas fa-clock"></i>
                                        {{ \Illuminate\Support\Carbon::parse($promotion->end_date)->locale(app()->getLocale())->diffForHumans() ?: trans('cms_public.promotions.remaining_fallback') }}
                                    </div>
                                @endif
                                <div class="promotionCardFooter">
                                    <button type="button"
                                            class="promotionCardAction"
                                            data-navbar-timed-promotion-trigger
                                            data-navbar-timed-promotion-placement="viewport"
                                            data-bs-target="#{{ $promotionModalId }}"
                                            aria-haspopup="dialog"
                                            aria-expanded="false">
                                        <span>{{ trans('cms_public.promotions.details') }}</span>
                                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </article>
                        @include('cms.global.partials.navbar-timed-promotion-modal', [
                            'promotion' => $promotion,
                            'modalId' => $promotionModalId,
                        ])
                    @endforeach
                </div>
            @else
                <div class="promotionsEmptyState">{{ trans('cms_public.promotions.empty') }}</div>
            @endif
          </div>
        </div>
    </section>
@endsection
