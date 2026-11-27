@php
    $disableDepositAlert = true;
    $disableSocialPanel = false;
    $frontLayoutMode = 'default';
    $frontLayoutFlags = [
        'hide_footer' => true,
        'hide_preloader' => true,
    ];
    $pageTitle = trim((string) (data_get($promotion, 'translation.detail_title') ?: data_get($promotion, 'translation.listing_title') ?: trans('cms_public.promotions.detail_title_fallback')));
@endphp
@extends('cms.global.index')

@push('head')
    <style>
        .promotionDetailPageOuter {
            padding: 34px 0 56px;
            --promotion-surface: linear-gradient(180deg, rgba(12, 18, 42, 0.96) 0%, rgba(8, 13, 30, 0.98) 100%);
            --promotion-surface-soft: linear-gradient(180deg, rgba(16, 24, 56, 0.9) 0%, rgba(10, 15, 35, 0.94) 100%);
            --promotion-border: rgba(125, 148, 220, 0.14);
            --promotion-border-strong: rgba(139, 170, 255, 0.24);
            --promotion-text: #f5f8ff;
            --promotion-muted: rgba(216, 224, 247, 0.72);
            --promotion-muted-strong: rgba(230, 236, 255, 0.88);
            --promotion-accent: #79a7ff;
            --promotion-shadow: 0 26px 60px rgba(4, 9, 24, 0.34);
        }

        .promotionDetailPageInner {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .promotionDetailBackLink {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            align-self: flex-start;
            color: #b8ccff;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
        }

        .promotionDetailBackLink:hover,
        .promotionDetailBackLink:focus-visible {
            color: #d7e4ff;
        }

        .promotionDetailHero {
            position: relative;
            overflow: hidden;
            padding: 28px;
            border: 1px solid var(--promotion-border);
            border-radius: 30px;
            background:
                radial-gradient(circle at top right, rgba(86, 121, 255, 0.2), transparent 34%),
                radial-gradient(circle at bottom left, rgba(50, 198, 255, 0.14), transparent 28%),
                var(--promotion-surface);
            box-shadow: var(--promotion-shadow);
        }

        .promotionDetailHeroGrid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(360px, 0.95fr) minmax(0, 1.1fr);
            gap: 24px;
            align-items: stretch;
        }

        .promotionDetailVisual {
            min-height: 100%;
            border-radius: 24px;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(26, 38, 89, 0.96) 0%, rgba(13, 19, 44, 0.98) 100%);
            border: 1px solid var(--promotion-border-strong);
        }

        .promotionDetailVisual img {
            display: block;
            width: 100%;
            height: 100%;
            min-height: 320px;
            object-fit: cover;
        }

        .promotionDetailCopy {
            display: flex;
            flex-direction: column;
            gap: 16px;
            min-width: 0;
        }

        .promotionDetailMetaRow {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .promotionDetailBadge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .promotionDetailBadge.is-main {
            border: 1px solid rgba(129, 150, 222, 0.16);
            background: rgba(255, 255, 255, 0.05);
            color: var(--promotion-muted-strong);
        }

        .promotionDetailBadge.is-sportsbook {
            background: rgba(76, 125, 255, 0.16);
            color: #dfe8ff;
        }

        .promotionDetailBadge.is-chess {
            background: rgba(68, 197, 175, 0.16);
            color: #dffff8;
        }

        .promotionDetailBadge.is-live {
            background: rgba(255, 130, 145, 0.15);
            color: #ffe5ea;
        }

        .promotionDetailBadge.is-casino {
            background: rgba(255, 185, 79, 0.16);
            color: #fff0cc;
        }

        .promotionDetailTitle {
            margin: 0;
            color: var(--promotion-text);
            font-size: clamp(30px, 3.6vw, 48px);
            line-height: 0.96;
            font-weight: 900;
            letter-spacing: -0.05em;
        }

        .promotionDetailSummary {
            max-width: 620px;
            margin: 0;
            color: var(--promotion-muted);
            font-size: 15px;
            line-height: 1.74;
        }

        .promotionDetailFacts {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .promotionDetailFact {
            padding: 16px 18px;
            border-radius: 20px;
            border: 1px solid rgba(126, 149, 223, 0.14);
            background: rgba(255, 255, 255, 0.04);
        }

        .promotionDetailFact span {
            display: block;
            margin-bottom: 6px;
            color: var(--promotion-muted);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .promotionDetailFact strong {
            display: block;
            color: var(--promotion-text);
            font-size: 19px;
            font-weight: 900;
            line-height: 1.15;
        }

        .promotionDetailActions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 4px;
        }

        .promotionDetailPrimaryButton,
        .promotionDetailSecondaryButton {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 50px;
            padding: 0 18px;
            border-radius: 16px;
            border: 1px solid transparent;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease;
        }

        .promotionDetailPrimaryButton {
            background: linear-gradient(135deg, #5c8fff 0%, #3767f0 100%);
            color: #f5f8ff;
            border-color: rgba(137, 168, 255, 0.34);
        }

        .promotionDetailSecondaryButton {
            background: rgba(255, 255, 255, 0.04);
            color: var(--promotion-muted-strong);
            border-color: rgba(129, 150, 222, 0.16);
        }

        .promotionDetailPrimaryButton:hover,
        .promotionDetailPrimaryButton:focus-visible,
        .promotionDetailSecondaryButton:hover,
        .promotionDetailSecondaryButton:focus-visible {
            transform: translateY(-1px);
            color: var(--promotion-text);
        }

        .promotionDetailArticle {
            padding: 28px;
            border: 1px solid var(--promotion-border);
            border-radius: 28px;
            background: var(--promotion-surface-soft);
            box-shadow: 0 18px 42px rgba(4, 8, 22, 0.24);
            color: var(--promotion-muted-strong);
        }

        .promotionDetailArticle h1,
        .promotionDetailArticle h2,
        .promotionDetailArticle h3,
        .promotionDetailArticle h4 {
            color: var(--promotion-text);
            font-weight: 900;
            letter-spacing: -0.03em;
        }

        .promotionDetailArticle p,
        .promotionDetailArticle li {
            color: var(--promotion-muted);
            font-size: 15px;
            line-height: 1.8;
        }

        .promotionDetailArticle ul,
        .promotionDetailArticle ol {
            padding-left: 20px;
        }

        .promotionDetailArticle a {
            color: #9fc0ff;
        }

        @media (min-width: 1024px) and (max-width: 1439px) {
            body.gelirse-menu-variant-2:not(.gelirse-left-rail-expanded) .promotionDetailPageOuter {
                width: calc(100% - (var(--gelirse-rail-width-collapsed) + 54px));
                margin-left: calc(var(--gelirse-rail-width-collapsed) + 54px);
            }
        }

        @media (max-width: 1023px) {
            .promotionDetailPageOuter {
                padding-top: 28px;
            }

            .promotionDetailHero {
                padding: 22px;
                border-radius: 24px;
            }

            .promotionDetailHeroGrid,
            .promotionDetailFacts {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 767px) {
            .promotionDetailTitle {
                font-size: 34px;
            }

            .promotionDetailVisual img {
                min-height: 220px;
            }
        }
    </style>
@endpush

@section('pageContent')
    @php
        $promotionTitle = trim((string) (data_get($promotion, 'translation.detail_title') ?: data_get($promotion, 'translation.listing_title') ?: trans('cms_public.promotions.detail_title_fallback')));
        $promotionSummary = trim((string) (data_get($promotion, 'translation.short_description') ?: trans('cms_public.promotions.summary_fallback')));
        $promotionDescription = (string) (data_get($promotion, 'translation.description') ?: trans('cms_public.promotions.description_fallback'));
        $promotionActionLabel = trim((string) (data_get($promotion, 'translation.redirect_button_text') ?: trans('cms_public.promotions.use_promotion')));
        $promotionMeta = ['label' => trans('cms_public.promotions.title'), 'icon' => 'fas fa-gift', 'accent' => 'is-promotions'];
        $promotionActionUrl = \App\Support\B1ContentUrl::allowed($promotion->redirect_url) ? $promotion->redirect_url : null;
    @endphp
    <div class="contentPageOuter promotionDetailPageOuter">
        <div class="container">
            <div class="promotionDetailPageInner">
                <a href="{{ route('cms.promotions') }}" class="promotionDetailBackLink">
                    <i class="fas fa-arrow-left"></i>
                    {{ trans('cms_public.promotions.back_to_promotions') }}
                </a>

                <section class="promotionDetailHero">
                    <div class="promotionDetailHeroGrid">
                        <div class="promotionDetailVisual">
                            <img src="{{ $promotion->getDetailImage() ?: $promotion->getThumbnail() }}" alt="{{ $promotionTitle }}">
                        </div>
                        <div class="promotionDetailCopy">
                            <div class="promotionDetailMetaRow">
                                <span class="promotionDetailBadge is-main {{ $promotionMeta['accent'] }}">
                                    <i class="{{ $promotionMeta['icon'] }}"></i>
                                    {{ $promotionMeta['label'] }}
                                </span>
                                @if($promotion->end_date && $promotion->end_date > now())
                                    <span class="promotionDetailBadge is-main">
                                        <i class="fas fa-clock"></i>
                                        {{ \Illuminate\Support\Carbon::parse($promotion->end_date)->locale(app()->getLocale())->diffForHumans() }}
                                    </span>
                                @elseif((int) $promotion->is_permanent === 1)
                                    <span class="promotionDetailBadge is-main">
                                        <i class="fas fa-infinity"></i>
                                        {{ trans('cms_public.promotions.permanent_campaign') }}
                                    </span>
                                @endif
                            </div>

                            <h1 class="promotionDetailTitle">{{ $promotionTitle }}</h1>
                            <p class="promotionDetailSummary">{{ $promotionSummary }}</p>

                            <div class="promotionDetailFacts">
                                <div class="promotionDetailFact">
                                    <span>{{ trans('cms_public.promotions.status') }}</span>
                                    <strong>{{ (int) $promotion->is_permanent === 1 ? trans('cms_public.promotions.always_active') : trans('cms_public.promotions.timed_active') }}</strong>
                                </div>
                                <div class="promotionDetailFact">
                                    <span>{{ trans('cms_public.promotions.redirect') }}</span>
                                    <strong>{{ $promotionMeta['label'] }}</strong>
                                </div>
                                <div class="promotionDetailFact">
                                    <span>{{ trans('cms_public.promotions.access') }}</span>
                                    <strong>{{ auth('cms')->check() ? trans('cms_public.promotions.account_ready') : trans('cms_public.promotions.guest_view') }}</strong>
                                </div>
                            </div>

                            <div class="promotionDetailActions">
                                @if($promotionActionUrl)<a href="{{ $promotionActionUrl }}" target="_blank" rel="noopener noreferrer" class="promotionDetailPrimaryButton">
                                    <i class="fas fa-bolt"></i>
                                    {{ $promotionActionLabel }}
                                </a>@endif
                                <a href="{{ route('cms.promotions') }}" class="promotionDetailSecondaryButton">
                                    <i class="fas fa-grid-2"></i>
                                    {{ trans('cms_public.promotions.show_all') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <article class="promotionDetailArticle">

                    {!! \App\Support\CmsContentHtml::render($promotionDescription) !!}
                </article>
            </div>
        </div>
    </div>
@endsection
