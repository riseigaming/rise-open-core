@extends('cms.global.index')

@section('pageContent')
    @include('cms.global.partials.layout.hero-slider', ['heroScope' => 'home'])

    <section class="globalHomeCommand" aria-labelledby="global-home-title">
        <div class="globalHomeIntro">
            <span class="globalEyebrow">{{ trans('global.one_account_clear_access') }}</span>
            <h1 id="global-home-title">{{ trans('global.b1.home_title') }}</h1>
            <p>{{ trans('global.b1.home_description') }}</p>
            <div class="globalHeroActions" aria-label="{{ trans('global.main_navigation') }}">
                <a class="globalPrimaryAction" href="{{ route('cms.promotions') }}">{{ trans('cms_public.promotions.title') }}</a>
                <a class="globalSecondaryAction" href="{{ route('cms.information') }}">{{ trans('cms_public.info_pages.center') }}</a>
            </div>
        </div>

        <aside class="globalNextAction" aria-label="{{ trans('cms_public.info_pages.sidebar.quick_title') }}">
            <span class="globalEyebrow">{{ trans('cms_public.info_pages.sidebar.quick_title') }}</span>
            <a href="{{ route('cms.promotions') }}">
                <span class="globalNavGlyph is-casino" aria-hidden="true"></span>
                <span><strong>{{ trans('cms_public.promotions.title') }}</strong><small>{{ trans('global.view_current_offers') }}</small></span>
                <b aria-hidden="true">&rarr;</b>
            </a>
            <a href="{{ route('cms.information') }}">
                <span class="globalNavGlyph is-sportsbook" aria-hidden="true"></span>
                <span><strong>{{ trans('cms_public.info_pages.center') }}</strong><small>{{ trans('global.b1.information_hint') }}</small></span>
                <b aria-hidden="true">&rarr;</b>
            </a>
            <a href="{{ route('cms.front.sessions') }}">
                <span class="globalNavGlyph is-wallet" aria-hidden="true"></span>
                <span><strong>{{ trans('global.sessions') }}</strong><small>{{ trans('global.b1.sessions_hint') }}</small></span>
                <b aria-hidden="true">&rarr;</b>
            </a>
        </aside>
    </section>

    <section class="globalProductStrip" aria-label="{{ trans('cms_public.shell.quick_navigation') }}">
        <a href="{{ route('cms.promotions') }}"><span class="globalNavGlyph is-casino" aria-hidden="true"></span><strong>{{ trans('cms_public.promotions.title') }}</strong><small>{{ trans('global.view_current_offers') }}</small></a>
        <a href="{{ route('cms.information') }}"><span class="globalNavGlyph is-sportsbook" aria-hidden="true"></span><strong>{{ trans('cms_public.info_pages.center') }}</strong><small>{{ trans('global.b1.information_hint') }}</small></a>
        <a href="{{ route('cms.front.sessions') }}"><span class="globalNavGlyph is-wallet" aria-hidden="true"></span><strong>{{ trans('global.sessions') }}</strong><small>{{ trans('global.sessions') }}</small></a>
        <a href="{{ url('/cms/promotions') }}"><span class="globalNavGlyph is-promotions" aria-hidden="true"></span><strong>{{ trans('cms_public.shell.campaigns') }}</strong><small>{{ trans('global.view_current_offers') }}</small></a>
        <a href="{{ route('cms.front.editPassword') }}"><span class="globalNavGlyph is-support" aria-hidden="true"></span><strong>{{ trans('global.b1.change_password') }}</strong><small>{{ trans('global.account_controls') }}</small></a>
    </section>

    @include('cms.global.partials.home.parity-vitrines', ['topThreePromotions' => $topThreePromotions])
    @include('cms.global.games.home')
@endsection
@push('head')
    <link rel="stylesheet" href="{{ asset('cms-assets/themes/global/parity-surfaces.css') }}?v={{ filemtime(public_path('cms-assets/themes/global/parity-surfaces.css')) }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
@endpush

@push('head')<link rel="stylesheet" href="{{ asset('cms-assets/themes/global/catalog.css') }}">@endpush
