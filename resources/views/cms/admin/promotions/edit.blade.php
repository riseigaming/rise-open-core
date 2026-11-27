@extends("cms.admin.index")
@section("style")

<style>
        .promotionMediaGrid {
            display: grid;
            gap: 16px;
        }

        .promotionMediaCard {
            border: 1px solid #e6ebf2;
            border-radius: 16px;
            background: #fbfcfe;
            padding: 14px;
        }

        .promotionMediaLabel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .promotionMediaLabel strong {
            color: #111827;
            font-size: 13px;
            font-weight: 700;
        }

        .promotionMediaPreview {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 220px;
            padding: 12px;
            border-radius: 14px;
            border: 1px solid #e5ebf3;
            background: #ffffff;
            overflow: hidden;
        }

        .promotionMediaPreview.is-hero {
            min-height: 294px;
        }

        .promotionMediaPreview img {
            display: block;
            width: 100%;
            height: 100%;
            max-width: 100%;
            object-fit: contain;
        }

        .promotionMediaAction {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 30px;
            margin-top: 8px;
            padding: 0 10px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #d6deea;
            color: #1f3f8f;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            width: fit-content;
        }

        .promotionMediaAction:hover {
            text-decoration: none;
            background: #f4f7fd;
        }

        .promotionEditorCard {
            margin-top: 18px;
            padding: 16px;
            border: 1px solid #e6ebf2;
            border-radius: 16px;
            background: #fbfcfe;
        }

        .promotionEditorCard .nav-link {
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .promotionEditorCard .tab-pane {
            margin-top: 12px;
            padding: 14px;
            border-radius: 14px;
            background: #fff;
            border: 1px solid #eef2f7;
        }

        .promotionTranslationTools {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 0 0 12px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #e6ebf2;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(80, 103, 168, 0.03) 100%);
        }

        .promotionTranslationTools small {
            color: #667085;
            font-size: 12px;
        }

        .promotionTranslationTools .promotionAutoTranslateButton {
            flex: 0 0 auto;
            white-space: nowrap;
        }

        .countrySelect option {
            height: 21px;
            padding: 4px 6px;
        }
        .countrySelect option:checked{
            background: #3d3d5f;
            color: white;
            margin-left: 7px;
            padding: 2px;
        }
</style>
@endsection
@section("content")
    <div class="container">
        <form class="row" id="createForm" action="{{route("cms.admin.promotions.update")}}" method="POST" enctype="multipart/form-data">
            @csrf
            @method("POST")
            <input type="hidden" name="promotionId" value="{{$promotion->id}}">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">

                            <div class="col-sm-3 mt-2">
                                <label class="form-label">{{ __('admin.ui.redirect_url') }}</label>
                                <input type="text" class="form-control"  name="redirect_url" value="{{$promotion->redirect_url}}">
                            </div>

                            <div class="col-sm-2 mt-2">
                                <label class="form-label">{{ __('admin.ui.status') }}</label>
                                <select class="form-control form-select" name="status" required>
                                    <option value="active" {{$promotion->status == "active" ? "selected" : ""}}>{{ __('admin.ui.active') }}</option>
                                    <option value="passive" {{$promotion->status == "passive" ? "selected" : ""}}>{{ __('admin.ui.inactive') }}</option>
                                </select>
                            </div>
                            <div class="col-sm-2 mt-2">
                                <label class="form-label">{{ __('admin_extra.14072d76225f') }}</label>
                                <select class="form-control form-select" name="is_permanent" required>
                                    <option value="0"  {{!$promotion->is_permanent ? "selected" : ""}}>{{ __('admin.ui.timed_campaign') }}</option>
                                    <option value="1"  {{$promotion->is_permanent ? "selected" : ""}}>{{ __('admin_extra.0f3b01d4f56e') }}</option>
                                </select>
                            </div>
                            <div class="col-sm-2 mt-2">
                                <label class="form-label">{{ __('admin.copy.yellow_band') }}</label>
                                <select class="form-control form-select" name="show_in_top_line">
                                    <option value="0" {{ !(int) $promotion->show_in_top_line ? "selected" : "" }}>{{ __('admin.ui.no') }}</option>
                                    <option value="1" {{ (int) $promotion->show_in_top_line ? "selected" : "" }}>{{ __('admin.ui.yes') }}</option>
                                </select>
                            </div>
                            <div class="col-sm-3 mt-2">
                                <label class="form-label">{{ __('admin.copy.homepage_campaign') }}</label>
                                <select class="form-control form-select" name="show_on_homepage_top">
                                    <option value="0" {{ !(int) $promotion->show_on_homepage_top ? "selected" : "" }}>{{ __('admin.ui.no') }}</option>
                                    <option value="1" {{ (int) $promotion->show_on_homepage_top ? "selected" : "" }}>{{ __('admin.ui.yes') }}</option>
                                </select>
                                <small class="text-muted d-block mt-1">{{ __('admin.copy.homepage_campaign_help') }}</small>
                            </div>
                            <div class="col-sm-3 mt-2" data-end-date-field>
                                <label class="form-label">{{ __('admin.ui.end_date') }}</label>
                                <input type="datetime-local" name="end_date" class="form-control" value="{{$promotion->end_date}}">
                                <small class="text-muted d-block mt-1">{{ __('admin.copy.timed_campaign_only') }}</small>
                            </div>

                            <div class="col-sm-12 mt-2">
                                <div class="promotionMediaCard uploadWrapper">
                                    <div class="promotionMediaLabel">
                                        <strong>{{ __('admin.ui.listing_image') }}</strong>
                                    </div>
                                    <div class="promotionMediaPreview is-hero">
                                        <img src="{{$promotion->getThumbnail()}}" class="d-block" alt="listing-preview" loading="lazy">
                                    </div>
                                    <a href="javascript:void(0)" class="promotionMediaAction fileSelector" data-target="#fileInput1"><i class="fa fa-sync"></i> {{ __('admin.ui.choose_image') }}</a>
                                    <input type="file" id="fileInput1" name="thumbnail" accept=".png, .jpeg, .jpg, .webp" class="d-none imageInput">
                                </div>
                            </div>
                            <div class="col-sm-12 mt-2">
                                <div class="promotionMediaCard uploadWrapper">
                                    <div class="promotionMediaLabel">
                                        <strong>{{ __('admin.ui.detail_image') }}</strong>
                                    </div>
                                    <div class="promotionMediaPreview">
                                        <img src="{{$promotion->getDetailImage()}}" class="d-block" alt="detail-preview" loading="lazy">
                                    </div>
                                    <a href="javascript:void(0)" class="promotionMediaAction fileSelector" data-target="#fileInput2"><i class="fa fa-sync"></i> {{ __('admin.ui.choose_image') }}</a>
                                    <input type="file" id="fileInput2" name="detail_image" accept=".png, .jpeg, .jpg, .webp" class="d-none imageInput">
                                </div>
                            </div>
                        </div>
                        <div class="promotionEditorCard">
                            <div class="row">
                                <div class="col-sm-12">
                                <div class="promotionTranslationTools">
                                    <small>{{ __('admin.copy.fill_languages_help') }}</small>
                                    
                                </div>
                                <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                                    @foreach($languages as $language)
                                        <li class="nav-item">
                                            <a class="nav-link {{$loop->first ? "active" : ""}}" id="pills-{{$language["code"]}}-tab" data-bs-toggle="pill" href="#pills-{{$language["code"]}}" role="tab" aria-controls="pills-{{$language["code"]}}" aria-selected="true">{{ __('admin_extra.2d9faadc2657', ['value1' => $language["name"]]) }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="tab-content" id="pills-tabContent">
                                    @foreach($languages as $language)
                                        <div class="tab-pane fade  {{$loop->first ? "show active" : ""}}" id="pills-{{$language["code"]}}" data-lang-index="{{$loop->index}}" data-language-code="{{$language["code"]}}" role="tabpanel" aria-labelledby="pills-{{$language["code"]}}-tab">
                                            <div class="row">
                                                <input type="hidden" name="langContent[{{$loop->index}}][cms_language_id]" value="{{$language["id"]}}">

                                                <div class="col-sm-4 mt-1">
                                                    <label class="form-label">{{ __('admin.copy.listing_title') }}</label>
                                                    <input type="text" name="langContent[{{$loop->index}}][listing_title]" class="form-control" value="{{isset($transData[$language["id"]]) ? $transData[$language["id"]]["listing_title"] : ""}}">
                                                </div>
                                                <div class="col-sm-4 mt-1">
                                                    <label class="form-label">{{ __('admin.copy.detail_title') }}</label>
                                                    <input type="text" name="langContent[{{$loop->index}}][detail_title]" class="form-control" value="{{isset($transData[$language["id"]]) ? $transData[$language["id"]]["detail_title"] : ""}}">
                                                </div>
                                                <div class="col-sm-4 mt-1">
                                                    <label class="form-label">{{ __('admin.copy.button_title') }}</label>
                                                    <input type="text" name="langContent[{{$loop->index}}][redirect_button_text]" class="form-control" value="{{isset($transData[$language["id"]]) ? $transData[$language["id"]]["redirect_button_text"] : ""}}">
                                                </div>
                                                <div class="col-sm-12 mt-1">
                                                    <label class="form-label">{{ __('admin.copy.short_description') }}</label>
                                                    <textarea type="text" name="langContent[{{$loop->index}}][short_description]" class="form-control">{{ __('admin_extra.2d9faadc2657', ['value1' => isset($transData[$language["id"]]) ? $transData[$language["id"]]["short_description"] : ""]) }}</textarea>
                                                </div>
                                                <div class="col-sm-12 mt-1">
                                                    <label class="form-label">{{ __('admin.copy.detail_description') }}</label>
                                                    <textarea
                                                        name="langContent[{{$loop->index}}][description]"
                                                        id="promotion-description-{{$language["code"]}}"
                                                        class="form-control editor-input"
                                                        rows="6"
                                                    >{{ __('admin_extra.2d9faadc2657', ['value1' => isset($transData[$language["id"]]) ? $transData[$language["id"]]["description"] : ""]) }}</textarea>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
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

        $(document).on("change", ".imageInput", function () {
            const input = this;

            if (!input.files || !input.files[0]) {
                return;
            }

            const previewImage = $(input).closest(".uploadWrapper").find("img").first();
            const objectUrl = window.URL.createObjectURL(input.files[0]);
            previewImage.attr("src", objectUrl);
        });

        $("#createForm").on("submit", function (e){
            e.stopPropagation();
            let route = $(this).attr("action");
            let formData = new FormData(document.getElementById("createForm"));
            let button = $(this).find('[type="submit"]');
            let buttonHtml = button.html();

            button.html("Processing...").prop("disabled", true);
            requestWithFile("POST", route, formData, (response) => {
                toastMessage(response.status, response.message);

                if(response.status == 200){
                    setTimeout(() => {
                        window.location.href="{{ route('cms.admin.promotions.index') }}";
                    }, 700);
                }

                button.html(buttonHtml).prop("disabled", false);
            });

            return false;
        })

        function syncPromotionTypeFields() {
            const isNormalPromotion = $('[name="is_permanent"]').val() === "1";
            const isTimedPromotion = !isNormalPromotion;
            $('[name="show_in_top_line"]').prop('disabled', !isNormalPromotion);
            $('[data-end-date-field]').toggle(isTimedPromotion);

            if (!isNormalPromotion) {
                $('[name="show_in_top_line"]').val("0");
            }
        }

        $(document).on('change', '[name="is_permanent"]', syncPromotionTypeFields);
        syncPromotionTypeFields();

    </script>
@endsection
