@extends("cms.admin.index")
@section("style")
    <style>

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

        .themeName {
            position: absolute;
            left: 15px;
            bottom: 15px;
        }

        .settingsSectionIntro {
            margin-bottom: 0;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.45;
        }

        .settingsMediaGrid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }

        .settingsMediaCard {
            height: 100%;
            padding: 14px;
            border: 1px solid #e6ebf2;
            border-radius: 14px;
            background: #fbfcfe;
        }

        .settingsMediaLabel {
            margin: 0 0 10px;
            color: #111827;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
        }

        .settingsMediaPreview {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 132px;
            padding: 10px;
            border-radius: 12px;
            background: #ffffff;
            border: 1px solid #eef2f7;
            overflow: hidden;
        }

        .settingsMediaPreview img {
            display: block;
            max-width: 100%;
            max-height: 118px;
            width: auto !important;
            height: auto;
            object-fit: contain;
        }

        .settingsMediaPlaceholder,
        .settingsThemePlaceholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            min-height: 56px;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }

        .settingsMediaAction {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 38px;
            margin-top: 10px;
            padding: 0 14px;
            border: 1px solid #d6deea;
            border-radius: 10px;
            background: #ffffff;
            color: #1f3f8f;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: border-color 0.18s ease, background-color 0.18s ease, color 0.18s ease;
        }

        .settingsMediaAction:hover {
            border-color: #b5c5e4;
            background: #f4f7fd;
            color: #173171;
            text-decoration: none;
        }

        .settingsMediaAction i {
            font-size: 13px;
        }

        .settingsThemeRow {
            align-items: stretch;
        }

        .settingsThemeSelection {
            padding: 16px;
            border: 1px solid #e6ebf2;
            border-radius: 16px;
            background: #fbfcfe;
        }

        .settingsThemeGrid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .settingsThemeCard {
            position: relative;
        }

        .settingsThemeOption {
            display: flex;
            flex-direction: column;
            gap: 10px;
            height: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #ffffff;
            cursor: pointer;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }

        .settingsThemeOption:hover {
            border-color: #c7d4ea;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
            transform: translateY(-1px);
        }

        .settingsThemeOption input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .settingsThemePreview {
            display: flex;
            align-items: center;
            justify-content: center;
            aspect-ratio: 16 / 9;
            min-height: 82px;
            padding: 8px;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
            overflow: hidden;
        }

        .settingsThemePreview img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center;
            border-radius: 8px;
        }

        .settingsThemeMeta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .settingsThemeTitle {
            display: block;
            color: #0f172a;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
        }

        .settingsThemeSlug {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 0 8px;
            border-radius: 999px;
            background: #e7eefb;
            color: #3357a7;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
        }

        .settingsThemeOption input:checked + .settingsThemeSurface {
            border-color: #9db7ef;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        .settingsThemeActiveBadge {
            position: absolute;
            top: 12px;
            right: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 8px;
            border-radius: 999px;
            background: #2563eb;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.24);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            line-height: 1;
            opacity: 0;
            pointer-events: none;
            transform: translateY(-3px);
            visibility: hidden;
            transition: opacity 0.16s ease, transform 0.16s ease, visibility 0.16s ease;
        }

        .settingsThemeOption input:checked ~ .settingsThemeActiveBadge {
            opacity: 1;
            transform: translateY(0);
            visibility: visible;
        }

        .settingsThemeSurface {
            display: flex;
            flex-direction: column;
            gap: 10px;
            height: 100%;
        }

        .settingsLogoCard {
            height: 100%;
        }

        .settingsLanguageTabs {
            margin-top: 14px;
        }

        .settingsLanguageTabs .nav-link {
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
        }

        .settingsLanguageTabPane {
            margin-top: 12px;
            padding: 14px;
            border: 1px solid #e6ebf2;
            border-radius: 14px;
            background: #fbfcfe;
        }

        .settingsCompactPanel {
            border: 1px solid #e6ebf2;
            border-radius: 14px;
            background: #fbfcfe;
        }

        .settingsCompactPanelSummary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            min-height: 54px;
            padding: 12px 16px;
            cursor: pointer;
            list-style: none;
        }

        .settingsCompactPanelSummary::-webkit-details-marker {
            display: none;
        }

        .settingsCompactPanelSummary strong,
        .settingsCompactPanelSummary small {
            display: block;
        }

        .settingsCompactPanelSummary strong {
            color: #111827;
            font-size: 14px;
        }

        .settingsCompactPanelSummary small {
            margin-top: 2px;
            color: #7a8798;
            font-size: 12px;
        }

        .settingsCompactPanel[open] .settingsCompactPanelSummary i {
            transform: rotate(180deg);
        }

        .settingsCompactPanelBody {
            padding: 0 16px 16px;
        }

        .settingsSortableRail {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .settingsSortableItem {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 56px;
            padding: 10px;
            border: 1px solid #e6ebf2;
            border-radius: 10px;
            background: #fff;
            cursor: grab;
            user-select: none;
            transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        .settingsSortableItem.is-disabled {
            opacity: .58;
        }

        .settingsSortableItem.is-dragging {
            opacity: .72;
            transform: scale(.99);
        }

        .settingsSortableItem:hover,
        .settingsSortableItem:focus {
            border-color: #b8c7dc;
            box-shadow: 0 8px 18px rgba(31, 41, 55, .08);
            outline: none;
        }

        .settingsSortableHandle {
            color: #7a8798;
        }

        .settingsSortableIcon {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: #f3f6fa;
            color: #1f2937;
        }

        .settingsSortableToggle input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .settingsSortableToggle span {
            display: block;
            width: 34px;
            height: 18px;
            border-radius: 999px;
            background: #cbd5e1;
            position: relative;
        }

        .settingsSortableToggle span:before {
            content: "";
            position: absolute;
            top: 3px;
            left: 3px;
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: #fff;
            transition: transform .15s ease;
        }

        .settingsSortableToggle input:checked + span {
            background: #22c55e;
        }

        .settingsSortableToggle input:checked + span:before {
            transform: translateX(16px);
        }

        .settingsSortableFields {
            display: grid;
            grid-template-columns: minmax(120px, .8fr) minmax(180px, 1.4fr) minmax(120px, .8fr);
            gap: 8px;
            flex: 1 1 auto;
            min-width: 0;
        }

        .settingsSortableRemove {
            flex: 0 0 auto;
            width: 32px;
            height: 32px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            color: #9ca3af;
        }

        .settingsSortableItem[data-custom="0"] .settingsSortableRemove {
            visibility: hidden;
        }

        @media (max-width: 767px) {
            .settingsSortableItem {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .settingsSortableFields {
                grid-template-columns: 1fr;
                flex-basis: 100%;
            }
        }
    </style>
@endsection
@section('content')
<div class="container-xl"><div class="card"><div class="card-body">
    <form class="row" id="createForm" action="{{ route('cms.admin.settings.updateSiteSettings') }}" method="post" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="active_theme" value="global"><input type="hidden" name="content_theme" value="global">
        <div class="col-sm-12"><h2>{{ __('admin.b1.settings') }}</h2></div>
        <div class="col-sm-6 mb-3">
            <label class="form-label" for="global-accent">{{ __('content_pages.accent') }}</label>
            <select class="form-select" id="global-accent" name="accent_preset">
                @foreach (\App\Models\CmsThemeSetting::ACCENT_PRESETS as $preset)
                    <option value="{{ $preset }}" @selected($preset === ($setting->accent_preset ?? 'default'))>{{ __('content_pages.accents.'.$preset) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-12 mb-3"><label class="form-label" for="logoInput">{{ __('admin.b1.logo') }}</label>
            @if($setting->getPublicLogo())<img src="{{ $setting->getPublicLogo() }}" alt="" class="settingsLogoPreview" style="max-width:180px;max-height:90px">@endif
            <input type="file" id="logoInput" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg" class="form-control">
        </div>
        @foreach(['register_status' => ['0','1'], 'account_mail_status' => ['passive','active'], 'homepage_hero_auth_mode' => ['login','register'], 'mobile_nav_type' => ['type_1','type_2'], 'topbar_fullscreen_status' => ['active','passive'], 'topbar_logout_status' => ['active','passive']] as $field => $choices)
            <div class="col-sm-6 mb-3"><label class="form-label" for="setting-{{ $field }}">{{ __('admin.b1.'.$field) }}</label><select class="form-control form-select" id="setting-{{ $field }}" name="{{ $field }}">@foreach($choices as $choice)<option value="{{ $choice }}" @selected((string) $setting->getAttribute($field) === (string) $choice)>{{ __('admin.b1.options.'.$choice) }}</option>@endforeach</select></div>
        @endforeach
        <div class="col-sm-12">
            <ul class="nav nav-pills mb-3" role="tablist">@foreach($languages as $language)<li class="nav-item"><a class="nav-link {{ $loop->first ? 'active' : '' }}" id="settings-tab-{{ $language['code'] }}" data-bs-toggle="pill" href="#settings-content-{{ $language['code'] }}" role="tab" aria-controls="settings-content-{{ $language['code'] }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $language['name'] }}</a></li>@endforeach</ul>
            <div class="tab-content">@foreach($languages as $language)<div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="settings-content-{{ $language['code'] }}" role="tabpanel" aria-labelledby="settings-tab-{{ $language['code'] }}">
                <input type="hidden" name="langContent[{{ $loop->index }}][cms_language_id]" value="{{ $language['id'] }}">
                <label class="form-label" for="title-{{ $language['code'] }}">{{ __('admin.ui.title') }}</label><input id="title-{{ $language['code'] }}" class="form-control mb-3" name="langContent[{{ $loop->index }}][site_title]" value="{{ data_get($transData, $language['id'].'.site_title', '') }}">
                <label class="form-label" for="description-{{ $language['code'] }}">{{ __('admin.ui.description') }}</label><textarea id="description-{{ $language['code'] }}" class="form-control mb-3" rows="5" name="langContent[{{ $loop->index }}][site_description]">{{ data_get($transData, $language['id'].'.site_description', '') }}</textarea>
            </div>@endforeach</div>
        </div>
        <div class="col-sm-12 mt-3"><p data-settings-feedback role="status"></p><button class="btn btn-primary" type="submit">{{ __('admin.ui.save') }}</button></div>
    </form>
</div></div></div>
@endsection
@section('script')
<script>
 document.getElementById('createForm').addEventListener('submit', async function(event) {
    event.preventDefault();
    const button = this.querySelector('[type="submit"]'), feedback = this.querySelector('[data-settings-feedback]');
    button.disabled = true;
    try {
        const response = await fetch(this.action, {method:'POST', body:new FormData(this), credentials:'same-origin', headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
        const result = await response.json();
        feedback.textContent = result.message || @json(__('admin.copy.unexpected_error'));
        if (response.ok && Number(result.status) === 200) window.location.reload();
    } catch (error) { feedback.textContent = @json(__('admin.copy.unexpected_error')); }
    finally {button.disabled = false;}
 });
</script>
@endsection
