@extends('cms.global.index')

@section('pageContent')
    <div class="globalSurfacePage globalInformationPage" data-global-information-page>
        <nav class="globalBreadcrumbs" aria-label="{{ trans('cms_public.info_pages.center') }}">
            <a href="{{ route('cms.front.home') }}">{{ trans('cms_public.shell.home') }}</a>
            <span aria-hidden="true">/</span><span aria-current="page">{{ trans('cms_public.info_pages.center') }}</span>
        </nav>
        <header class="globalSurfaceIntro">
            <h1>{{ trans('cms_public.info_pages.center') }}</h1>
        </header>
        <p class="journeyNotice">{{ __('journey.legal_notice') }}</p>
        <nav class="globalInfoTabs" aria-label="{{ trans('cms_public.info_pages.center') }}">
            @foreach($informationSections as $entry)
                <a href="#{{ $entry['id'] }}" data-global-information-link>{{ $entry['copy']['title'] }}</a>
            @endforeach
        </nav>
        <div class="globalInformationSections">
            @foreach($informationSections as $entry)
                <details class="globalFinancePanel globalInfoEntry" id="{{ $entry['id'] }}" data-global-information-section @if($loop->first) open @endif>
                    <summary><strong>{{ $entry['copy']['title'] }}</strong><span aria-hidden="true">⌄</span></summary>
                    <div class="globalInformationContent">
                        @include('cms.global.content.partials.legal-page-content', ['legalPage' => $entry['copy']])
                        <a href="{{ route('cms.staticPage',$entry['id']) }}">{{ __('journey.pages.'.$entry['id']) }} →</a>
                    </div>
                </details>
            @endforeach
        </div>
        @include('cms.global.partials.journey.information-links')
    </div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('cms-assets/themes/global/feature-parity.css') }}?v={{ filemtime(public_path('cms-assets/themes/global/feature-parity.css')) }}">
@endpush
@push('js')
    <script>
        (() => {
            const page = document.querySelector('[data-global-information-page]');
            if (!page) return;
            const openHashSection = () => {
                let id;
                try { id = decodeURIComponent(window.location.hash.slice(1)); } catch (_) { return; }
                if (!id) return;
                const section = Array.from(page.querySelectorAll('[data-global-information-section]'))
                    .find(node => node.id === id);
                if (section) section.open = true;
            };
            page.querySelectorAll('[data-global-information-link]').forEach(link => {
                link.addEventListener('click', () => {
                    const id = link.hash.slice(1);
                    const section = Array.from(page.querySelectorAll('[data-global-information-section]'))
                        .find(node => node.id === id);
                    if (section) section.open = true;
                });
            });
            window.addEventListener('hashchange', openHashSection);
            openHashSection();
        })();
    </script>
@endpush
