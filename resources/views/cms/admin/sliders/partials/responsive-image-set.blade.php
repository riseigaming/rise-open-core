@php
    $blankImage = asset('cms-assets/blank-placeholder.svg');
    $responsiveImageSet = collect($responsiveImageSet ?? [
        'desktop' => ['label' => __('admin.copy.residual.desktop_default'), 'help' => __('admin.copy.residual.wide_hero_crop'), 'dimensions' => '2560x720', 'status' => 'missing', 'url' => null, 'name' => null],
        'tablet' => ['label' => __('admin.copy.residual.tablet'), 'help' => __('admin.copy.residual.compact_crop'), 'dimensions' => '1600x1020', 'status' => 'missing', 'url' => null, 'name' => null],
        'mobile' => ['label' => __('admin.copy.residual.mobile'), 'help' => __('admin.copy.residual.portrait_crop'), 'dimensions' => '1200x2280', 'status' => 'missing', 'url' => null, 'name' => null],
    ]);
    $variantInputs = $variantInputs ?? [];
    $statusClass = function ($status) {
        return match ($status) {
            'uploaded' => 'is-uploaded',
            'fallback to desktop' => 'is-fallback',
            default => 'is-missing',
        };
    };
@endphp

<div class="responsiveImageSet">
    <div class="responsiveImageSetHeader">
        <div>
            <strong>{{ __('admin.ui.responsive_image_set') }}</strong>
            <span>{{ __('admin.copy.responsive_fallback_help') }}</span>
        </div>
    </div>
    <div class="responsiveImageSetGrid">
        @foreach(['desktop', 'tablet', 'mobile'] as $variant)
            @php
                $item = $responsiveImageSet->get($variant, []);
                $input = $variantInputs[$variant] ?? [];
                $previewId = $input['preview_id'] ?? ('responsiveImagePreview-' . $variant);
            @endphp
            <div class="responsiveImageVariant">
                <img id="{{ $previewId }}" src="{{ $item['url'] ?? $blankImage }}" alt="{{ __('admin.copy.residual.preview_alt', ['label' => $item['label'] ?? $variant]) }}" loading="lazy">
                <div class="responsiveImageVariantBody">
                    <span class="responsiveImageVariantTitle">{{ __('admin_extra.2d9faadc2657', ['value1' => $item['label'] ?? ucfirst($variant)]) }}</span>
                    <span>{{ __('admin_extra.b854423b6eea', ['value1' => $item['help'] ?? '', 'value2' => $item['dimensions'] ?? '']) }}</span>
                    <span class="responsiveImageStatus {{ $statusClass($item['status'] ?? 'missing') }}">{{ match ($item['status'] ?? 'missing') { 'uploaded' => __('admin.copy.residual.uploaded'), 'fallback to desktop' => __('admin.copy.residual.fallback_to_desktop'), default => __('admin.copy.residual.missing') } }}</span>
                    @if($variant === 'desktop')
                        <span class="responsiveImageFilename">{{ __('admin.copy.primary_image_managed_here') }}</span>
                    @elseif(!empty($input['id']))
                        <button type="button" class="responsiveImageUploadBtn fileSelector" data-target="#{{ $input['id'] }}">
                            {{ __('admin.copy.residual.upload_variant', ['variant' => $item['label'] ?? ucfirst($variant)]) }}
                        </button>
                        <input type="file"
                               id="{{ $input['id'] }}"
                               @if(!empty($input['name'])) name="{{ $input['name'] }}" @endif
                               accept=".png, .jpeg, .jpg, .webp, .avif"
                               class="d-none responsiveImageInput"
                               data-preview-target="#{{ $previewId }}">
                    @endif
                    @if(!empty($item['name']))
                        <span class="responsiveImageFilename">{{ __('admin_extra.2d9faadc2657', ['value1' => $item['name']]) }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    <small class="text-muted d-block mt-2">
        {{ __('admin.copy.file_rule') }} <code>{{ __('admin_extra.26ea5ad8c44c') }}</code> {{ __('admin_extra.2d9faadc2657', ['value1' => __('cms_public.auth.social_or')]) }} <code>{{ __('admin_extra.4d08b52e1175') }}</code>; {{ __('admin.copy.responsive_file_rule') }}
    </small>
</div>
