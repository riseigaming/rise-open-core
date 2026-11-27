@php
    $globalHeroScope = 'home';
    $globalHeroFallbackHref = route('cms.promotions');

    $globalResolveSliderImage = static function ($path, $name): ?string {
        $name = ltrim(trim((string) $name), '/');
        if ($name === '' || str_contains($name, '..') || preg_match('#^[a-z]+://#i', $name)) {
            return null;
        }

        $path = trim((string) $path, '/');
        if (str_contains($path, '..') || preg_match('#^[a-z]+://#i', $path)) {
            $path = '';
        }

        $candidates = collect([
            $path !== '' ? $path . '/' . $name : null,
            $name,
            'storage/sliders/' . $name,
        ])->filter()->unique();

        foreach ($candidates as $candidate) {
            if (file_exists(public_path($candidate))) {
                return '/' . $candidate;
            }
        }

        return null;
    };

    $globalHeroSlides = collect($sliders ?? [])->map(function ($slider) use ($globalResolveSliderImage) {
        $translations = collect(data_get($slider, 'translations', []));
        $currentTranslation = data_get($slider, 'translation');
        $imageTranslation = $translations->first(function ($translation) use ($globalResolveSliderImage) {
            return $globalResolveSliderImage(
                data_get($translation, 'image_path'),
                data_get($translation, 'image_name')
            ) !== null;
        });
        $image = $globalResolveSliderImage(data_get($slider, 'image_path'), data_get($slider, 'image_name'))
            ?: $globalResolveSliderImage(data_get($imageTranslation, 'image_path'), data_get($imageTranslation, 'image_name'));

        $textTranslation = $currentTranslation;
        if (blank(data_get($textTranslation, 'title')) && blank(data_get($textTranslation, 'description'))) {
            $textTranslation = $translations->first(function ($translation) {
                return filled(data_get($translation, 'title')) || filled(data_get($translation, 'description'));
            }) ?: $currentTranslation ?: $imageTranslation;
        }

        $href = trim((string) data_get($textTranslation, 'url'));
        if ($href !== '' && !\App\Support\B1ContentUrl::allowed($href)) {
            $href = '';
        }

        return [
            'id' => data_get($slider, 'id'),
            'image' => $image,
            'title' => trim((string) data_get($textTranslation, 'title')),
            'description' => trim((string) data_get($textTranslation, 'description')),
            'href' => $href,
        ];
    })->filter(fn (array $slide): bool => filled($slide['image']))->values();
@endphp

<section class="globalFeatureHero is-{{ $globalHeroScope }}"
         data-global-hero-slider
         data-global-slider-scope="{{ $globalHeroScope }}"
         aria-label="{{ trans('global.b1.highlights') }}">
    @forelse($globalHeroSlides as $index => $slide)
        <article class="globalFeatureSlide {{ $index === 0 ? 'is-active' : '' }}"
                 data-global-slide
                 data-global-slide-index="{{ $index }}"
                 @if($index !== 0) hidden @endif>
            <img src="{{ $slide['image'] }}" alt="" loading="{{ $index === 0 ? 'eager' : 'lazy' }}" decoding="async">
            @if(filled($slide['title']) || filled($slide['description']) || filled($slide['href']))
                <div class="globalFeatureSlideCopy">
                    @if(filled($slide['title']))
                        <h2>{{ $slide['title'] }}</h2>
                    @endif
                    @if(filled($slide['description']))
                        <p>{{ $slide['description'] }}</p>
                    @endif
                    @if(filled($slide['href']))
                        <a class="globalPrimaryAction" href="{{ $slide['href'] }}">{{ trans('global.explore') }}</a>
                    @endif
                </div>
            @endif
        </article>
    @empty
        <div class="globalFeatureHeroEmpty">
            <span class="globalEyebrow">{{ trans('global.one_account_clear_access') }}</span>
            <h2>{{ trans('global.b1.home_title') }}</h2>
            <p>{{ trans('global.b1.home_description') }}</p>
            <a class="globalSecondaryAction" href="{{ $globalHeroFallbackHref }}">{{ trans('global.view_current_offers') }}</a>
        </div>
    @endforelse

    @if($globalHeroSlides->count() > 1)
        <div class="globalFeatureHeroControls" aria-label="Slider controls">
            <button type="button" data-global-slider-previous aria-label="Previous slide">&larr;</button>
            <span data-global-slider-status aria-live="polite">1 / {{ $globalHeroSlides->count() }}</span>
            <button type="button" data-global-slider-next aria-label="Next slide">&rarr;</button>
        </div>
    @endif
</section>
