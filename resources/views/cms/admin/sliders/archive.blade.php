@extends("cms.admin.index")

@section("content")
    <style>
        .sliderArchiveGrid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }

        .sliderArchiveCard {
            border: 1px solid #dbe4f0;
            border-radius: 14px;
            background: #fff;
            padding: 12px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .sliderArchiveCard img {
            width: 100%;
            height: 132px;
            object-fit: cover;
            border-radius: 10px;
            background: #f8fafc;
        }

        .sliderArchiveCard h4 {
            margin: 10px 0 4px;
            font-size: 15px;
            color: #111827;
        }

        .sliderArchiveMeta {
            display: block;
            font-size: 12px;
            color: #64748b;
            margin-bottom: 10px;
        }

        .sliderArchiveActions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
    </style>

    <div class="container">
        @include('cms.admin.sliders.partials.placement-tabs', [
            'selectedPlacement' => 'archive',
            'placementLabels' => $placementLabels,
        ])

        <div class="card">
            <div class="card-body">
                <h4 class="mb-1">{{ __('admin.copy.slider_archive') }}</h4>
                <p class="text-muted mb-3">{{ __('admin.copy.slider_archive_help') }}</p>

                @if($archivedSliders->isEmpty())
                    <div class="alert alert-info mb-0">{{ __('admin.copy.slider_archive_empty') }}</div>
                @else
                    <div class="sliderArchiveGrid">
                        @foreach($archivedSliders as $archivedSlider)
                            <div class="sliderArchiveCard">
                                <img src="{{ $archivedSlider['image_url'] }}" alt="archive-slider-{{ $archivedSlider['id'] }}" loading="lazy">
                                <h4>{{ __('admin_extra.2d9faadc2657', ['value1' => $archivedSlider['title']]) }}</h4>
                                <span class="sliderArchiveMeta">
                                    {{ __('admin_extra.af0168acac06', ['value1' => $archivedSlider['placement_label'], 'value2' => $archivedSlider['deleted_at'] ?? '-']) }}
                                </span>
                                <div class="sliderArchiveActions">
                                    <form action="{{ $archivedSlider['restore_home_url'] }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="placement" value="{{ \App\Models\CmsSlider::PLACEMENT_HOME }}">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('admin.copy.restore_homepage') }}</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
