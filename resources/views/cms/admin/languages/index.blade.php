@extends("cms.admin.index")
@section("content")
    <div class="container">
        <input type="hidden" id="selectedIds" value="">
        <input type="hidden" id="bulkActionRoute" value="{{route("cms.admin.languages.bulkAction")}}">
        <div class="row mb-3">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1">{{ __('admin.languages_page.country_defaults') }}</h5>
                                <p class="text-muted mb-0">{{ __('admin.languages_page.country_defaults_help') }}</p>
                            </div>
                            <span class="badge bg-info text-dark">{{ __('admin.languages_page.mapping_count', ['count' => count($countryDefaults['entries'])]) }}</span>
                        </div>

                        @if(!$countryDefaults['storage_available'])
                            <div class="alert alert-warning mb-0">{{ __('admin.languages_page.storage_unavailable') }}</div>
                        @elseif(empty($countryDefaults['countries']) || empty($countryDefaults['languages']))
                            <div class="alert alert-warning mb-0">{{ __('admin.languages_page.requirements') }}</div>
                        @else
                            <form id="countryDefaultLanguageForm" action="{{ route('cms.admin.languages.countryDefaults.set') }}" method="POST" class="row align-items-end mb-3">
                                @csrf
                                <div class="col-md-5 mb-2">
                                    <label class="form-label">{{ __('admin.ui.country') }}</label>
                                    <select name="country_id" class="form-control form-select" required>
                                        <option value="">{{ __('admin_extra.2d9faadc2657', ['value1' => __('cms_public.auth.country_select')]) }}</option>
                                        @foreach($countryDefaults['countries'] as $country)
                                            <option value="{{ $country['id'] }}">{{ __('admin_extra.f52d942c22d3', ['value1' => $country['label'], 'value2' => $country['code'] ? ' · '.$country['code'] : '']) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5 mb-2">
                                    <label class="form-label">{{ __('admin_extra.d8ad60fe3cea') }}</label>
                                    <select name="language_code" class="form-control form-select" required>
                                        <option value="">{{ __('admin.languages_page.select_language') }}</option>
                                        @foreach($countryDefaults['languages'] as $language)
                                            <option value="{{ $language['code'] }}">{{ __('admin_extra.b854423b6eea', ['value1' => $language['name'], 'value2' => strtoupper($language['code'])]) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <button type="submit" class="btn btn-primary w-100">{{ __('admin.ui.save') }}</button>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                    <tr>
                                        <th>{{ __('admin.ui.country') }}</th>
                                        <th>{{ __('admin.ui.default_language') }}</th>
                                        <th>{{ __('admin.ui.status') }}</th>
                                        <th style="width: 110px;">{{ __('admin.dashboard.dynamic.transaction') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($countryDefaults['entries'] as $entry)
                                        <tr>
                                            <td>{{ __('admin_extra.f52d942c22d3', ['value1' => $entry['country_label'], 'value2' => $entry['country_code'] ? ' · '.$entry['country_code'] : '']) }}</td>
                                            <td>{{ __('admin_extra.b854423b6eea', ['value1' => $entry['language_name'], 'value2' => strtoupper($entry['language_code'])]) }}</td>
                                            <td>
                                                @if($entry['active'])
                                                    <span class="badge bg-success">{{ __('admin.ui.active') }}</span>
                                                @else
                                                    <span class="badge bg-warning text-dark">{{ __('admin_extra.c7cf1d9739d4') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-danger removeCountryDefaultLanguage"
                                                        data-route="{{ route('cms.admin.languages.countryDefaults.remove', ['countryId' => $entry['country_id']]) }}">
                                                    {{ __('admin_extra.2d9faadc2657', ['value1' => __('cms_public.games.roulette.remove')]) }}
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">{{ __('admin.languages_page.empty') }}</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <div class="toolbar">
                            <a href="{{ route("cms.admin.languages.create") }}" class="btn btn-outline-primary pull-left" style="margin-right: 5px;"> <i class="fa fa-plus"></i>  {{ __('admin.ui.new_record') }}</a>
                            
                            <button type="button" class="actionButton btn btn-primary" data-type="active" id="do_active" disabled>{{ __('admin.ui.activate') }}</button>
                            <button type="button" class="actionButton btn btn-warning" data-type="passive" id="do_passive" disabled>{{ __('admin.ui.deactivate') }}</button>
                            <button type="button" class="actionButton btn btn-danger" data-type="delete" id="do_deleted" disabled>{{ __('admin.ui.delete') }}</button>
                        </div>
                        <table id="table1" data-locale="{{ app()->getLocale() === 'tr' ? 'tr-TR' : 'en-US' }}" data-toolbar=".toolbar" data-search="true"
                               class="bootstrap-table bootstrap-table-1"
                               data-show-refresh="true" data-show-toggle="true" data-pagination="true" data-resizable="true"
                               data-sortable="true" data-show-search-clear-button="true" data-side-pagination="server" data-click-to-select="true"
                               data-page-list="[10, 25, 50, 100, 200, 250]"
                               data-url="{{ route("cms.admin.languages.getAjaxList") }}">
                            <thead>
                            <tr>
                                <th data-formatter="checkboxFormatter" data-field="id">#</th>
                                <th data-sortable="true" data-field="name">{{ __('admin.ui.title') }}</th>
                                <th data-sortable="true" data-field="code">{{ __('admin.languages_page.short_code') }}</th>
                                <th data-sortable="true" data-field="is_default" data-formatter="isDefaultFormatter">{{ __('admin.languages_page.default') }}</th>
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

        $(document).on("submit", "#countryDefaultLanguageForm", function (e){
            e.preventDefault();
            let form = $(this);

            request("POST", form.attr("action"), form.serializeArray(), (response) => {
                toastMessage(response.status, response.message);
                if(response.status == 200){
                    window.location.reload();
                }
            });
        });

        $(document).on("click", ".removeCountryDefaultLanguage", function (e){
            e.preventDefault();
            let route = $(this).data("route");

            if(confirm(@json(__('admin.languages_page.remove_confirm')))){
                request("DELETE", route, {}, (response) => {
                    toastMessage(response.status, response.message);
                    if(response.status == 200){
                        window.location.reload();
                    }
                });
            }
        });

        function edit(id){
            let currentBtn = $("#editBtn-"+id);
            let route = currentBtn.data("route");
            window.location.href=route;
        }
        function buttonFormatter(id){
            let editBtn =  '<button type="button" data-route="/cms/admin/languages/edit/'+id+'" id="editBtn-'+id+'" onclick="edit('+id+')" class="btn btn-outline-success editBtn" data-id="'+id+'"><i class="fa fa-edit"></i>  {{ __('admin.ui.edit') }}</button>';
            let deleteBtn = ' <button type="button" id="deleteBtn-'+id+'" class="btn btn-outline-danger deleteBtn" data-id="'+id+'"><i class="fa fa-trash-alt"></i>  {{ __('admin.ui.delete') }}</button>';

            return editBtn + deleteBtn;
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
            if(confirm(@json(__('admin.languages_page.action_confirm')))){
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
            let route = "/cms/admin/languages/delete/"+id;
            e.stopPropagation();
            if(confirm(@json(__('admin.languages_page.action_confirm')))){
                request("DELETE",route,{id:id}, (response) => {
                    toastMessage(response.status, response.message);
                    $(".bootstrap-table-1").bootstrapTable("refresh");
                });
            }
        });
    </script>
@endsection
