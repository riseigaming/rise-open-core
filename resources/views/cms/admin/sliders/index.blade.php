@extends("cms.admin.index")
@section("content")
    <div class="container">
        <input type="hidden" id="selectedIds" value="">
        <input type="hidden" id="bulkActionRoute" value="{{route("cms.admin.sliders.bulkAction")}}">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <div class="toolbar">
                            <a href="{{ route("cms.admin.sliders.create") }}" class="btn btn-outline-primary pull-left" style="margin-right: 5px;"> <i class="fa fa-plus"></i>  {{ __('admin.ui.new_record') }}</a>
                            <button type="button" class="actionButton btn btn-primary" data-type="active" id="do_active" disabled>{{ __('admin.ui.activate') }}</button>
                            <button type="button" class="actionButton btn btn-warning" data-type="passive" id="do_passive" disabled>{{ __('admin.ui.deactivate') }}</button>
                            <button type="button" class="actionButton btn btn-danger" data-type="delete" id="do_deleted" disabled>{{ __('admin.ui.delete') }}</button>
                        </div>
                        <table id="table1" data-locale="{{ app()->getLocale() === 'tr' ? 'tr-TR' : 'en-US' }}" data-toolbar=".toolbar" data-search="true"
                               class="bootstrap-table bootstrap-table-1"
                               data-show-refresh="true" data-show-toggle="true" data-pagination="true" data-resizable="true"
                               data-sortable="true" data-show-search-clear-button="true" data-side-pagination="server" data-click-to-select="true"
                               data-page-list="[10, 25, 50, 100, 200, 250]"
                               data-sort-name="rank"
                               data-sort-order="asc"
                               data-url="{{ route("cms.admin.sliders.getAjaxList") }}">
                            <thead>
                            <tr>
                                <th data-formatter="checkboxFormatter" data-field="id">#</th>
                                <th data-field="image_url" data-formatter="imageFormatter">{{ __('admin.copy.image') }}</th>
                                <th data-sortable="true" data-field="rank" data-formatter="rankFormatter">{{ __('admin.copy.display_priority') }}</th>
                                <th data-sortable="true" data-field="title">{{ __('admin.ui.title') }}</th>
                                <th data-sortable="true" data-field="description">{{ __('admin.ui.description') }}</th>
                                <th data-sortable="true" data-field="url">{{ __('admin.ui.url') }}</th>
                                <th data-sortable="true" data-field="status" data-formatter="integerStatusFormatter">{{ __('admin.ui.status') }}</th>
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
            let editBtn =  '<button type="button" data-route="/cms/admin/sliders/edit/'+id+'" id="editBtn-'+id+'" onclick="edit('+id+')" class="btn btn-outline-success editBtn" data-id="'+id+'"><i class="fa fa-edit"></i>  {{ __('admin.ui.edit') }}</button>';
            let deleteBtn = ' <button type="button" id="deleteBtn-'+id+'" class="btn btn-outline-danger deleteBtn" data-id="'+id+'"><i class="fa fa-trash-alt"></i>  {{ __('admin.ui.delete') }}</button>';

            return editBtn + deleteBtn;
        }

        function imageFormatter(image_url){
            return `<img src="${image_url}" class="img-fluid" style="max-width: 100px">`;
        }

        function rankFormatter(rank){
            const safeRank = Number(rank || 0);
            return `<span class="badge bg-primary" style="font-size:12px; padding:8px 10px;">#${safeRank}</span>`;
        }

        function isDefaultFormatter(status){
            if(status == 1){
                return "<span class='bg-success p-2 rounded d-block text-white text-center'>{{ __('admin.ui.yes') }}</span>";
            }else if(status == 0){
                return "<span class='bg-info p-2 rounded d-block text-black text-center'>{{ __('admin.ui.no') }}</span>";
            }
        }


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
            let route = "/cms/admin/sliders/delete/"+id;
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
