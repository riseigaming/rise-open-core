@php
    $selectedPlacement = $selectedPlacement ?? \App\Models\CmsSlider::PLACEMENT_HOME;
    $placementLabels = $placementLabels ?? [
        \App\Models\CmsSlider::PLACEMENT_HOME => __('admin.navigation.dashboard'),
    ];
@endphp

<div class="mb-3">
    <ul class="nav nav-tabs">
        @foreach($placementLabels as $placementKey => $placementLabel)
            <li class="nav-item">
                <a class="nav-link {{ $selectedPlacement === $placementKey ? 'active' : '' }}"
                   href="{{ route('cms.admin.sliders.index', ['placement' => $placementKey]) }}">
                    {{ __('admin_extra.2d9faadc2657', ['value1' => $placementLabel]) }}
                </a>
            </li>
        @endforeach
        <li class="nav-item">
            <a class="nav-link {{ $selectedPlacement === 'archive' ? 'active' : '' }}"
               href="{{ route('cms.admin.sliders.archive') }}">
                {{ __('admin.copy.archive') }}
            </a>
        </li>
    </ul>
</div>
