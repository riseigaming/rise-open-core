@extends("cms.admin.index")
@section("content")
    @php
        $sharedSliderImageUrl = data_get($responsiveImageSet ?? [], 'desktop.url') ?: asset('cms-assets/blank-placeholder.svg');
    @endphp
    <style>
        .sliderEditorLayout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
            align-items: start;
        }

        .sliderEditorMediaCard,
        .sliderEditorContentCard {
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            background: #fff;
            padding: 16px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .sliderEditorMediaCard .uploadWrapper img {
            height: 220px !important;
            border-radius: 12px;
            object-fit: cover;
            background: #f8fafc;
        }

        .sliderImageRankList {
            display: grid;
            gap: 10px;
            margin-top: 18px;
        }

        .sliderImageRankCard {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border: 1px solid #dbe4f0;
            border-radius: 14px;
            background: #fff;
            cursor: grab;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }

        .sliderImageRankCard.is-current {
            border-color: #16a34a;
            box-shadow: 0 10px 24px rgba(22, 163, 74, 0.16);
        }

        .sliderImageRankCard.is-passive {
            border-color: #e5e7eb;
            background: #f8fafc;
            opacity: 0.78;
        }

        .sliderImageRankCard.is-draft {
            border-style: dashed;
            border-color: #2563eb;
            background: #eff6ff;
        }

        .sliderImageRankCard.is-dragging {
            opacity: 0.6;
            transform: scale(0.98);
        }

        .sliderImageRankCard img {
            width: 88px;
            height: 58px;
            object-fit: cover;
            border-radius: 10px;
            background: #f8fafc;
            flex: 0 0 auto;
        }

        .sliderImageRankThumb {
            display: block;
            flex: 0 0 auto;
            border-radius: 10px;
        }

        .sliderImageRankCardMeta {
            min-width: 0;
            flex: 1 1 auto;
        }

        .sliderImageRankCardMeta strong,
        .sliderImageRankCardMeta span {
            display: block;
        }

        .sliderImageRankCardMeta strong {
            font-size: 13px;
            color: #111827;
            line-height: 1.35;
        }

        .sliderImageRankCardMeta span {
            margin-top: 3px;
            font-size: 12px;
            color: #6b7280;
        }

        .sliderImageRankStatusRow {
            display: flex !important;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 7px;
        }

        .sliderImageRankStatusBadge {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            min-height: 22px;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
        }

        .sliderImageRankStatusBadge.is-active {
            background: #dcfce7;
            color: #166534;
        }

        .sliderImageRankStatusBadge.is-passive {
            background: #fee2e2;
            color: #991b1b;
        }

        .sliderImageStatusAction {
            min-height: 24px;
            padding: 3px 9px;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            background: #ffffff;
            color: #334155;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            cursor: pointer;
        }

        .sliderImageStatusAction:hover,
        .sliderImageStatusAction:focus-visible {
            border-color: #2563eb;
            color: #1d4ed8;
        }

        .sliderImageRankHandle {
            color: #94a3b8;
            font-size: 16px;
            flex: 0 0 auto;
        }

        .sliderImageRankCreateIcon {
            width: 88px;
            height: 58px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(37, 99, 235, 0.08);
            color: #2563eb;
            font-size: 20px;
            flex: 0 0 auto;
        }

        .sliderRankHint {
            margin-top: 12px;
            font-size: 12px;
            color: #475569;
        }

        .sliderCreateEntry {
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 10px 12px;
            border: 1px dashed #2563eb;
            border-radius: 14px;
            background: #eff6ff;
            color: #1d4ed8;
            cursor: pointer;
        }

        .sliderInlineCreatePanel {
            margin-top: 18px;
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            background: #fff;
            padding: 18px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .sliderInlineCreatePanelHeader {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .sliderInlineCreatePanelHeader h4 {
            margin: 0;
            font-size: 16px;
            color: #111827;
        }

        .responsiveImageSet {
            margin-top: 14px;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #f8fafc;
        }

        .responsiveImageSetHeader strong,
        .responsiveImageSetHeader span {
            display: block;
        }

        .responsiveImageSetHeader strong {
            font-size: 13px;
            color: #0f172a;
        }

        .responsiveImageSetHeader span {
            margin-top: 2px;
            font-size: 12px;
            color: #64748b;
        }

        .responsiveImageSetGrid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .responsiveImageVariant {
            min-width: 0;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            overflow: hidden;
        }

        .responsiveImageVariant img {
            width: 100%;
            height: 64px;
            object-fit: cover;
            background: #eef2f7;
        }

        .responsiveImageVariantBody {
            display: grid;
            gap: 4px;
            padding: 8px;
            font-size: 11px;
            color: #64748b;
        }

        .responsiveImageVariantTitle {
            font-weight: 800;
            color: #0f172a;
        }

        .responsiveImageStatus {
            justify-self: start;
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .responsiveImageStatus.is-uploaded {
            background: #dcfce7;
            color: #166534;
        }

        .responsiveImageStatus.is-fallback {
            background: #fef3c7;
            color: #92400e;
        }

        .responsiveImageStatus.is-missing {
            background: #fee2e2;
            color: #991b1b;
        }

        .responsiveImageUploadBtn {
            justify-self: start;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            background: #fff;
            color: #334155;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 8px;
        }

        .responsiveImageFilename {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 991px) {
            .responsiveImageSetGrid {
                grid-template-columns: 1fr;
            }
        }

    </style>
    @php
        $uploadedSliderOverview = collect($sliderOverview ?? [])->filter(fn ($item) => !empty($item['has_image']))->values();
    @endphp
    <div class="container">
        @include('cms.admin.sliders.partials.placement-tabs', [
            'selectedPlacement' => $selectedPlacement,
            'placementLabels' => $placementLabels,
        ])
        <form class="row" id="createForm" action="{{route("cms.admin.sliders.update")}}" method="POST" enctype="multipart/form-data">
            @csrf
            @method("POST")
            <div class="col-sm-12">
                <input type="hidden" name="sliderId" value="{{$slider->id}}">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6">
                                <label class="form-label">{{ __('admin.ui.display_location') }}</label>
                                <select class="form-control form-select" name="placement" id="sliderPlacementInput" required>
                                    @foreach($placementLabels as $placementKey => $placementLabel)
                                        <option value="{{ $placementKey }}" {{ $selectedPlacement === $placementKey ? "selected" : "" }}>{{ __('admin_extra.2d9faadc2657', ['value1' => $placementLabel]) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ __('admin.ui.status') }}</label>
                                <select class="form-control form-select" name="status" id="sliderStatusInput" required>
                                    <option value="1" {{$slider->status == 1 ? "selected" : ""}}>{{ __('admin.ui.active') }}</option>
                                    <option value="0" {{$slider->status == 0 ? "selected" : ""}}>{{ __('admin.ui.inactive') }}</option>
                                </select>
                            </div>
                            @if(!empty($themeScopeEnabled))
                                <div class="col-sm-12 mt-3">
                                    <label class="form-label">{{ __('admin.ui.active_theme') }}</label>
                                    <div class="form-control bg-light" aria-readonly="true">{{ __('admin_extra.2d9faadc2657', ['value1' => $activeThemeLabel]) }}</div>
                                    <small class="text-muted">{{ __('admin.copy.slider_theme_scope') }}</small>
                                </div>
                            @endif
                        </div>
                       <div class="row mt-4">
                           <input type="hidden" name="rank" id="sliderRankInput" value="{{ max(1, (int) $slider->rank) }}">
                           <div class="col-sm-12">
                               <div class="sliderEditorLayout">
                                   <div class="sliderEditorMediaCard">
                                       <label class="form-label">{{ __('admin.ui.responsive_image_set') }}</label>
                                       <small class="text-muted d-block mb-2">{{ __('admin.copy.responsive_crop_help') }}</small>
                                       <div class="uploadWrapper">
                                           <img src="{{ $sharedSliderImageUrl }}" class="d-block w-100" alt="shared-slider-image" loading="lazy" style="height: 300px">
                                           <a href="javascript:void(0)" class="btn btn-success fileSelector" data-target="#sharedSliderImageInput"><i class="fa fa-file"></i>&nbsp; {{ __('admin.copy.file_select') }}</a>
                                           <input type="file" id="sharedSliderImageInput" name="shared_image" accept=".png, .jpeg, .jpg, .webp, .avif" class="d-none imageInput">
                                       </div>
                                       @include('cms.admin.sliders.partials.responsive-image-set', [
                                           'responsiveImageSet' => $responsiveImageSet ?? [],
                                           'variantInputs' => [
                                               'tablet' => ['id' => 'sharedSliderImageTabletInput', 'name' => 'shared_image_tablet', 'preview_id' => 'sharedSliderImageTabletPreview'],
                                               'mobile' => ['id' => 'sharedSliderImageMobileInput', 'name' => 'shared_image_mobile', 'preview_id' => 'sharedSliderImageMobilePreview'],
                                           ],
                                       ])
                                       @if(filled($sharedSliderImageName ?? null))
                                           <div class="form-check mt-3">
                                               <input class="form-check-input" type="checkbox" value="1" id="removeSharedSliderImage" name="remove_shared_image">
                                               <label class="form-check-label" for="removeSharedSliderImage">
                                                   {{ __('admin.copy.remove_current_image') }}
                                               </label>
                                           </div>
                                       @endif
                                       <div class="sliderRankHint">{{ __('admin.copy.slider_management_help') }}</div>
                                       <div class="sliderImageRankList" id="sliderImageRankList" data-rank-sortable data-slider-status-route="{{ route('cms.admin.sliders.bulkAction') }}">
                                           @foreach($uploadedSliderOverview as $overviewItem)
                                               @php($overviewStatus = (int) ($overviewItem['status'] ?? 0))
                                               <div class="sliderImageRankCard {{ (int) $overviewItem['id'] === (int) $slider->id ? 'is-current' : '' }} {{ $overviewStatus === 1 ? 'is-active' : 'is-passive' }}" draggable="true" data-slider-id="{{ $overviewItem['id'] }}" data-status="{{ $overviewStatus }}">
                                                   <a href="{{ $overviewItem['edit_url'] }}" class="sliderImageRankThumb" aria-label="{{ __('admin.copy.edit_item', ['title' => $overviewItem['title']]) }}">
                                                       <img src="{{ $overviewItem['image_url'] }}" alt="slider-{{ $overviewItem['id'] }}" loading="lazy">
                                                   </a>
                                                   <div class="sliderImageRankCardMeta">
                                                       <strong>{{ __('admin_extra.2d9faadc2657', ['value1' => $overviewItem['title']]) }}</strong>
                                                       <span>{{ __('admin.copy.rank_number', ['rank' => $overviewItem['rank']]) }}</span>
                                                       <span class="sliderImageRankStatusRow">
                                                           <span class="sliderImageRankStatusBadge {{ $overviewStatus === 1 ? 'is-active' : 'is-passive' }}">{{ __('admin_extra.2d9faadc2657', ['value1' => $overviewItem['status_label'] ?? ($overviewStatus === 1 ? __('admin.copy.residual.active') : __('admin.copy.residual.inactive'))]) }}</span>
                                                           <button type="button"
                                                                   class="sliderImageStatusAction"
                                                                   data-slider-id="{{ $overviewItem['id'] }}"
                                                                   data-action="{{ $overviewItem['toggle_action'] ?? ($overviewStatus === 1 ? 'passive' : 'active') }}"
                                                                   data-next-status="{{ $overviewItem['toggle_status'] ?? ($overviewStatus === 1 ? 0 : 1) }}">
                                                               {{ $overviewItem['toggle_label'] ?? ($overviewStatus === 1 ? __('admin.copy.deactivate') : __('admin.copy.residual.activate')) }}
                                                           </button>
                                                       </span>
                                                   </div>
                                                   <span class="sliderImageRankHandle"><i class="fas fa-grip-vertical"></i></span>
                                               </div>
                                           @endforeach
                                           <div class="sliderImageRankCard is-draft" id="inlineCreateDraftCard" draggable="true" data-slider-id="draft-slider" hidden>
                                               <img src="{{ asset('cms-assets/blank-placeholder.svg') }}" alt="draft-slider" loading="lazy">
                                               <div class="sliderImageRankCardMeta">
                                                   <strong>{{ __('admin.ui.new_slider') }}</strong>
                                                   <span>{{ __('admin.copy.new_record_order') }}</span>
                                               </div>
                                               <span class="sliderImageRankHandle"><i class="fas fa-grip-vertical"></i></span>
                                           </div>
                                       </div>
                                       <button type="button" class="sliderCreateEntry" id="sliderInlineCreateToggle">
                                           <span class="sliderImageRankCreateIcon"><i class="fas fa-plus"></i></span>
                                           <span class="sliderImageRankCardMeta">
                                               <strong>{{ __('admin.ui.new_slider') }}</strong>
                                               <span>{{ __('admin.copy.create_without_changing_slider') }}</span>
                                           </span>
                                       </button>
                                   </div>
                                   <div class="sliderEditorContentCard">
                               <label class="form-label">{{ __('admin.ui.slider_texts') }}</label>
                               <small class="text-muted d-block mb-3">{{ __('admin.copy.selected_hero_help', ['placement' => $placementLabels[$selectedPlacement] ?? __('admin.copy.selected')]) }}</small>
                               <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                                  @foreach($languages as $language)
                                       <li class="nav-item">
                                           <a class="nav-link {{$loop->first ? "active" : ""}}" id="pills-{{$language["code"]}}-tab" data-bs-toggle="pill" href="#pills-{{$language["code"]}}" role="tab" aria-controls="pills-{{$language["code"]}}" aria-selected="true">{{ __('admin_extra.2d9faadc2657', ['value1' => $language["name"]]) }}</a>
                                       </li>
                                  @endforeach
                               </ul>
                               <div class="tab-content" id="pills-tabContent">
                                   @foreach($languages as $language)
                                       <div class="tab-pane fade  {{$loop->first ? "show active" : ""}}" id="pills-{{$language["code"]}}" role="tabpanel" aria-labelledby="pills-{{$language["code"]}}-tab">
                                           <div class="row">
                                               <input type="hidden" name="langContent[{{$loop->index}}][cms_language_id]" value="{{$language["id"]}}">
                                               <di class="col-sm-6 mt-1">
                                                   <label class="form-label">{{ __('admin.ui.hero_title') }}</label>
                                                   <input type="text" name="langContent[{{$loop->index}}][title]" class="form-control" value="{{isset($transData[$language["id"]]) ? $transData[$language["id"]]["title"] : ""}}">
                                               </di>
                                               <di class="col-sm-6 mt-1">
                                                   <label class="form-label">{{ __('admin.ui.optional_url') }}</label>
                                                   <input type="text" name="langContent[{{$loop->index}}][url]" class="form-control" value="{{isset($transData[$language["id"]]) ? $transData[$language["id"]]["url"] : ""}}">
                                               </di>
                                               <di class="col-sm-12 mt-1">
                                                   <label class="form-label">{{ __('admin.ui.description') }}</label>
                                                   <textarea type="text" name="langContent[{{$loop->index}}][description]" class="form-control">{{ __('admin_extra.2d9faadc2657', ['value1' => isset($transData[$language["id"]]) ? $transData[$language["id"]]["description"] : ""]) }}</textarea>
                                               </di>
                                           </div>
                                       </div>
                                   @endforeach
                                       </div>
                                   </div>
                               </div>
                           </div>

                           <div class="col-sm-12 mt-2 text-center">
                               <button type="submit" class="btn btn-success" id="sliderEditSubmitButton"><i class="fa fa-save mr-2"></i> &nbsp; {{ __('admin.ui.save') }}</button>
                           </div>
                       </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="row mt-3">
            <div class="col-sm-12">
                <div class="sliderInlineCreatePanel" id="sliderInlineCreatePanel" hidden>
                    <div class="sliderInlineCreatePanelHeader">
                        <div>
                            <h4>{{ __('admin.ui.new_slider') }}</h4>
                            <small class="text-muted d-block">{{ __('admin.copy.new_slider_help') }}</small>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="sliderInlineCreateClose">{{ __('admin.ui.close') }}</button>
                    </div>
                    <div id="inlineSliderCreateForm" data-action="{{ route('cms.admin.sliders.store') }}">
                        <input type="hidden" id="inlineSliderCreateRankInput" value="{{ $uploadedSliderOverview->count() + 1 }}">
                        <div class="row">
                            <div class="col-sm-12 mb-3">
                                <label class="form-label">{{ __('admin.ui.display_location') }}</label>
                                <select class="form-control form-select" id="inlineSliderCreatePlacement" required>
                                    @foreach($placementLabels as $placementKey => $placementLabel)
                                        <option value="{{ $placementKey }}" {{ $selectedPlacement === $placementKey ? "selected" : "" }}>{{ __('admin_extra.2d9faadc2657', ['value1' => $placementLabel]) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-12 mb-3">
                                <label class="form-label">{{ __('admin.ui.status') }}</label>
                                <select class="form-control form-select" id="inlineSliderCreateStatus" required>
                                    <option value="1">{{ __('admin.ui.active') }}</option>
                                    <option value="0">{{ __('admin.ui.inactive') }}</option>
                                </select>
                            </div>
                            @if(!empty($themeScopeEnabled))
                                <div class="col-sm-12 mb-3">
                                    <label class="form-label">{{ __('admin.ui.active_theme') }}</label>
                                    <div class="form-control bg-light" aria-readonly="true">{{ __('admin_extra.2d9faadc2657', ['value1' => $activeThemeLabel]) }}</div>
                                    <small class="text-muted">{{ __('admin.copy.new_slider_active_theme') }}</small>
                                </div>
                            @endif
                            <div class="col-sm-12 mb-3">
                                <label class="form-label">{{ __('admin.copy.new_slider_image') }}</label>
                                <div class="uploadWrapper">
                                    <img src="{{ asset('cms-assets/blank-placeholder.svg') }}" class="d-block w-100" alt="inline-create-slider-image" loading="lazy" style="height: 240px">
                                    <a href="javascript:void(0)" class="btn btn-success fileSelector" data-target="#inlineCreateSharedImageInput"><i class="fa fa-file"></i>&nbsp; {{ __('admin.copy.file_select') }}</a>
                                    <input type="file" id="inlineCreateSharedImageInput" accept=".png, .jpeg, .jpg, .webp, .avif" class="d-none inlineSliderCreateImageInput">
                                </div>
                                @include('cms.admin.sliders.partials.responsive-image-set', [
                                    'variantInputs' => [
                                        'tablet' => ['id' => 'inlineCreateSharedImageTabletInput', 'preview_id' => 'inlineCreateSharedImageTabletPreview'],
                                        'mobile' => ['id' => 'inlineCreateSharedImageMobileInput', 'preview_id' => 'inlineCreateSharedImageMobilePreview'],
                                    ],
                                ])
                            </div>
                            <div class="col-sm-12">
                                <label class="form-label">{{ __('admin.ui.slider_texts') }}</label>
                                <small class="text-muted d-block mb-3">{{ __('admin.copy.selected_hero_help', ['placement' => __('admin.copy.selected')]) }}</small>
                                <ul class="nav nav-pills mb-3" id="inline-create-pills-tab" role="tablist">
                                    @foreach($languages as $language)
                                        <li class="nav-item">
                                            <a class="nav-link {{ $loop->first ? 'active' : '' }}" id="inline-create-pills-{{ $language['code'] }}-tab" data-bs-toggle="pill" href="#inline-create-pills-{{ $language['code'] }}" role="tab" aria-controls="inline-create-pills-{{ $language['code'] }}" aria-selected="true">{{ __('admin_extra.2d9faadc2657', ['value1' => $language['name']]) }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="tab-content" id="inline-create-pills-tabContent">
                                    @foreach($languages as $language)
                                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="inline-create-pills-{{ $language['code'] }}" role="tabpanel" aria-labelledby="inline-create-pills-{{ $language['code'] }}-tab">
                                            <div class="row">
                                                <input type="hidden" data-create-lang-id="{{ $loop->index }}" value="{{$language["id"]}}">
                                                <div class="col-sm-6 mt-1">
                                                    <label class="form-label">{{ __('admin.ui.hero_title') }}</label>
                                                    <input type="text" data-create-lang-title="{{ $loop->index }}" class="form-control">
                                                </div>
                                                <div class="col-sm-6 mt-1">
                                                    <label class="form-label">{{ __('admin.ui.optional_url') }}</label>
                                                    <input type="text" data-create-lang-url="{{ $loop->index }}" class="form-control">
                                                </div>
                                                <div class="col-sm-12 mt-1">
                                                    <label class="form-label">{{ __('admin.ui.description') }}</label>
                                                    <textarea data-create-lang-description="{{ $loop->index }}" class="form-control"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 text-center">
                            <button type="button" class="btn btn-success" id="inlineSliderCreateSubmit"><i class="fa fa-save mr-2"></i> &nbsp; {{ __('admin.ui.save') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section("script")
    <script>
        (function () {
            const rankList = document.getElementById('sliderImageRankList');
            const currentSliderId = "{{ (int) $slider->id }}";
            const rankInput = document.getElementById('sliderRankInput');
            let draggedItem = null;

            if (!rankList) {
                return;
            }

            const syncCurrentRank = function () {
                const cards = Array.from(rankList.querySelectorAll('.sliderImageRankCard:not([hidden])'))
                    .filter((card) => card.dataset.sliderId !== 'draft-slider');
                const currentIndex = cards.findIndex((card) => card.dataset.sliderId === currentSliderId);

                if (currentIndex === -1) {
                    return;
                }

                const resolvedRank = currentIndex + 1;
                if (rankInput) {
                    rankInput.value = resolvedRank;
                }
            };

            Array.from(rankList.querySelectorAll('.sliderImageRankCard')).forEach(function (card) {
                card.addEventListener('dragstart', function () {
                    draggedItem = card;
                    card.classList.add('is-dragging');
                });

                card.addEventListener('dragend', function () {
                    card.classList.remove('is-dragging');
                    draggedItem = null;
                    syncCurrentRank();
                });
            });

            rankList.addEventListener('dragover', function (event) {
                event.preventDefault();

                const afterElement = Array.from(rankList.querySelectorAll('.sliderImageRankCard:not(.is-dragging):not([hidden])'))
                    .find((card) => event.clientY <= card.getBoundingClientRect().top + (card.offsetHeight / 2));

                if (!draggedItem) {
                    return;
                }

                if (!afterElement) {
                    rankList.appendChild(draggedItem);
                    return;
                }

                rankList.insertBefore(draggedItem, afterElement);
            });

            syncCurrentRank();
        })();

        (function () {
            const toggleButton = document.getElementById('sliderInlineCreateToggle');
            const closeButton = document.getElementById('sliderInlineCreateClose');
            const panel = document.getElementById('sliderInlineCreatePanel');
            const draftCard = document.getElementById('inlineCreateDraftCard');
            const rankList = document.getElementById('sliderImageRankList');
            const rankInput = document.getElementById('inlineSliderCreateRankInput');
            const createForm = document.getElementById('inlineSliderCreateForm');
            const createSubmitButton = document.getElementById('inlineSliderCreateSubmit');
            const createPlacementInput = document.getElementById('inlineSliderCreatePlacement');
            const createStatusInput = document.getElementById('inlineSliderCreateStatus');
            const createImageInput = document.getElementById('inlineCreateSharedImageInput');

            if (!toggleButton || !panel || !draftCard || !rankList || !rankInput || !createForm || !createSubmitButton || !createPlacementInput || !createStatusInput || !createImageInput) {
                return;
            }

            const syncDraftRank = function () {
                const cards = Array.from(rankList.querySelectorAll('.sliderImageRankCard:not([hidden])'));
                const draftIndex = cards.findIndex((card) => card.dataset.sliderId === 'draft-slider');
                rankInput.value = draftIndex >= 0 ? draftIndex + 1 : cards.length + 1;
            };

            const openPanel = function () {
                panel.hidden = false;
                draftCard.hidden = false;
                rankList.appendChild(draftCard);
                syncDraftRank();
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            };

            const closePanel = function () {
                panel.hidden = true;
                draftCard.hidden = true;
                createPlacementInput.value = "{{ $selectedPlacement }}";
                createStatusInput.value = '1';
                createImageInput.value = '';
                const createTabletInput = document.getElementById('inlineCreateSharedImageTabletInput');
                const createMobileInput = document.getElementById('inlineCreateSharedImageMobileInput');
                const createTabletPreview = document.getElementById('inlineCreateSharedImageTabletPreview');
                const createMobilePreview = document.getElementById('inlineCreateSharedImageMobilePreview');
                if (createTabletInput) {
                    createTabletInput.value = '';
                }
                if (createMobileInput) {
                    createMobileInput.value = '';
                }
                if (createTabletPreview) {
                    createTabletPreview.src = "{{ asset('cms-assets/blank-placeholder.svg') }}";
                }
                if (createMobilePreview) {
                    createMobilePreview.src = "{{ asset('cms-assets/blank-placeholder.svg') }}";
                }
                createForm.querySelectorAll('input[type="text"], textarea').forEach(function (field) {
                    field.value = '';
                });
                draftCard.querySelector('img').src = "{{ asset('cms-assets/blank-placeholder.svg') }}";
            };

            toggleButton.addEventListener('click', openPanel);
            closeButton.addEventListener('click', closePanel);

            rankList.addEventListener('dragend', function () {
                if (!panel.hidden) {
                    syncDraftRank();
                }
            });

            $(".inlineSliderCreateImageInput").change(function (){
                const input = this;
                if (input.files && input.files[0]) {
                    var t = new FileReader();
                    (t.onload = function(e) {
                        $(input).closest(".uploadWrapper").find("img").attr("src", e.target.result);
                        draftCard.querySelector('img').src = e.target.result;
                    }),
                        t.readAsDataURL(input.files[0]);
                }
            });

            $("#inlineSliderCreateSubmit").on("click", function (){
                let route = createForm.dataset.action;
                let formData = new FormData();
                let button = $(this);
                let buttonText = button.html();
                const resetButton = function () {
                    button.html(buttonText).prop("disabled", false);
                };

                formData.append('_token', '{{ csrf_token() }}');
                formData.append('placement', createPlacementInput.value || '{{ $selectedPlacement }}');
                formData.append('rank', rankInput.value || '1');
                formData.append('status', createStatusInput.value || '1');
                if (createImageInput.files && createImageInput.files[0]) {
                    formData.append('shared_image', createImageInput.files[0]);
                }

                const createTabletInput = document.getElementById('inlineCreateSharedImageTabletInput');
                const createMobileInput = document.getElementById('inlineCreateSharedImageMobileInput');
                if (createTabletInput && createTabletInput.files && createTabletInput.files[0]) {
                    formData.append('shared_image_tablet', createTabletInput.files[0]);
                }
                if (createMobileInput && createMobileInput.files && createMobileInput.files[0]) {
                    formData.append('shared_image_mobile', createMobileInput.files[0]);
                }

                createForm.querySelectorAll('[data-create-lang-id]').forEach(function (field) {
                    const index = field.dataset.createLangId;
                    formData.append(`langContent[${index}][cms_language_id]`, field.value || '');
                });
                createForm.querySelectorAll('[data-create-lang-title]').forEach(function (field) {
                    const index = field.dataset.createLangTitle;
                    formData.append(`langContent[${index}][title]`, field.value || '');
                });
                createForm.querySelectorAll('[data-create-lang-url]').forEach(function (field) {
                    const index = field.dataset.createLangUrl;
                    formData.append(`langContent[${index}][url]`, field.value || '');
                });
                createForm.querySelectorAll('[data-create-lang-description]').forEach(function (field) {
                    const index = field.dataset.createLangDescription;
                    formData.append(`langContent[${index}][description]`, field.value || '');
                });

                button.html(@json(__('admin.copy.processing'))).prop("disabled", true);
                requestWithFile("POST", route, formData, (response) => {
                    toastMessage(response.status, response.message);

                    if(response.status == 200){
                        setTimeout(() => {
                            window.location.href = response.redirect_url || "{{route("cms.admin.sliders.index")}}";
                        }, 700);
                    }

                    resetButton();
                }, null, (xhr) => {
                    warningToast(sliderUploadErrorMessage(xhr));
                    resetButton();
                });
            });
        })();

        (function () {
            const rankList = document.getElementById('sliderImageRankList');
            const statusInput = document.getElementById('sliderStatusInput');
            const currentSliderId = "{{ (int) $slider->id }}";

            if (!rankList) {
                return;
            }

            const route = rankList.dataset.sliderStatusRoute;

            const syncCardStatus = function (card, nextStatus) {
                const resolvedStatus = Number(nextStatus) === 1 ? 1 : 0;
                const badge = card.querySelector('.sliderImageRankStatusBadge');
                const button = card.querySelector('.sliderImageStatusAction');

                card.dataset.status = String(resolvedStatus);
                card.classList.toggle('is-active', resolvedStatus === 1);
                card.classList.toggle('is-passive', resolvedStatus === 0);

                if (badge) {
                    badge.textContent = resolvedStatus === 1 ? @json(__('admin.ui.active')) : @json(__('admin.ui.inactive'));
                    badge.classList.toggle('is-active', resolvedStatus === 1);
                    badge.classList.toggle('is-passive', resolvedStatus === 0);
                }

                if (button) {
                    button.dataset.action = resolvedStatus === 1 ? 'passive' : 'active';
                    button.dataset.nextStatus = resolvedStatus === 1 ? '0' : '1';
                    button.textContent = resolvedStatus === 1 ? @json(__('admin.copy.deactivate')) : @json(__('admin.ui.activate'));
                    button.disabled = false;
                }

                if (statusInput && String(card.dataset.sliderId) === currentSliderId) {
                    statusInput.value = String(resolvedStatus);
                }
            };

            rankList.addEventListener('pointerdown', function (event) {
                if (event.target.closest('.sliderImageStatusAction')) {
                    event.stopPropagation();
                }
            });

            rankList.addEventListener('click', function (event) {
                const button = event.target.closest('.sliderImageStatusAction');

                if (!button || !route) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                const card = button.closest('.sliderImageRankCard');
                const sliderId = button.dataset.sliderId || card?.dataset.sliderId;
                const action = button.dataset.action;
                const nextStatus = button.dataset.nextStatus;

                if (!card || !sliderId || !action) {
                    return;
                }

                button.disabled = true;

                request("POST", route, {
                    ids: sliderId,
                    action: action,
                }, (response) => {
                    toastMessage(response.status, response.message);

                    if (Number(response.status) === 201 || Number(response.status) === 200) {
                        syncCardStatus(card, nextStatus);
                        return;
                    }

                    button.disabled = false;
                });
            });
        })();

        $(".imageInput").change(function (){
            const input = this;
            if (input.files && input.files[0]) {
                var t = new FileReader();
                (t.onload = function(e) {
                    $(input).closest(".uploadWrapper").find("img").attr("src", e.target.result);
                    const currentCard = $("#sliderImageRankList").find('[data-slider-id="{{ (int) $slider->id }}"]');
                    if (currentCard.length) {
                        currentCard.prop("hidden", false).find("img").attr("src", e.target.result);
                    }
                }),
                    t.readAsDataURL(input.files[0]);

                $("#removeSharedSliderImage").prop("checked", false);
            }
        });

        $(document).on("change", ".responsiveImageInput", function (){
            const input = this;
            const previewTarget = input.getAttribute("data-preview-target");
            if (!previewTarget || !input.files || !input.files[0]) {
                return;
            }

            var reader = new FileReader();
            reader.onload = function(event) {
                $(previewTarget).attr("src", event.target.result);
            };
            reader.readAsDataURL(input.files[0]);
        });

        function sliderUploadErrorMessage(xhr) {
            if (xhr && xhr.status === 413) {
                return @json(__('admin.copy.image_upload_limit'));
            }

            if (xhr && xhr.status === 419) {
                return @json(__('admin.copy.session_expired_retry'));
            }

            if (xhr && xhr.responseJSON) {
                if (xhr.responseJSON.errors) {
                    const firstKey = Object.keys(xhr.responseJSON.errors)[0];
                    const firstError = firstKey ? xhr.responseJSON.errors[firstKey] : null;

                    if (Array.isArray(firstError) && firstError.length > 0) {
                        return firstError.join(", ");
                    }

                    if (typeof firstError === "string") {
                        return firstError;
                    }
                }

                if (xhr.responseJSON.message) {
                    return xhr.responseJSON.message;
                }
            }

            return @json(__('admin.copy.slider_upload_failed'));
        }

        $("#removeSharedSliderImage").on("change", function (){
            const previewImage = $(this).closest(".sliderEditorMediaCard").find(".uploadWrapper img");
            const currentCard = $("#sliderImageRankList").find('[data-slider-id="{{ (int) $slider->id }}"]');
            if ($(this).is(":checked")) {
                previewImage.attr("src", "{{ asset('cms-assets/blank-placeholder.svg') }}");
                currentCard.prop("hidden", true);
                return;
            }

            previewImage.attr("src", "{{ $sharedSliderImageUrl }}");
            currentCard.prop("hidden", false);
        });

        $("#createForm").on("submit", function (e){
            e.stopPropagation();
            let route = $(this).attr("action");

            let formData = new FormData(document.getElementById("createForm"));
            let button = $('#sliderEditSubmitButton');
            let buttonText = button.html();
            const resetButton = function () {
                button.html(buttonText).prop("disabled", false);
            };

            button.html(@json(__('admin.copy.processing'))).prop("disabled", true);
            requestWithFile("POST", route, formData, (response) => {
                toastMessage(response.status, response.message);

                if(response.status == 200){
                    setTimeout(() => {
                        window.location.reload();
                    }, 700);
                }

                resetButton();

            }, null, (xhr) => {
                warningToast(sliderUploadErrorMessage(xhr));
                resetButton();
            });

            return false;
        });

        $(".uploadWrapper").find("img").on("error", function (){
            $(this).attr("src", "{{ asset('cms-assets/blank-placeholder.svg') }}");
        });
    </script>
@endsection
