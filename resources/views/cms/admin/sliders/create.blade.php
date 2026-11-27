@extends("cms.admin.index")
@section("content")
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

        .sliderImageRankCard.is-passive {
            border-color: #e5e7eb;
            background: #f8fafc;
            opacity: 0.78;
        }

        .sliderImageRankCard.is-draft {
            border-style: dashed;
            border-color: #16a34a;
            background: #f0fdf4;
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

        .sliderRankHint {
            margin-top: 12px;
            font-size: 12px;
            color: #475569;
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
        $defaultDraftRank = $uploadedSliderOverview->count() + 1;
    @endphp
    <div class="container">
        @include('cms.admin.sliders.partials.placement-tabs', [
            'selectedPlacement' => $selectedPlacement,
            'placementLabels' => $placementLabels,
        ])
        <form class="row" id="createForm" action="{{route("cms.admin.sliders.store")}}" method="POST" enctype="multipart/form-data">
            @csrf
            @method("POST")
            <div class="col-sm-12">

                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6">
                                <label class="form-label">{{ __('admin.ui.display_location') }}</label>
                                <select class="form-control form-select" name="placement" required>
                                    @foreach($placementLabels as $placementKey => $placementLabel)
                                        <option value="{{ $placementKey }}" {{ $selectedPlacement === $placementKey ? "selected" : "" }}>{{ __('admin_extra.2d9faadc2657', ['value1' => $placementLabel]) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ __('admin.ui.status') }}</label>
                                <select class="form-control form-select" name="status" required>
                                    <option value="1">{{ __('admin.ui.active') }}</option>
                                    <option value="0">{{ __('admin.ui.inactive') }}</option>
                                </select>
                            </div>
                            @if(!empty($themeScopeEnabled))
                                <div class="col-sm-12 mt-3">
                                    <label class="form-label">{{ __('admin.ui.active_theme') }}</label>
                                    <div class="form-control bg-light" aria-readonly="true">{{ __('admin_extra.2d9faadc2657', ['value1' => $activeThemeLabel]) }}</div>
                                    <small class="text-muted">{{ __('admin.copy.slider_active_theme_help') }}</small>
                                </div>
                            @endif
                        </div>
                       <div class="row mt-4">
                           <input type="hidden" name="rank" id="sliderRankInput" value="{{ $defaultDraftRank }}">
                           <div class="col-sm-12">
                               <div class="sliderEditorLayout">
                                   <div class="sliderEditorMediaCard">
                                       <label class="form-label">{{ __('admin.ui.responsive_image_set') }}</label>
                                       <small class="text-muted d-block mb-2">{{ __('admin.copy.responsive_crop_help') }}</small>
                                       <div class="uploadWrapper">
                                           <img src="{{ asset('cms-assets/blank-placeholder.svg') }}" class="d-block w-100" alt="shared-slider-image" loading="lazy" style="height: 300px">
                                           <a href="javascript:void(0)" class="btn btn-success fileSelector" data-target="#sharedSliderImageInput"><i class="fa fa-file"></i>&nbsp; {{ __('admin.copy.file_select') }}</a>
                                           <input type="file" id="sharedSliderImageInput" name="shared_image" accept=".png, .jpeg, .jpg, .webp, .avif" class="d-none imageInput">
                                       </div>
                                       @include('cms.admin.sliders.partials.responsive-image-set', [
                                           'variantInputs' => [
                                               'tablet' => ['id' => 'sharedSliderImageTabletInput', 'name' => 'shared_image_tablet', 'preview_id' => 'sharedSliderImageTabletPreview'],
                                               'mobile' => ['id' => 'sharedSliderImageMobileInput', 'name' => 'shared_image_mobile', 'preview_id' => 'sharedSliderImageMobilePreview'],
                                           ],
                                       ])
                                       <div class="sliderRankHint">{{ __('admin.copy.slider_management_help') }}</div>
                                       <div class="sliderImageRankList" id="sliderImageRankList" data-rank-sortable data-slider-status-route="{{ route('cms.admin.sliders.bulkAction') }}">
                                           @foreach($uploadedSliderOverview as $overviewItem)
                                               @php($overviewStatus = (int) ($overviewItem['status'] ?? 0))
                                               <div class="sliderImageRankCard {{ $overviewStatus === 1 ? 'is-active' : 'is-passive' }}" draggable="true" data-slider-id="{{ $overviewItem['id'] }}" data-status="{{ $overviewStatus }}">
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
                                           <div class="sliderImageRankCard is-draft" id="sliderDraftRankCard" draggable="true" data-slider-id="draft-slider" hidden>
                                               <img src="{{ asset('cms-assets/blank-placeholder.svg') }}" alt="draft-slider" loading="lazy">
                                               <div class="sliderImageRankCardMeta">
                                                   <strong>{{ __('admin.ui.new_slider') }}</strong>
                                                   <span>{{ __('admin.copy.selected_image_rank_help') }}</span>
                                               </div>
                                               <span class="sliderImageRankHandle"><i class="fas fa-grip-vertical"></i></span>
                                           </div>
                                       </div>
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
                                                   <input type="text" name="langContent[{{$loop->index}}][title]" class="form-control">
                                               </di>
                                               <di class="col-sm-6 mt-1">
                                                   <label class="form-label">{{ __('admin.ui.optional_url') }}</label>
                                                   <input type="text" name="langContent[{{$loop->index}}][url]" class="form-control">
                                               </di>
                                               <di class="col-sm-12 mt-1">
                                                   <label class="form-label">{{ __('admin.ui.description') }}</label>
                                                   <textarea type="text" name="langContent[{{$loop->index}}][description]" class="form-control"></textarea>
                                               </di>
                                           </div>
                                       </div>
                                   @endforeach
                               </div>
                                   </div>
                               </div>
                           </div>


                           <div class="col-sm-12 mt-2 text-center">
                               <button type="submit" class="btn btn-success"><i class="fa fa-save mr-2"></i> &nbsp; {{ __('admin.ui.save') }}</button>
                           </div>
                       </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

    @section("script")
    <script>
        (function () {
            const rankList = document.getElementById('sliderImageRankList');
            const draftCard = document.getElementById('sliderDraftRankCard');
            const rankInput = document.getElementById('sliderRankInput');
            let draggedItem = null;

            const syncDraftRank = function () {
                if (!rankInput) {
                    return;
                }

                const cards = Array.from(rankList.querySelectorAll('.sliderImageRankCard:not([hidden])'));
                const draftIndex = cards.findIndex((card) => card.dataset.sliderId === 'draft-slider');
                const resolvedRank = draftIndex >= 0 ? draftIndex + 1 : cards.length + 1;

                rankInput.value = resolvedRank;
            };

            const bindDragEvents = function (card) {
                card.addEventListener('dragstart', function () {
                    draggedItem = card;
                    card.classList.add('is-dragging');
                });

                card.addEventListener('dragend', function () {
                    card.classList.remove('is-dragging');
                    draggedItem = null;
                    syncDraftRank();
                });
            };

            Array.from(rankList.querySelectorAll('.sliderImageRankCard')).forEach(bindDragEvents);

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

            syncDraftRank();

            document.addEventListener('change', function (event) {
                if (!event.target.matches('.imageInput')) {
                    return;
                }

                const input = event.target;
                if (input.files && input.files[0] && draftCard) {
                    const reader = new FileReader();
                    reader.onload = function (readerEvent) {
                        draftCard.hidden = false;
                        draftCard.querySelector('img').src = readerEvent.target.result;
                        rankList.appendChild(draftCard);
                        syncDraftRank();
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            });
        })();

        (function () {
            const rankList = document.getElementById('sliderImageRankList');

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

        $(document).on("change", ".imageInput", function (e){
            const input = this;
            if (input.files && input.files[0]) {
                var t = new FileReader();
                (t.onload = function(e) {
                    $(input).closest(".uploadWrapper").find("img").attr("src", e.target.result);
                }),
                    t.readAsDataURL(input.files[0]);
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

        $("#createForm").on("submit", function (e){
            e.stopPropagation();
            let route = $(this).attr("action");

            let formData = new FormData(document.getElementById("createForm"));
            let button = $(this).find('[type="submit"]');
            let buttonText = button.html();
            const resetButton = function () {
                button.html(buttonText).prop("disabled", false);
            };

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

            return false;
        });
    </script>
@endsection
