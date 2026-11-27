<article class="globalSurfacePage" data-global-legal-page>
    @include('cms.global.partials.journey.breadcrumb',['crumbs'=>[__('journey.information')=>route('cms.information'),$legalPage['title']=>null]])
    <p class="journeyNotice">{{ __('journey.legal_notice') }}</p>
    <header class="globalSurfaceIntro">
        <h1>{{ $legalPage['title'] ?? trans('cms_public.info_pages.center') }}</h1>
        @if(filled($legalPage['summary'] ?? null))<p>{{ $legalPage['summary'] }}</p>@endif
    </header>
    @include('cms.global.content.partials.legal-page-content', ['legalPage' => $legalPage])
    @include('cms.global.partials.journey.information-links')
</article>
