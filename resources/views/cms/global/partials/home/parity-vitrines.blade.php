@php
    $globalPromos = collect($topThreePromotions ?? [])->filter(fn ($promo) =>
        filled(data_get($promo, 'id'))
    )->take(3)->values();
@endphp
@if($globalPromos->isNotEmpty())
<section class="globalParityPanel globalParityPromos" data-global-mobile-promos aria-label="{{ trans('cms_public.promotions.mobile_banners_label') }}">
    @foreach($globalPromos as $promo)
        <a class="globalParityPromoCard" href="{{ route('cms.promotions.detail', ['id' => data_get($promo, 'id')]) }}">
            @if(is_object($promo) && method_exists($promo, 'getThumbnail') && filled($promo->getThumbnail()))
                <img src="{{ $promo->getThumbnail() }}" alt="" loading="lazy" decoding="async">
            @endif
            <strong>{{ data_get($promo, 'translation.listing_title', trans('cms_public.promotions.fallback_title')) }}</strong>
        </a>
    @endforeach
</section>
@endif
