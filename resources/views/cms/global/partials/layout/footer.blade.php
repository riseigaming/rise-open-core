@php
    $globalFooterLanguages = collect($activeLanguages ?? [])->filter(function ($language) {
        return trim((string) data_get($language, 'code')) !== '';
    });
    $globalFooterNormalizeLanguageCode = static function ($value): string {
        $code = strtolower(str_replace('_', '-', trim((string) $value)));
        $aliases = ['ing' => 'en', 'esp' => 'es'];

        if (isset($aliases[$code])) {
            return $aliases[$code];
        }

        $primary = strtok($code, '-');

        return $primary !== false && $primary !== '' ? ($aliases[$primary] ?? $primary) : $code;
    };
    $globalCurrentLocale = $globalFooterNormalizeLanguageCode(session('currentLocale', app()->getLocale()));
@endphp

<footer class="globalFooterShell">
    <div class="globalFooterBrand">
        <strong>{{ trim((string) data_get($siteSetting, 'translation.site_title')) ?: 'Rise Open Core' }}</strong>
        <span>{{ trans('journey.footer_note') }}</span>
    </div>

    <div class="journeyFooterGroups">
        <nav aria-label="{{ __('journey.catalog') }}"><h2>{{ __('journey.catalog') }}</h2><a href="{{ route('cms.games.index') }}">{{ __('journey.all') }}</a>@foreach(array_keys(\App\Models\CmsGame::TYPES) as $type)<a href="{{ route('cms.games.index',['type'=>$type]) }}">{{ __('journey.types.'.$type) }}</a>@endforeach<a href="{{ route('cms.games.index') }}#catalog-providers">{{ __('journey.providers') }}</a></nav>
        <nav aria-label="{{ __('journey.information') }}"><h2>{{ __('journey.information') }}</h2><a href="{{ route('cms.information') }}">{{ __('cms_public.info_pages.center') }}</a>@foreach(\App\Support\WinxInformationPages::PAGES as $page=>$definition)<a href="{{ route('cms.staticPage',$page) }}">{{ __('journey.pages.'.$page) }}</a>@endforeach</nav>
        <nav aria-label="{{ __('journey.help') }}"><h2>{{ __('journey.help') }}</h2><a href="{{ route('cms.front.learnCenter') }}">{{ __('journey.learn') }}</a>@foreach(\App\Support\GlobalGuides::all() as $slug=>$guide)<a href="{{ route('cms.front.learnGuide',$slug) }}">{{ $guide['title'] }}</a>@endforeach<a href="{{ route('cms.promotions') }}">{{ __('cms_public.promotions.title') }}</a></nav>
        <nav aria-label="{{ __('journey.account') }}"><h2>{{ __('journey.account') }}</h2>@auth('cms')<a href="{{ route('cms.front.sessions') }}">{{ __('global.sessions') }}</a><a href="{{ route('cms.front.editPassword') }}">{{ __('global.b1.change_password') }}</a>@else<a href="{{ route('cms.login') }}" data-global-auth-open="login">{{ __('global.log_in') }}</a>@endauth<a href="{{ route('cms.front.learnGuide','account-security') }}">{{ __('journey.guides.account-security.title') }}</a></nav>
    </div>

    @if($globalFooterLanguages->isNotEmpty())
        <nav class="globalFooterLanguages" aria-label="{{ trans('cms_public.shell.language_selection') }}">
            @foreach($globalFooterLanguages as $language)
                @php
                    $languageRouteCode = strtolower(trim((string) data_get($language, 'code')));
                    $languageCode = $globalFooterNormalizeLanguageCode($languageRouteCode);
                    $languageName = (string) (data_get($language, 'name') ?: strtoupper($languageCode));
                @endphp
                <a href="{{ route('cms.setLanguage', ['languageCode' => $languageRouteCode, 'redirect' => url()->full()]) }}"
                   class="{{ $globalCurrentLocale === $languageCode ? 'is-active' : '' }}"
                   data-global-language-code="{{ $languageCode }}"
                   @if($globalCurrentLocale === $languageCode) aria-current="true" @endif>
                    {{ $languageName }}
                </a>
            @endforeach
        </nav>
    @endif
</footer>
