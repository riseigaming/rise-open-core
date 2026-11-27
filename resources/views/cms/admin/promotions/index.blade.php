@extends("cms.admin.index")
@section("content")
    <div class="container">
        <input type="hidden" id="selectedIds" value="">
        <input type="hidden" id="bulkActionRoute" value="{{route("cms.admin.promotions.bulkAction")}}">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <div class="toolbar">
                            <a href="{{ route("cms.admin.promotions.create") }}" class="btn btn-outline-primary pull-left" style="margin-right: 5px;"> <i class="fa fa-plus"></i>  {{ __('admin.ui.new_record') }}</a>
                            <a href="{{ route("cms.admin.promotions.order") }}" class="btn btn-outline-secondary pull-left" style="margin-right: 5px;"> <i class="fa fa-sort"></i>  {{ __('admin.copy.sort') }}</a>
                            <button type="button" class="actionButton btn btn-primary" data-type="active" id="do_active" disabled>{{ __('admin.ui.activate') }}</button>
                            <button type="button" class="actionButton btn btn-warning" data-type="passive" id="do_passive" disabled>{{ __('admin.ui.deactivate') }}</button>
                            <button type="button" class="actionButton btn btn-danger" data-type="delete" id="do_deleted" disabled>{{ __('admin.ui.delete') }}</button>
                        </div>
                        <table id="table1" data-locale="{{ app()->getLocale() === 'tr' ? 'tr-TR' : 'en-US' }}" data-toolbar=".toolbar" data-search="true"
                               class="bootstrap-table bootstrap-table-1"
                               data-show-refresh="true" data-show-toggle="true" data-pagination="true" data-resizable="true"
                               data-sortable="true" data-show-search-clear-button="true" data-side-pagination="server" data-click-to-select="true"
                               data-page-list="[10, 25, 50, 100, 200, 250]"
                               data-url="{{ route("cms.admin.promotions.getAjaxList") }}">
                            <thead>
                            <tr>
                                <th data-formatter="checkboxFormatter" data-field="id">#</th>
                                <th data-sortable="true" data-field="thumbnail" data-formatter="thumbnailFormatter">{{ __('admin.ui.thumbnail') }}</th>
                                <th data-sortable="true" data-field="detail_image" data-formatter="detailImageFormatter">{{ __('admin.ui.detail_image') }}</th>
                                <th data-sortable="true" data-field="listing_title">{{ __('admin.ui.name') }}</th>
                                <th data-sortable="true" data-field="detail_title">{{ __('admin.ui.description') }}</th>
                                <th data-sortable="true" data-field="redirect_url">{{ __('admin.ui.redirect_url') }}</th>
                                <th data-sortable="true" data-field="campaignTypeText">{{ __('admin.ui.type') }}</th>
                                <th data-sortable="true" data-field="topLineText">{{ __('admin.copy.yellow_band_short') }}</th>
                                <th data-sortable="true" data-field="status" data-formatter="statusFormatter">{{ __('admin.ui.status') }}</th>
                                <th data-field="id" data-formatter="buttonFormatter" data-click-to-select="false">{{ __('admin.ui.operations') }}</th>
                            </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section("script")
    <script>

        function edit(id){
            let currentBtn = $("#editBtn-"+id);
            let route = currentBtn.data("route");
            window.location.href=route;
        }
        function buttonFormatter(id){
            let editBtn =  '<button type="button" data-route="/cms/admin/promotions/edit/'+id+'" id="editBtn-'+id+'" onclick="edit('+id+')" class="btn btn-outline-success editBtn" data-id="'+id+'"><i class="fa fa-edit"></i>  {{ __('admin.ui.edit') }}</button>';
            let deleteBtn = ' <button type="button" id="deleteBtn-'+id+'" class="btn btn-outline-danger deleteBtn" data-id="'+id+'"><i class="fa fa-trash-alt"></i>  {{ __('admin.ui.delete') }}</button>';

            return editBtn + deleteBtn;
        }

        function thumbnailFormatter(thumbnail, row){
            if (!thumbnail) {
                return `<div style="position:relative;display:inline-block;line-height:0;"><img src="/cms-assets/blank-placeholder.svg" style="width: 75px;"></div>`;
            }

            const normalizedThumbnail = String(thumbnail);
            const src = normalizedThumbnail.startsWith("http")
                ? normalizedThumbnail
                : `/storage/promotions/${normalizedThumbnail}`;

            const isHomepageTop = Number(row?.show_on_homepage_top) === 1;
            const badge = isHomepageTop
                ? `<span style="position:absolute;top:8px;left:8px;z-index:2;padding:4px 8px;border-radius:999px;background:#ff006b;color:#fff;font-size:10px;font-weight:800;box-shadow:0 8px 18px rgba(255,0,107,0.28);letter-spacing:.02em;">{{ __('cms_public.sportsbook.bet_feed.market_over') }}</span>`
                : '';

            return `<div style="position:relative;display:inline-block;line-height:0;">${badge}<img src="${src}" style="width: 75px;"></div>`
        }
        function detailImageFormatter(image){
            if (!image) {
                return `<img src="/cms-assets/blank-placeholder.svg" style="width: 75px;">`;
            }

            const normalizedImage = String(image);
            const src = normalizedImage.startsWith("http")
                ? normalizedImage
                : `/storage/promotions/${normalizedImage}`;

            return `<img src="${src}" style="width: 75px;">`
        }
        function isDefaultFormatter(status){
            if(status == 1){
                return "<span class='bg-success p-2 rounded d-block text-white text-center'>{{ __('admin.ui.yes') }}</span>";
            }else if(status == 0){
                return "<span class='bg-info p-2 rounded d-block text-black text-center'>{{ __('admin.ui.no') }}</span>";
            }
        }

        function restrictFormatter(id, row){
            console.log("Log", row);
            let text =  "<span class='d-block'>" +( row.country_restriction == 0 ? @json(__('admin.ui.no')) : @json(__('admin.copy.residual.yes_count', ['count' => '__COUNT__'])).replace('__COUNT__', row.country_restrictions.length))

            if(row.country_restriction == 0){
                text += "</span>";
            }else{
                text += "<a href='javascript:void(0);' class='btn-link btnExpand' data-id='"+row.id+"'> " + @json(__('admin.copy.inspect')) + " " +'<i class="fa-solid fa-magnifying-glass" style="margin-top: 8px"></i>'+"</a></span>";
                let countriesHolder = '<span class="d-block d-none" id="countryHolder-'+row.id+'">';
                let countries = [];
                $.each(row.country_restrictions, (i,v) => {
                    countries.push(v.name);
                    //  countriesHolder += v.name + ",";
                });

                countriesHolder += countries.join(', ') + "</span>";

                text += countriesHolder;
            }

            return text;
        }

        $(document).on("click", ".btnExpand", function (){
            $("#countryHolder-" + $(this).data("id")).toggleClass("d-none");
        })

        $(document).on("click", ".actionButton", function (e){
            e.stopPropagation();
            if(confirm(@json(__('admin.copy.confirm_action')))){
                request("POST",
                    $("#bulkActionRoute").val(),
                    {
                        ids: $("#selectedIds").val(),
                        action:  $(this).data("type")
                    },
                    (response) => {
                        toastMessage(response.status, response.message);
                        $(".bootstrap-table-1").bootstrapTable("refresh");
                    });
            }
        });
        $(document).on("click", ".deleteBtn", function (e){
            let id = $(this).data("id");
            let route = "/cms/admin/promotions/delete/"+id;
            e.stopPropagation();
            if(confirm(@json(__('admin.copy.confirm_action')))){
                request("DELETE",route,{id:id}, (response) => {
                    toastMessage(response.status, response.message);
                    $(".bootstrap-table-1").bootstrapTable("refresh");
                });
            }
        });
    </script>
@endsection
