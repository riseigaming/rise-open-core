@php
    $sections = is_array($legalPage['sections'] ?? null) ? $legalPage['sections'] : [];
@endphp

<div class="homepageContentsTextOuter legalPageContent">
    @if(!empty($legalPage['intro']))
        <p>{{ $legalPage['intro'] }}</p>
    @endif

    @foreach($sections as $section)
        <section class="legalPageSection">
            @if(!empty($section['title']))
                <h3>{{ $section['title'] }}</h3>
            @endif

            @foreach((array) ($section['body'] ?? []) as $paragraph)
                @if(is_array($paragraph))
                    @php
                        $allowedRoutes = ['cms.front.home', 'cms.information', 'cms.promotions', 'cms.front.login', 'cms.front.register', 'cms.password.request'];
                        $paragraphUrl = !empty($paragraph['route']) && in_array($paragraph['route'], $allowedRoutes, true)
                            ? route($paragraph['route']) : null;
                        if (empty($paragraph['route']) && !empty($paragraph['url'])) {
                            $candidate = $paragraph['url'];
                            if (is_string($candidate) && !preg_match('/[\\\\\x00-\x20]/', $candidate)
                                && ((str_starts_with($candidate, '/') && !str_starts_with($candidate, '//'))
                                    || (filter_var($candidate, FILTER_VALIDATE_URL) && parse_url($candidate, PHP_URL_SCHEME) === 'https'))) {
                                $paragraphUrl = $candidate;
                            }
                        }
                    @endphp
                    <p>
                        {{ $paragraph['before'] ?? '' }}
                        @if(!empty($paragraphUrl) && !empty($paragraph['label']))
                            <a href="{{ $paragraphUrl }}"@if(empty($paragraph['route']) && str_starts_with($paragraphUrl, 'http')) target="_blank" rel="noopener noreferrer" @endif>{{ $paragraph['label'] }}</a>
                        @elseif(!empty($paragraph['label']))
                            {{ $paragraph['label'] }}
                        @endif
                        {{ $paragraph['after'] ?? '' }}
                    </p>
                @else
                    <p>{{ $paragraph }}</p>
                @endif
            @endforeach

            @if(!empty($section['items']) && is_array($section['items']))
                <ul>
                    @foreach($section['items'] as $item)
                        <li>
                            @if(is_array($item) && !empty($item['url']))
                                @if(!empty($item['label']))<strong>{{ $item['label'] }}</strong> – @endif<a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer">{{ $item['text'] ?? $item['url'] }}</a>
                            @else
                                {{ $item }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif


        </section>
    @endforeach
</div>
