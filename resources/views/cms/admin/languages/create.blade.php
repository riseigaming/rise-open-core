@extends("cms.admin.index")
@section("content")
    <div class="container">
        <form class="row" id="createForm" action="{{route("cms.admin.languages.store")}}" method="POST">
            @csrf
            @method("POST")
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                       <div class="row">
                           <div class="col-sm-3">
                               <label class="form-label">{{ __('admin.copy.language_name') }}</label>
                               <input type="text" name="name" class="form-control" placeholder="{{ __('admin.copy.language_name_example') }}" required>
                           </div>
                           <div class="col-sm-3">
                               <label class="form-label">{{ __('admin_extra.52adf5ffecfa') }}</label>
                               <input type="text" name="code" class="form-control" placeholder="{{ __('admin.copy.language_code_example') }}" maxlength="5" required>
                           </div>
                           <div class="col-sm-3">
                               <label class="form-label">{{ __('admin.ui.default_language') }}</label>
                               <select class="form-control form-select" name="is_default" required>
                                   <option value="0">{{ __('admin.ui.no') }}</option>
                                   <option value="1">{{ __('admin.ui.yes') }}</option>
                               </select>
                           </div>

                           <div class="col-sm-3">
                               <label class="form-label">{{ __('admin.ui.status') }}</label>
                               <select class="form-control form-select" name="status" required>
                                   <option value="1">{{ __('admin.ui.active') }}</option>
                                   <option value="0">{{ __('admin.ui.inactive') }}</option>
                               </select>
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
        $("#createForm").on("submit", function (e){
            e.stopPropagation();
            let route = $(this).attr("action");
            let formData = $(this).serializeArray();

            request("POST", route, formData, (response) => {
                toastMessage(response.status, response.message);

                if(response.status == 200){
                    setTimeout(() => {
                        window.location.href="{{route("cms.admin.languages.index")}}";
                    }, 700);
                }
            });

            return false;
        })
    </script>
@endsection
