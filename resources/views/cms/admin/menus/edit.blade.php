@extends("cms.admin.index")
@section("style")
@endsection
@section("content")
    <div class="container">
       @if($slot !== null)
       <nav class="nav nav-tabs mb-3" aria-label="Global" data-navigation-slots>
           @foreach(['sidebar', 'hero'] as $menuSlot)
               <a class="nav-link {{ $slot === $menuSlot ? 'active' : '' }}"
                  @if($slot === $menuSlot) aria-current="page" @endif
                  href="{{ route('cms.admin.menus.edit', ['slot' => $menuSlot]) }}">{{ __('navigation.'.$menuSlot) }}</a>
           @endforeach
       </nav>
       @endif
       <div class="row">
           <div class="col-sm-12">
               <div class="card">
                   <div class="card-body">
                       <form class="row" id="frmEdit">
                           <input type="hidden" name="id" class="item-menu">
                           <input type="hidden" name="variant" class="item-menu">
                           <div class="col-sm-12">
                               <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                                   @foreach($languages as $lang)
                                       <li class="nav-item" role="presentation">
                                           <button class="nav-link {{$loop->first ? "active"  : ""}}" id="pills-{{$lang["code"]}}-tab" data-bs-toggle="pill" data-bs-target="#pills-{{$lang["code"]}}" type="button" role="tab" aria-controls="pills-{{$lang["code"]}}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ __('admin_extra.2d9faadc2657', ['value1' => $lang["name"]]) }}</button>
                                       </li>
                                   @endforeach
                               </ul>
                           </div>
                           <div class="tab-content mb-2" id="pills-tabContent">
                               @foreach($languages as $lang)
                                   <div class="tab-pane fade show {{$loop->first ? "active" : ""}}" id="pills-{{$lang["code"]}}" role="tabpanel" aria-labelledby="pills-{{$lang["code"]}}-tab">
                                       <div class="row mb-2">
                                           <div class="form-group mt-2">
                                               <label class="form-label" for="text_{{$lang['code']}}">{{ __('admin.copy.text') }}</label>
                                               <div class="input-group">
                                                   <input type="text" class="form-control item-menu" name="text_{{$lang["code"]}}" id="text_{{$lang["code"]}}">
                                               </div>
                                               <input type="hidden" name="icon_{{$lang['code']}}" class="item-menu">
                                           </div>
                                           <div class="form-group mt-2">
                                               <label class="form-label" for="href_{{$lang['code']}}">{{ __('admin.ui.url') }}</label>
                                               <input type="text" class="form-control item-menu" id="href_{{$lang["code"]}}" name="href_{{$lang["code"]}}">
                                           </div>
                                           <div class="form-group mt-2">
                                               <label class="form-label" for="target_{{$lang['code']}}">{{ __('admin_extra.c36cd1a07d39') }}</label>
                                               <select name="target_{{$lang["code"]}}" id="target_{{$lang["code"]}}" class="form-control item-menu">
                                                   <option value="_self">{{ __('admin.ui.no') }}</option>
                                                   <option value="_blank">{{ __('admin.ui.yes') }}</option>
                                               </select>
                                           </div>
                                           <div class="form-group mt-2">
                                               <label class="form-label" for="title_{{$lang['code']}}">{{ __('admin.ui.description') }}</label>
                                               <input type="text" name="title_{{$lang["code"]}}" class="form-control item-menu" id="title_{{$lang["code"]}}">
                                           </div>
                                       </div>
                                   </div>

                               @endforeach
                           </div>
                           <div class="col-sm-12 mb-2">
                               <label class="form-label" for="menuVisibility">{{ __('navigation.visibility') }}</label>
                               <select class="form-select item-menu" id="menuVisibility" name="visibility">
                                   <option value="all">{{ __('navigation.all') }}</option>
                                   <option value="authenticated">{{ __('navigation.authenticated') }}</option>
                               </select>
                           </div>
                       </form>

                   </div>
                   <div class="card-footer">
                       <div class="row mb-4 justify-content-center">
                           <div class="col-sm-12">
                               <button type="button" id="btnUpdate" class="btn btn-outline-primary" disabled><i class="fas fa-sync-alt"></i> {{ __('admin_extra.2d9faadc2657', ['value1' => __('cms.profile.update')]) }}</button>
                               <button type="button" id="btnAdd" class="btn btn-outline-success"><i class="fas fa-plus"></i> {{ __('admin_extra.b96355a12ac0') }}</button>
                           </div>
                       </div>
                   </div>
               </div>
           </div>
       </div>

        <div class="row mt-2">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 col-12">
                                <input type="hidden" class="jsonMenu" value="{{ $menu->json ?? "[]" }}">
                                <ul id="menus" class="sortableLists list-group">

                                </ul>
                            </div>

                            <div class="row">
                                <div class="col-sm-12 text-center">
                                    <button type="button" id="saveMenu" class="btn btn-success mt-2">{{ __('admin.ui.save') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section("script")
    <script src="{{ asset('vendor/iconpicker/bootstrap-iconpicker.min.js') }}"></script>
    <script src="{{asset("vendor/menu-editor/jquery-menu-editor.min.js")}}"></script>
    <script>

        $(function (){
            var editor = new MenuEditor('menus');

            editor.setForm($('#frmEdit'));
            editor.setUpdateButton($('#btnUpdate'));
            var arrayjson =  JSON.parse($(".jsonMenu").val());
            editor.setData(arrayjson);
            var originalMenu = editor.getString();
            var formDirty = false;
            var saving = false;
            $('#frmEdit').on('input change', '.item-menu', function () { formDirty = true; });
            window.addEventListener('beforeunload', function (event) {
                if (!saving && (formDirty || editor.getString() !== originalMenu)) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
            $("#btnUpdate").click(function(){
                editor.update();
                formDirty = false;
            });
            $('#btnAdd').click(function(){
                $('#frmEdit [name="id"]').val('');
                editor.add();
                $('#frmEdit')[0].reset();
                editor.setUpdateButton($('#btnUpdate'));
                formDirty = false;
            });
            $('#btnLoadOrj').click(function () {
                editor.setData(arrayjson);
            });

            $(document).on("click", "#saveMenu", function (e){
                e.stopPropagation();
                if (formDirty) {
                    if (editor.getCurrentItem()) {
                        editor.update();
                    } else {
                        $('#frmEdit [name="id"]').val('');
                        editor.add();
                    }
                    formDirty = false;
                }
                let route = "{{route('cms.admin.menus.update')}}";
                var menu = editor.getString().toString();
                request("POST", route, {menu:menu, slot: @json($slot)}, (response) => {
                    toastMessage(response.status, response.message);
                    if(response.status == 200){
                        saving = true;
                        setTimeout(() => {
                            window.location.reload();
                        }, 700);
                    }
                });


                return false;
            })
        })


    </script>
@endsection
