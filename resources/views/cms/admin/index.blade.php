<!doctype html>
<html lang="{{ app()->getLocale() === 'tr' ? 'tr' : 'en' }}">

<head>
    @php
        $adminLocale = app()->getLocale() === 'tr' ? 'tr' : 'en';
        $adminLocaleUrl = static function (string $locale): string {
            return request()->fullUrlWithQuery(['admin_locale' => $locale]);
        };
        $adminAlternateLocale = $adminLocale === 'en' ? 'tr' : 'en';
        $adminLocaleShortLabels = [
            'en' => __('admin.language.short_english'),
            'tr' => __('admin.language.short_turkish'),
        ];
        $adminLocaleLongLabels = [
            'en' => __('admin.language.english'),
            'tr' => __('admin.language.turkish'),
        ];
    @endphp
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('title')</title>
    <!-- CSS files -->

    
    
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link href="{{ asset('vendor/tabler/tabler.min.css') }}" rel="stylesheet" />

    <link rel="stylesheet" href="{{asset("vendor/toastr/toastr.min.css")}}">
    <link rel="stylesheet" href="{{ asset('cms-assets/css/admin-notice.css') }}?v={{ filemtime(public_path('cms-assets/css/admin-notice.css')) }}">
    <link rel="stylesheet" href="{{asset("vendor/bootstrap-table/bootstrap-table.min.css")}}">
    <style type="text/css">
        :root {
            --font-family: "Open Sans", "Arial", "Helvetica Neue", sans-serif;
            --hero: #1473e6;
            --confirm: #2bc784;
            --warning: #e6af57;
            --danger: #e64e48;
            --note: #fdc625;
            --message: #8bd779;
            --background: #fff;
            --foreground: #3d3d5f;
            --hero-hover: #1267cf;
            --hero-sc: #fff;
            --hero-rgb: 20, 115, 230;
            --hero-sc-rgb: 255, 255, 255;
            --confirm-hover: #26b276;
            --confirm-sc: #fff;
            --confirm-rgb: 43, 199, 132;
            --confirm-sc-rgb: 255, 255, 255;
            --warning-hover: #e3a441;
            --warning-sc: #fff;
            --warning-rgb: 230, 175, 87;
            --warning-sc-rgb: 255, 255, 255;
            --danger-hover: #e33832;
            --danger-sc: #fff;
            --danger-rgb: 230, 78, 72;
            --danger-sc-rgb: 255, 255, 255;
            --note-hover: #fdbf0c;
            --note-sc: #000;
            --note-rgb: 253, 198, 37;
            --note-sc-rgb: 0, 0, 0;
            --message-hover: #7ad165;
            --message-sc: #000;
            --message-rgb: 139, 215, 121;
            --message-sc-rgb: 0, 0, 0;
            --background-hover: #f2f2f2;
            --background-sc: #000;
            --background-rgb: 255, 255, 255;
            --background-sc-rgb: 0, 0, 0;
            --foreground-hover: #33334f;
            --foreground-sc: #fff;
            --foreground-rgb: 61, 61, 95;
            --foreground-sc-rgb: 255, 255, 255;
        }
        .invert-colors {
            --background: var(--foreground);
            --background-hover: var(--foreground-hover);
            --background-sc: var(--foreground-sc);
            --background-rgb: var(--foreground-rgb);
            --background-sc-rgb: var(--foreground-sc-rgb);
        }
        * {
            -webkit-font-smoothing: subpixel-antialiased;
            -webkit-tap-highlight-color: transparent;
            background: none;
            border: 0;
            border-radius: 0;
            margin: 0;
            outline: 0;
            padding: 0;
            vertical-align: top;
        }

        a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s, background 0.3s;
        }
        .notifycustom { color: #31708f !important;  background-color: #2bc784 !important;  border-color: #e9f9f2 !important}

        #exportReportBtn,
        .fixed-table-toolbar button[name="export"],
        .fixed-table-toolbar button[name="refresh"],
        .fixed-table-toolbar button[name="toggle"],
        .fixed-table-toolbar button[name="columns"],
        .fixed-table-toolbar button[name="clearSearch"],
        .fixed-table-toolbar .search-clear,
        .fixed-table-toolbar .search .search-clear {
            display: none !important;
        }

        .uploadWrapper {
            position: relative;
        }
        .uploadWrapper .fileSelector{
            position: absolute;
            top: 10px;
            left: 10px;
        }

        .quick-filter-dropdown:hover > .dropdown-menu,
        .quick-filter-dropdown:focus-within > .dropdown-menu {
            display: block;
        }

        .quick-filter-dropdown > .dropdown-toggle:hover,
        .quick-filter-dropdown > .dropdown-toggle:focus {
            text-decoration: none;
        }

        .filterOpener:hover,
        .filterOpener:focus,
        .btn.btn-link[data-bs-toggle="collapse"]:hover,
        .btn.btn-link[data-bs-toggle="collapse"]:focus {
            text-decoration: none !important;
            box-shadow: none;
        }

        .modal.fade .modal-dialog {
            transform: translate3d(0, 0, 0) scale(0.982);
            opacity: 0;
            filter: blur(1px);
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.16s ease, filter 0.16s ease;
            will-change: transform, opacity;
            backface-visibility: hidden;
        }

        .modal.show .modal-dialog {
            transform: translate3d(0, 0, 0) scale(1);
            opacity: 1;
            filter: blur(0);
        }

        .modal-content {
            contain: layout paint;
            backface-visibility: hidden;
        }

        .modal-body {
            overscroll-behavior: contain;
        }

        .modal .modal-body,
        .modal .modal-footer {
            transition: opacity 0.16s ease, transform 0.16s ease;
        }

        .modal.is-hydrating .modal-body,
        .modal.is-hydrating .modal-footer {
            opacity: 1;
            transform: translate3d(0, 0, 0);
        }

        .modal.is-ready .modal-body,
        .modal.is-ready .modal-footer {
            opacity: 1;
            transform: translate3d(0, 0, 0);
        }

        @media (prefers-reduced-motion: reduce) {
            .modal.fade .modal-dialog,
            .modal .modal-body,
            .modal .modal-footer {
                transition: none !important;
            }
        }
    </style>


    <style>
        #dtBasicExample_wrapper {
            width: 100%
        }
        table tr:hover {
            background-color: #edf4fd !important;
            cursor: default
        }table tr{background:##fafafa;font-size:14px;font-weight:500;line-height:30px}
        table tr:nth-child(odd){
            background-color: #fafafa;
        }
        table tr:nth-child(even){
            background-color: white;
        }

        
.wrappers{
  position: relative;
  transform: translate(-50%,-50%);
  overflow: visible;
  z-index: 20;
}

.wrappers .search_box{
    position: absolute;
  background: #f4f6fa;
  border-radius: 12px;
  height: 65px;
  display: flex;
  padding: 10px;
  overflow: visible;
  z-index: 20;
}

.wrappers .search_box .magic-search-mode{
  width: 194px;
  border-right: 1px solid rgba(148, 163, 184, 0.22);
  color: #334155;
  position: relative;
  cursor: pointer;
  padding-right: 10px;
  overflow: visible;
  z-index: 30;
}

.wrappers .search_box .magic-search-mode-trigger{
  width: 100%;
  height: 100%;
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 0 14px 0 15px;
  border-radius: 16px;
  background: linear-gradient(135deg, rgba(37, 99, 235, 0.12) 0%, rgba(80, 103, 168, 0.08) 100%);
  color: #1e293b;
  text-align: left;
  cursor: pointer;
  transition: transform 0.2s ease, background 0.2s ease, color 0.2s ease;
}

.wrappers .search_box .magic-search-mode-trigger:hover,
.wrappers .search_box .magic-search-mode-trigger:focus {
  transform: none;
}

.wrappers .search_box .magic-search-mode.is-open .magic-search-mode-trigger{
  background: linear-gradient(135deg, rgba(37, 99, 235, 0.12) 0%, rgba(80, 103, 168, 0.08) 100%);
}

.wrappers .search_box .magic-search-mode-copy{
  display: grid;
  gap: 2px;
  min-width: 0;
}

.wrappers .search_box .magic-search-mode-copy small{
  color: #6b7a91;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.wrappers .search_box .magic-search-mode-copy strong{
  color: #0f172a;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.wrappers .search_box .magic-search-mode-current-icon{
  color: #3559b7;
  font-size: 13px;
  flex: 0 0 auto;
}

.wrappers .search_box .magic-search-mode-chevron{
  color: #5067a8;
  font-size: 12px;
  transition: transform 0.2s ease;
}

.wrappers .search_box .magic-search-mode.is-open .magic-search-mode-chevron{
  transform: rotate(180deg);
}

.wrappers .search_box .magic-search-mode-menu{
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  width: 236px;
  min-width: 100%;
  padding: 8px;
  border-radius: 18px;
  border: 1px solid rgba(148, 163, 184, 0.18);
  background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
  box-shadow: 0 24px 44px rgba(15, 23, 42, 0.14);
  display: none;
  max-height: 360px;
  overflow-y: auto;
  z-index: 1055;
}

.wrappers .search_box .magic-search-mode.is-open .magic-search-mode-menu{
  display: grid;
}

.wrappers .search_box .magic-search-mode-item{
  width: 100%;
  border: 0;
  background: transparent;
  border-radius: 14px;
  color: #334155;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 11px 12px;
  text-align: left;
  transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
}

.wrappers .search_box .magic-search-mode-item:hover{
  background: rgba(80, 103, 168, 0.08);
  color: #102453;
  transform: translateX(1px);
}

.wrappers .search_box .magic-search-mode-item.is-active{
  background: linear-gradient(135deg, rgba(37, 99, 235, 0.12) 0%, rgba(80, 103, 168, 0.08) 100%);
  color: #1d4ed8;
}

.wrappers .search_box .magic-search-mode-item i{
  color: rgba(37, 99, 235, 0.55);
  font-size: 11px;
}

.wrappers .search_box .search_field{
  width: 350px;
  height: 100%;
  position: relative;
  display: flex;
  align-items: center;
}

.wrappers .search_box .search_field .input{
  font-family: var(--font-family);
  font-size: 15px;
  font-weight: 500;
  width: 100%;
  height: 100%;
  border: 0px;
  padding-left: 20px;
  padding-right: 18px;
  color: #0f172a;
  background: transparent;
  letter-spacing: 0;
}

#search .spinner {
  -webkit-animation: spinner 1s infinite linear;
          animation: spinner 1s infinite linear;
  display: none;
  padding: 0 0.25em;
  color:#5078ef;
  width:18px;
  height: 18px;
  position:absolute;
  right:20px;
  top:19px
}

#search.loading button {
  display: none;
}

#search.loading .spinner {
  display: block;
}
@-webkit-keyframes spinner {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
@keyframes spinner {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
.hovering:hover{
    cursor:pointer;
    background: #dbf2f2f0;
    border-left: 4px solid #758c8c;
}
.rmmargin{margin-left:0!important}

.form-control {
    height:36px
}



        div.header-search-results{
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 12px);
            min-height: 320px;
            max-height: 72vh;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            margin-top: 0;
            border-radius: 20px;
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.1);
            overflow-y: auto;
            overflow-x: hidden;
            padding-bottom: 10px;
            backdrop-filter: blur(12px);
            z-index: 40;
        }

        div.header-search-results.is-ai-preview{
            min-height: 300px;
            max-height: 68vh;
        }

        div.header-search-results.is-autocomplete{
            min-height: auto;
            max-height: 420px;
        }

        #headerSearchResultItems{
            display:block;
            margin:0;
            padding:0;
            list-style:none;
            min-width:0;
        }

        div.header-search-results a{
            color:#206bc4!important;
        }

        div.header-search-results li{
            line-height: 1.4;
        }
        div.header-search-results li:not(:last-child){
            border-bottom: 1px solid rgba(226, 233, 245, 0.9);
        }

        div.header-search-results .closeHeaderSearch{
            position: absolute;
            right: 14px;
            color: #8a97ac !important;
            border-radius: 10px;
            top: 10px;
            text-align: center;
            font-size: 18px;
        }

        div.header-search-results .closeSearchInput{
            min-height: 48px;
            border-bottom: 1px solid rgba(226, 233, 245, 0.9);
            padding: 12px 14px;
            display: flex;
            align-items: center;
        }
        .header-search-note{
            color:#475569;
            font-size:12px;
            line-height:1.5;
            margin:10px 12px 0;
            padding:10px 12px;
            border:1px solid rgba(148, 163, 184, 0.16);
            border-radius:14px;
            background: rgba(248, 250, 252, 0.92);
        }
        .header-search-surface-head{
            align-items:flex-start;
            border-bottom:1px solid rgba(226, 233, 245, 0.88);
            display:flex;
            justify-content:space-between;
            gap:12px;
            padding:14px 14px 12px;
        }
        .header-search-surface-copy{
            display:grid;
            gap:4px;
            min-width:0;
            overflow-wrap:anywhere;
        }
        .header-search-surface-kicker{
            color:#64748b;
            font-size:11px;
            font-weight:800;
            letter-spacing:.08em;
            text-transform:uppercase;
        }
        .header-search-surface-title{
            color:#0f172a;
            font-size:13px;
            font-weight:800;
        }
        .header-search-surface-subtitle{
            color:#64748b;
            font-size:11px;
            line-height:1.45;
        }
        .header-search-autocomplete-badge{
            align-items:center;
            background:rgba(37, 99, 235, 0.06);
            border:1px solid rgba(37, 99, 235, 0.12);
            border-radius:999px;
            color:#1d4ed8;
            display:inline-flex;
            flex:0 0 auto;
            font-size:11px;
            font-weight:700;
            padding:6px 9px;
            white-space:nowrap;
        }
        .header-search-autocomplete-meta{
            color:#708195;
            font-size:12px;
            line-height:1.45;
            margin-top:4px;
            overflow-wrap:anywhere;
        }
        .header-search-selected-state{
            display:grid;
            gap:8px;
            padding:10px 12px 0;
        }
        .header-search-selected-state strong{
            color:#64748b;
            font-size:11px;
            font-weight:800;
            letter-spacing:.04em;
            text-transform:uppercase;
        }
        .header-search-selected-state-list{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
        }
        .header-search-state-pill,
        .header-search-state-token{
            align-items:center;
            background:#f8fafc;
            border:1px solid #e6ecf5;
            border-radius:999px;
            color:#334155;
            display:inline-flex;
            font-size:12px;
            font-weight:700;
            gap:8px;
            padding:7px 11px;
        }
        .header-search-state-token i{
            color:#64748b;
            font-size:11px;
        }
        .header-search-result-link,
        .header-search-result-button{
            box-sizing:border-box;
            width:calc(100% - 24px);
            display:block;
            text-align:left;
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:12px;
            box-shadow:none;
            padding:12px 14px;
            color:#243245!important;
            margin:10px 12px 0;
            white-space:normal;
            overflow-wrap:anywhere;
        }
        .header-search-result-button{
            cursor:pointer;
        }
        .header-search-result-link small,
        .header-search-result-button small{
            display:block;
            color:#708195;
            margin-top:4px;
            line-height:1.45;
        }
        .header-search-result-link:hover,
        .header-search-result-button:hover,
        .header-search-result-link:focus-visible,
        .header-search-result-button:focus-visible{
            border-color:#93b4e8;
            background:#eff6ff;
            outline:2px solid #2563eb;
            outline-offset:1px;
        }
        .header-search-autocomplete-item{
            display:grid;
            gap:6px;
        }
        .header-search-autocomplete-row{
            align-items:center;
            display:flex;
            justify-content:space-between;
            gap:10px;
        }
        .header-search-autocomplete-label{
            color:#0f172a;
            font-size:13px;
            font-weight:800;
            min-width:0;
            overflow-wrap:anywhere;
        }
        .header-search-result-button .header-search-autocomplete-label{
            margin:0;
        }
        .header-search-subject-lookup{
            width:calc(100% - 24px);
            margin:10px 12px;
            border:1px solid rgba(148, 163, 184, 0.14);
            border-radius:16px;
            background:#fff;
            box-shadow:0 6px 14px rgba(15, 23, 42, 0.035);
            padding:10px 12px;
            font-size:13px;
        }
        .header-search-subject-lookup:focus{
            border-color:rgba(37, 99, 235, 0.18);
            box-shadow:0 0 0 3px rgba(37, 99, 235, 0.08);
        }
        .header-search-clarification-grid{
            display:grid;
            gap:10px;
            padding:0 0 8px;
        }
        .header-search-subject-lookup.is-visible{
            display:block!important;
        }
        .header-search-selected-subject{
            display:none;
            margin:10px 12px 0;
            border:1px solid rgba(148, 163, 184, 0.16);
            border-radius:16px;
            padding:10px 12px;
            background: rgba(248, 250, 252, 0.92);
            color:#243245;
        }
        .header-search-selected-subject.is-visible{
            display:block!important;
        }
        .header-search-selected-subject strong{
            display:block;
            font-size:13px;
        }
        .header-search-selected-subject small{
            display:block;
            color:#708195;
            margin-top:4px;
        }
        .header-search-selected-subject .closeHeaderSearchSubject{
            color:#b42318!important;
            font-size:12px;
            font-weight:700;
            margin-top:6px;
            display:inline-block;
        }

        .wrappers .search_box .search_field .input{
            font-family: var(--font-family);
            font-size: 15px;
            font-weight: 500;
            letter-spacing: 0.01em;
            color: #1f2937;
            padding-right: 18px;
        }

        @media (max-width: 1180px) {
            .wrappers{
                width: 100%;
                transform: none;
            }

            .wrappers .search_box{
                position: relative;
                width: 100%;
                height: auto;
                flex-wrap: wrap;
                gap: 8px;
                max-width: 100%;
            }

            .wrappers .search_box .magic-search-mode{
                width: 100%;
                border-right: 0;
                padding-right: 0;
            }

            .wrappers .search_box .search_field{
                width: 100%;
                min-width: 0;
                height: 50px;
            }

            .wrappers .search_box .search_field .input{
                height: 50px;
            }

            div.header-search-results{
                top: calc(100% + 8px);
                max-height: 56vh;
                width: 100%;
            }
        }

        @media (max-width: 767px) {
            .wrappers .search_box{
                padding: 8px;
            }

            .wrappers .search_box .magic-search-mode-copy small{
                display:none;
            }

            .wrappers .search_box .magic-search-mode-copy strong{
                font-size: 11px;
                letter-spacing: 0.04em;
            }

            .wrappers .search_box .magic-search-mode-current-icon{
                display:none;
            }

            .wrappers .search_box .magic-search-mode-trigger{
                min-height: 48px;
            }

            div.header-search-results{
                border-radius: 16px;
                max-height: 60vh;
                min-height: auto;
            }

            .header-search-result-link,
            .header-search-result-button{
                margin: 8px 10px 0;
                padding: 11px 12px;
            }

            .header-search-surface-head{
                padding: 12px 12px 10px;
            }
        }

    </style>
    <style>
        .dashboardicon{
            display: flex;
            justify-content: flex-start;
            margin: 10px 0px 20px;
        }
        .activeSideItemWrapper{
            background:#fafafa !important;
            margin-left: 0px !important;
            padding: 5px 5px 5px 5px !important;
        }

        .admin-topbar-utility{
            margin-left: 0;
            position: relative;
        }

        .admin-topbar-right{
            align-items: center;
            display: flex;
            flex-wrap: nowrap;
            gap: 10px;
            min-height: 42px;
            margin-right: 0;
        }

        .admin-pending-request-indicator{
            align-items: center;
            background: #f4f5f9;
            border: 0;
            border-radius: 14px;
            box-sizing: border-box;
            box-shadow: none;
            color: #4b5563;
            display: inline-flex;
            flex: 0 0 auto;
            gap: 10px;
            height: 36px;
            justify-content: center;
            min-height: 36px;
            min-width: 78px;
            overflow: hidden;
            padding: 0 10px;
            text-decoration: none !important;
            transition: background 0.16s ease, color 0.16s ease;
            vertical-align: middle;
            white-space: nowrap;
        }

        .admin-pending-request-indicator:hover,
        .admin-pending-request-indicator:focus-visible{
            background: #eef1f7;
            color: #1f4ec9;
            outline: none;
            text-decoration: none !important;
        }

        .admin-pending-request-indicator:active{
            background: #e8edf6;
        }

        .admin-pending-request-indicator.is-active{
            color: #a33b31;
        }

        .admin-pending-request-icon{
            align-items: center;
            background: transparent;
            border-radius: 10px;
            box-sizing: border-box;
            color: #2962ff;
            display: inline-flex;
            flex: 0 0 30px;
            height: 30px;
            justify-content: center;
            width: 30px;
        }

        .admin-pending-request-icon i{
            display: inline-block;
            font-size: 14px;
            height: 14px;
            line-height: 14px;
            text-align: center;
            width: 14px;
        }

        .admin-pending-request-indicator.is-active .admin-pending-request-icon{
            background: transparent;
            color: #d94b3d;
        }

        .admin-pending-request-copy{
            display: none;
            flex-direction: column;
            flex: 0 0 92px;
            gap: 1px;
            justify-content: center;
            line-height: 1.1;
            min-width: 0;
        }

        .admin-pending-request-indicator.is-empty .admin-pending-request-copy{
            display: none;
        }

        .admin-pending-request-indicator.is-empty{
            gap: 10px;
            min-width: 78px;
            padding-right: 10px;
        }

        .admin-pending-request-copy small{
            color: #98a2b3;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .admin-pending-request-copy strong{
            color: inherit;
            font-size: 12px;
            font-weight: 700;
        }

        .admin-pending-request-count{
            align-items: center;
            background: #eef2ff;
            border-radius: 999px;
            box-sizing: border-box;
            color: #3758cc;
            display: inline-flex;
            flex: 0 0 auto;
            font-size: 11px;
            font-variant-numeric: tabular-nums;
            font-weight: 800;
            height: 26px;
            justify-content: center;
            line-height: 26px;
            min-width: 26px;
            padding: 0 8px;
        }

        .admin-pending-request-indicator.is-active .admin-pending-request-count{
            background: #ffe7e4;
            color: #bb3d31;
        }

        .admin-pending-request-indicator.is-withdraw .admin-pending-request-icon{
            background: transparent;
            color: #b7791f;
        }

        .admin-pending-request-indicator.is-withdraw .admin-pending-request-count{
            background: #fff4d6;
            color: #9a6700;
        }

        .admin-pending-request-indicator.is-support .admin-pending-request-icon{
            background: transparent;
            color: #15803d;
        }

        .admin-pending-request-indicator.is-support .admin-pending-request-count{
            background: #dcfce7;
            color: #15803d;
        }

        .admin-pending-request-indicator.is-support.is-active{
            color: #166534;
        }

        .admin-pending-request-indicator.is-support.is-new-message{
            animation: none;
        }

        .admin-pending-request-indicator.is-human-handoff .admin-pending-request-icon{
            background: rgba(245, 158, 11, 0.16);
            color: #b45309;
        }

        .admin-pending-request-indicator.is-human-handoff .admin-pending-request-count{
            background: #fef3c7;
            color: #b45309;
        }

        .admin-pending-request-indicator.is-human-handoff.is-active{
            color: #92400e;
        }

        .admin-pending-request-indicator.is-backup .admin-pending-request-icon{
            background: rgba(249, 115, 22, 0.12);
            color: #c2410c;
        }

        .admin-pending-request-indicator.is-backup .admin-pending-request-count{
            background: #ffedd5;
            color: #c2410c;
        }

        .admin-pending-request-indicator.is-backup.is-active{
            color: #9a3412;
        }

        .admin-pending-request-indicator.is-finance-integrity .admin-pending-request-icon{
            background: rgba(220, 38, 38, 0.14);
            color: #b91c1c;
        }

        .admin-pending-request-indicator.is-finance-integrity .admin-pending-request-count{
            background: #fee2e2;
            color: #b91c1c;
        }

        .admin-pending-request-indicator.is-finance-integrity.is-active{
            color: #991b1b;
        }

        .admin-profile-trigger{
            align-items: center;
            -webkit-backdrop-filter: blur(14px) saturate(1.08);
            backdrop-filter: blur(14px) saturate(1.08);
            background: #f3f5f9;
            border: 1px solid rgba(221, 226, 241, 0.95);
            border-radius: 999px;
            box-shadow: none;
            color: #1d2939;
            display: flex;
            gap: 8px;
            height: 36px;
            margin-left: 0;
            min-height: 36px;
            padding: 3px 9px 3px 4px;
            text-decoration: none !important;
            transition: background 0.16s ease, color 0.16s ease;
        }

        .admin-profile-trigger:hover,
        .admin-profile-trigger:focus-visible,
        .admin-profile-trigger.show{
            background: #e8edf6;
            color: #244ca1;
            outline: none;
            text-decoration: none !important;
        }

        .admin-profile-trigger:active{
            background: #e1e7f2;
        }

        .admin-profile-trigger.dropdown-toggle::after{
            content: none !important;
            display: none !important;
        }

        .admin-profile-avatar-wrap{
            display: inline-flex;
            flex: 0 0 28px;
            position: relative;
        }

        .admin-profile-trigger .user-avatar-c{
            --size: 28px;
            box-shadow: none !important;
        }

        .admin-profile-menu-head .user-avatar-c{
            box-shadow: 0 0 0 3px #ffffff, 0 0 0 4px rgba(49, 84, 165, 0.16);
        }

        .admin-profile-status-dot{
            background: #22a06b;
            border: 2px solid #ffffff;
            border-radius: 999px;
            bottom: -1px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
            height: 7px;
            position: absolute;
            right: -1px;
            width: 7px;
        }

        .admin-profile-copy{
            margin-left: 0;
            max-width: 140px;
            text-align: left;
        }

        .admin-profile-copy p{
            color: #26334d;
            line-height: 1.2;
            margin-bottom: 0;
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 13px;
            font-weight: 750;
            letter-spacing: -0.01em;
        }

        header.navbar{
            position: relative;
        }

        header.navbar > ul{
            align-items: center;
            display: flex;
            justify-content: space-between;
            gap: 24px;
            list-style: none;
            margin: 0;
            padding-right: 0;
            width: 100%;
        }

        header.navbar > ul > li:last-child{
            align-items: center;
            display: flex;
            flex: 0 1 auto;
            gap: 12px;
            margin-left: auto;
        }

        .admin-profile-chevron{
            color: #8190aa;
            font-size: 10px;
            transition: color 0.16s ease, transform 0.16s ease;
        }

        .admin-profile-trigger:hover .admin-profile-chevron,
        .admin-profile-trigger:focus-visible .admin-profile-chevron,
        .admin-profile-trigger.show .admin-profile-chevron{
            color: #3154a5;
        }

        .admin-profile-trigger.show .admin-profile-chevron{
            transform: rotate(180deg);
        }

        .admin-brand-row{
            align-items: center;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            width: 100%;
        }

        .admin-brand-row .navbar-brand a{
            align-items: center;
            background: transparent;
            color: inherit;
            display: inline-flex;
            gap: 10px;
            text-decoration: none;
            transform: none;
        }

        .admin-brand-row .navbar-brand,
        .admin-brand-row .navbar-brand:hover,
        .admin-brand-row .navbar-brand:focus,
        .admin-brand-row .navbar-brand a:hover,
        .admin-brand-row .navbar-brand a:focus{
            background: transparent;
            color: inherit;
            opacity: 1;
            text-decoration: none;
            transform: none;
        }

        .admin-brand-mark{
            filter: drop-shadow(0 12px 22px rgba(52, 92, 255, 0.2));
            flex: 0 0 42px;
            height: 42px;
            width: 42px;
        }

        .admin-brand-expand-icon { display: none; }

        .admin-brand-wordmark{
            align-items: center;
            color: #101828;
            display: inline-flex;
            font-size: 18px;
            font-weight: 900;
            gap: 7px;
            letter-spacing: 0;
            line-height: 1;
            white-space: nowrap;
        }

        .admin-brand-wordmark-badge{
            background: linear-gradient(145deg, #263b79, #3158b4);
            border: 1px solid rgba(255, 255, 255, 0.24);
            border-radius: 7px;
            box-shadow: 0 5px 12px rgba(31, 78, 201, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.18);
            color: #ffffff;
            display: inline-flex;
            font-size: 10px;
            font-weight: 850;
            justify-content: center;
            letter-spacing: 0.09em;
            line-height: 1;
            min-height: 21px;
            min-width: 28px;
            padding: 5px 6px 4px;
        }

        .admin-collapse-trigger{
            align-items: center;
            background: #ffffff;
            border: 1px solid rgba(221, 226, 241, 0.95);
            border-radius: 12px;
            color: #3d3d5f;
            display: inline-flex;
            height: 36px;
            justify-content: center;
            width: 36px;
        }

        .admin-sidebar-settings {
            position: relative;
            width: 100%;
        }

        .admin-sidebar-footer-link {
            align-items: center;
            border-radius: 10px;
            color: #667085;
            display: flex;
            gap: 11px;
            min-height: 44px;
            padding: 0 12px;
            text-decoration: none;
            transition: background 140ms ease, color 140ms ease;
            width: 100%;
        }

        .admin-sidebar-footer-link:hover,
        .admin-sidebar-footer-link:focus-visible,
        .admin-sidebar-footer-link.activeSideItem {
            background: rgba(32, 107, 196, 0.08);
            color: #1f4ec9;
            outline: none;
        }

        .admin-sidebar-footer-link .nav-link-icon {
            flex: 0 0 22px;
            margin-right: 0;
        }

        .admin-sidebar-footer-link .nav-link-title {
            color: inherit;
            flex: 1 1 auto;
            font-size: 13px;
            font-weight: 750;
        }

        .admin-sidebar-settings-trigger {
            align-items: center;
            background: transparent;
            border: 0;
            border-radius: 10px;
            color: #667085;
            display: flex;
            gap: 11px;
            min-height: 44px;
            padding: 0 12px;
            text-align: left;
            transition: background 140ms ease, color 140ms ease;
            width: 100%;
        }

        .admin-sidebar-settings-trigger:hover,
        .admin-sidebar-settings-trigger:focus-visible,
        .admin-sidebar-settings-trigger.show,
        .admin-sidebar-settings-trigger.activeSideItem {
            background: rgba(32, 107, 196, 0.08);
            color: #1f4ec9;
            outline: none;
        }

        .admin-sidebar-settings-trigger svg {
            flex: 0 0 22px;
        }

        .admin-sidebar-settings-trigger .nav-link-title {
            color: inherit;
            flex: 1 1 auto;
            font-size: 13px;
            font-weight: 750;
        }

        .admin-sidebar-settings-trigger::after {
            margin-left: auto;
        }

        .admin-sidebar-settings-menu {
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid rgba(221, 226, 241, 0.96);
            border-radius: 12px;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.16), 0 3px 10px rgba(15, 23, 42, 0.08);
            inset: auto 0 calc(100% + 8px) 0 !important;
            margin: 0 !important;
            min-width: 100%;
            padding: 7px;
            transform: none !important;
        }

        .admin-sidebar-settings-menu .dropdown-item {
            border-radius: 8px;
            color: #475467;
            font-size: 12px;
            font-weight: 650;
            padding: 9px 10px;
        }

        .admin-sidebar-settings-menu .dropdown-item:hover,
        .admin-sidebar-settings-menu .dropdown-item:focus {
            background: #f2f5fb;
            color: #1f4ec9;
        }

        .admin-sidebar-settings-menu-label {
            color: #98a2b3;
            display: block;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.08em;
            padding: 10px 10px 5px;
            text-transform: uppercase;
        }

        .admin-sidebar-settings-menu-divider {
            border-top: 1px solid #eaecf0;
            margin: 7px 3px 0;
        }

        .admin-page-content {
            color: #464564;
            margin-top: 30px;
            min-width: 0;
            padding: 0 15px 20px;
        }

        @media (min-width: 1024px) {
            .navbar.navbar-vertical.navbar-expand-lg {
                overflow: hidden !important;
                transition: width 180ms ease, box-shadow 180ms ease;
                width: 15rem !important;
            }

            .navbar.navbar-vertical.navbar-expand-lg .container-fluid {
                align-items: stretch;
                display: flex;
                flex-direction: column;
                height: 100dvh;
                min-height: 0;
                overflow: hidden;
                padding-bottom: 0;
            }

            .navbar.navbar-vertical.navbar-expand-lg .admin-brand-row {
                flex: 0 0 auto;
                min-height: 62px;
                padding: 8px 10px 6px 0;
            }

            .navbar.navbar-vertical.navbar-expand-lg #admin-navbar-menu {
                display: flex !important;
                flex: 1 1 auto;
                flex-direction: column;
                min-height: 0;
                overflow: hidden;
            }

            .navbar.navbar-vertical.navbar-expand-lg #admin-navbar-menu > .navbar-nav {
                flex: 1 1 auto;
                min-height: 0;
                overflow-x: hidden;
                overflow-y: auto;
                padding-bottom: 14px;
                -ms-overflow-style: none;
                scrollbar-width: none;
            }

            .navbar.navbar-vertical.navbar-expand-lg #admin-navbar-menu > .navbar-nav::-webkit-scrollbar {
                display: none;
                height: 0;
                width: 0;
            }

            .admin-sidebar-footer {
                background: rgba(255, 255, 255, 0.94);
                border-top: 1px solid rgba(226, 232, 240, 0.9);
                box-shadow: none;
                display: grid;
                flex: 0 0 auto;
                gap: 6px;
                grid-template-columns: minmax(0, 1fr);
                margin: 0 -12px;
                padding: 9px 12px 12px;
            }

            .admin-sidebar-footer-link,
            .admin-sidebar-settings-trigger {
                gap: 6px;
                min-width: 0;
                padding-left: 7px;
                padding-right: 7px;
            }

            .admin-sidebar-footer-link .nav-link-title,
            .admin-sidebar-settings-trigger .nav-link-title {
                font-size: 11px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .admin-sidebar-settings-menu {
                inset: auto 0 calc(100% + 8px) auto !important;
                min-width: 210px;
                width: 210px;
            }

            .admin-collapse-trigger {
                background: transparent;
                border: 0;
                border-radius: 10px;
                box-shadow: none;
                color: #667085;
                flex: 0 0 38px;
                height: 38px;
                padding: 0;
                transition: background 140ms ease, color 140ms ease;
                width: 38px;
            }

            .admin-collapse-trigger:hover,
            .admin-collapse-trigger:focus-visible {
                background: transparent;
                box-shadow: none;
                color: #1f4ec9;
                outline: none;
            }

            .admin-collapse-trigger:focus-visible {
                outline: 2px solid rgba(32, 107, 196, 0.24);
                outline-offset: 2px;
            }

            .admin-collapse-trigger svg {
                flex: 0 0 auto;
            }

            body:not(.admin-sidebar-collapsed) #adminDashboardRow {
                position: relative;
            }

            body:not(.admin-sidebar-collapsed) #adminDashboardRow > .nav-link {
                padding-right: 36px;
            }

            body:not(.admin-sidebar-collapsed) #adminDashboardRow > .admin-collapse-trigger {
                position: absolute;
                right: 30px;
                top: 50%;
                transform: translateY(-50%);
                width: 30px;
                height: 34px;
                border-radius: 8px;
            }

            body:not(.admin-sidebar-collapsed) #adminDashboardRow > .admin-collapse-trigger svg {
                width: 18px;
                height: 18px;
            }

            .admin-mobile-settings-nav {
                display: none !important;
            }

            .navbar-expand-lg.navbar-vertical ~ .navbar,
            .navbar-expand-lg.navbar-vertical ~ .page-wrapper {
                transition: margin-left 180ms ease, width 180ms ease;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg {
                --admin-collapsed-icon-gap: 4px;
                cursor: ew-resize;
                overflow: hidden !important;
                width: 5.25rem !important;
            }

            body.admin-sidebar-collapsed .navbar-expand-lg.navbar-vertical ~ .navbar,
            body.admin-sidebar-collapsed .navbar-expand-lg.navbar-vertical ~ .page-wrapper {
                margin-left: 5.25rem !important;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg .admin-brand-row {
                flex-direction: column;
                gap: var(--admin-collapsed-icon-gap);
                justify-content: flex-start;
                min-height: auto;
                padding: 8px 0 0;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg .navbar-brand {
                display: block;
                flex: 0 0 38px;
                height: 38px;
                margin: 0;
                padding: 0;
                width: 38px;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg .navbar-brand a {
                display: flex;
                font-size: 0;
                height: 38px;
                justify-content: center;
                min-width: 0;
                overflow: hidden;
                width: 100%;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg .navbar-brand svg {
                flex: 0 0 38px;
                height: 38px;
                width: 38px;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg .admin-brand-wordmark {
                display: none;
            }

            body.admin-sidebar-collapsed [data-admin-sidebar-brand] { position: relative; }
            body.admin-sidebar-collapsed [data-admin-sidebar-brand] .admin-brand-mark,
            body.admin-sidebar-collapsed [data-admin-sidebar-brand] .admin-brand-expand-icon {
                transition: opacity 180ms ease, transform 240ms cubic-bezier(0.22, 1, 0.36, 1);
                transform-origin: center;
            }
            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg .navbar-brand svg.admin-brand-expand-icon {
                position: absolute;
                inset: 0;
                margin: auto;
                display: block;
                width: 20px;
                height: 20px;
                opacity: 0;
                transform: translateX(-3px) scale(0.88);
                pointer-events: none;
                color: #1f4ec9;
            }
            body.admin-sidebar-collapsed [data-admin-sidebar-brand]:is(:hover, :focus-visible) .admin-brand-mark {
                opacity: 0;
                transform: translateX(3px) scale(0.92);
            }
            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg [data-admin-sidebar-brand]:is(:hover, :focus-visible) svg.admin-brand-expand-icon {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
            body.admin-sidebar-collapsed [data-admin-sidebar-brand]:focus-visible {
                outline: 2px solid rgba(32, 107, 196, 0.24);
                outline-offset: 3px;
                border-radius: 8px;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg .nav-link {
                border-radius: 10px;
                height: 38px;
                justify-content: center;
                margin-left: auto;
                margin-right: auto;
                min-height: 38px;
                padding: 0;
                width: 38px;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg #admin-navbar-menu > .navbar-nav {
                display: flex;
                flex-direction: column;
                gap: var(--admin-collapsed-icon-gap);
                padding-top: var(--admin-collapsed-icon-gap) !important;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg #admin-navbar-menu > .navbar-nav > .nav-item {
                margin: 0;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg .nav-link-icon {
                margin-right: 0 !important;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg .nav-link-title,
            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg .dropdown-toggle::after,
            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg .dropdown-menu,
            body.admin-sidebar-collapsed .admin-sidebar-settings-trigger .nav-link-title,
            body.admin-sidebar-collapsed .admin-sidebar-settings-trigger::after {
                display: none !important;
            }

            body.admin-sidebar-collapsed .admin-sidebar-footer {
                align-items: center;
                display: flex;
                flex-direction: column;
                gap: var(--admin-collapsed-icon-gap);
                justify-content: center;
                margin-left: -8px;
                margin-right: -8px;
                padding: var(--admin-collapsed-icon-gap) 10px;
            }

            body.admin-sidebar-collapsed .admin-collapse-trigger {
                display: none;
            }

            body.admin-sidebar-collapsed .admin-sidebar-settings-trigger {
                height: 38px;
                justify-content: center;
                min-height: 38px;
                padding: 0;
                width: 38px;
            }

            body.admin-sidebar-collapsed .admin-sidebar-settings {
                width: 38px;
            }

            body.admin-sidebar-collapsed .admin-sidebar-footer-link {
                height: 38px;
                justify-content: center;
                min-height: 38px;
                padding: 0;
                width: 38px;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg .admin-sidebar-settings-menu.is-collapsed-popover,
            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg #admin-navbar-menu > .navbar-nav > .nav-item.dropdown:not(.admin-mobile-settings-nav) > .dropdown-menu.is-collapsed-popover {
                background: rgba(255, 255, 255, 0.98);
                border: 1px solid rgba(221, 226, 241, 0.96);
                border-radius: 12px;
                box-shadow: 0 18px 42px rgba(15, 23, 42, 0.16), 0 3px 10px rgba(15, 23, 42, 0.08);
                display: block !important;
                inset: auto !important;
                margin: 0 !important;
                min-width: 210px;
                padding: 7px;
                position: fixed !important;
                transform: none !important;
                width: 210px;
                z-index: 1101;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg #admin-navbar-menu > .navbar-nav > .nav-item.dropdown:not(.admin-mobile-settings-nav) > .dropdown-menu.is-collapsed-popover .dropdown-item {
                border-radius: 8px;
                color: #475467;
                font-size: 12px;
                font-weight: 650;
                padding: 9px 10px;
                white-space: normal;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg #admin-navbar-menu > .navbar-nav > .nav-item.dropdown:not(.admin-mobile-settings-nav) > .dropdown-menu.is-collapsed-popover .dropdown-item:hover,
            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg #admin-navbar-menu > .navbar-nav > .nav-item.dropdown:not(.admin-mobile-settings-nav) > .dropdown-menu.is-collapsed-popover .dropdown-item:focus {
                background: #f2f5fb;
                color: #1f4ec9;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg #admin-navbar-menu > .navbar-nav > .nav-item.dropdown:not(.admin-mobile-settings-nav) > .dropdown-toggle.is-collapsed-popover-trigger {
                background: rgba(32, 107, 196, 0.08);
                color: #1f4ec9 !important;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg .admin-sidebar-settings-menu.is-collapsed-popover::before {
                background: rgba(255, 255, 255, 0.98);
                border-bottom: 1px solid rgba(221, 226, 241, 0.96);
                border-left: 1px solid rgba(221, 226, 241, 0.96);
                content: '';
                height: 9px;
                left: -5px;
                position: absolute;
                top: calc(100% - 24px);
                transform: rotate(45deg);
                width: 9px;
            }

            body.admin-sidebar-collapsed .navbar.navbar-vertical.navbar-expand-lg #admin-navbar-menu > .navbar-nav > .nav-item.dropdown:not(.admin-mobile-settings-nav) > .dropdown-menu.is-collapsed-popover::before {
                background: rgba(255, 255, 255, 0.98);
                border-bottom: 1px solid rgba(221, 226, 241, 0.96);
                border-left: 1px solid rgba(221, 226, 241, 0.96);
                content: '';
                height: 9px;
                left: -5px;
                position: absolute;
                top: var(--admin-collapsed-popover-arrow-top, 24px);
                transform: translateY(-50%) rotate(45deg);
                width: 9px;
            }
        }

        .admin-profile-menu{
            -webkit-backdrop-filter: blur(20px) saturate(1.12);
            backdrop-filter: blur(20px) saturate(1.12);
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid rgba(208, 216, 232, 0.9);
            border-radius: 16px;
            box-shadow: 0 24px 54px rgba(15, 23, 42, 0.17), 0 5px 16px rgba(15, 23, 42, 0.08);
            margin-top: 12px;
            min-width: 238px;
            padding: 7px;
        }

        .admin-profile-menu-head{
            align-items: center;
            display: flex;
            gap: 11px;
            padding: 10px 10px 12px;
        }

        .admin-profile-menu-head-copy{
            display: flex;
            flex-direction: column;
            line-height: 1.25;
            min-width: 0;
        }

        .admin-profile-menu-head-copy strong{
            color: #1d2939;
            font-size: 13px;
            font-weight: 800;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .admin-profile-menu-head-copy span{
            color: #8a96aa;
            font-size: 10px;
            font-weight: 750;
            letter-spacing: 0.08em;
            margin-top: 2px;
            text-transform: uppercase;
        }

        .admin-profile-menu-divider{
            background: linear-gradient(90deg, transparent, rgba(208, 216, 232, 0.9) 14%, rgba(208, 216, 232, 0.9) 86%, transparent);
            height: 1px;
            margin: 0 3px 6px;
        }

        .admin-profile-menu .dropdown-item{
            align-items: center;
            border-radius: 10px;
            color: #4b5563;
            display: flex;
            font-size: 13px;
            font-weight: 600;
            gap: 9px;
            padding: 10px 12px;
            text-decoration: none !important;
            transition: background 0.14s ease, color 0.14s ease;
        }

        .admin-profile-menu .dropdown-item i{
            color: #98a2b3;
            font-size: 12px;
            width: 14px;
        }

        .admin-profile-menu .dropdown-item:hover,
        .admin-profile-menu .dropdown-item:focus-visible{
            background: #f1f5ff;
            color: #244ca1;
            outline: none;
            text-decoration: none !important;
        }

        .admin-profile-menu .dropdown-item:last-child{
            color: #a33b31;
            margin-top: 3px;
        }

        .admin-profile-menu .dropdown-item:last-child:hover,
        .admin-profile-menu .dropdown-item:last-child:focus-visible{
            background: #fff1ef;
            color: #962f26;
        }

        .admin-mobile-drawer-head,
        .admin-mobile-nav-backdrop,
        .admin-mobile-command-dock,
        .admin-mobile-search-panel {
            display: none;
        }

        .admin-mobile-context-controls {
            align-items: center;
            display: flex;
            gap: 8px;
        }

        .admin-language-switch {
            align-items: center;
            background: #f3f5f9;
            border: 1px solid rgba(221, 226, 241, 0.95);
            border-radius: 999px;
            display: inline-flex;
            gap: 2px;
            padding: 3px;
        }

        .admin-language-switch a {
            align-items: center;
            border-radius: 999px;
            color: #667085;
            display: inline-flex;
            font-size: 11px;
            font-weight: 800;
            height: 28px;
            justify-content: center;
            min-width: 34px;
            padding: 0 9px;
            text-decoration: none !important;
            transition: background 0.16s ease, color 0.16s ease;
        }

        .admin-language-switch a.is-active {
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
            color: #244ca1;
        }

        .admin-language-switch a:hover,
        .admin-language-switch a:focus-visible {
            background: #e8edf6;
            color: #244ca1;
            outline: none;
            text-decoration: none !important;
        }

        .admin-language-switch a:active {
            background: #e1e7f2;
        }

        .admin-language-switch-desktop {
            display: block;
            height: 36px;
            min-width: 54px;
            padding: 3px;
            position: relative;
        }

        .admin-language-switch-desktop::before {
            content: "";
            height: 8px;
            left: 0;
            position: absolute;
            right: 0;
            top: 100%;
        }

        .admin-language-current {
            align-items: center;
            background: transparent;
            border-radius: 999px;
            box-shadow: none;
            color: #244ca1;
            display: inline-flex;
            font-size: 11px;
            font-weight: 800;
            height: 28px;
            justify-content: center;
            min-width: 46px;
            padding: 0 15px 0 10px;
            position: relative;
        }

        .admin-language-current::after {
            border-bottom: 1.5px solid currentColor;
            border-right: 1.5px solid currentColor;
            content: "";
            height: 5px;
            position: absolute;
            right: 9px;
            top: 10px;
            transform: rotate(45deg);
            width: 5px;
        }

        .admin-language-alternative {
            background: #f3f5f9;
            border: 1px solid rgba(221, 226, 241, 0.95);
            border-radius: 999px;
            left: -1px;
            opacity: 0;
            padding: 3px;
            pointer-events: none;
            position: absolute;
            right: -1px;
            top: calc(100% + 6px);
            transform: translateY(-3px);
            transition: opacity 160ms ease, transform 160ms ease, visibility 0s linear 160ms;
            visibility: hidden;
        }

        .admin-language-alternative a {
            background: transparent;
            box-shadow: none;
            color: #244ca1;
            width: 100%;
        }

        .admin-language-switch-desktop:hover .admin-language-current,
        .admin-language-switch-desktop:focus-within .admin-language-current {
            background: #e8edf6;
            color: #244ca1;
        }

        .admin-language-switch-desktop:hover .admin-language-alternative,
        .admin-language-switch-desktop:focus-within .admin-language-alternative {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
            transition-delay: 0s;
            visibility: visible;
        }

        @media (prefers-reduced-motion: reduce) {
            body.admin-sidebar-collapsed [data-admin-sidebar-brand] .admin-brand-mark,
            body.admin-sidebar-collapsed [data-admin-sidebar-brand] .admin-brand-expand-icon {
                transition: none !important;
                transform: none !important;
            }
            .admin-pending-request-indicator,
            .admin-language-switch a,
            .admin-language-alternative,
            .admin-profile-trigger,
            .admin-profile-chevron {
                transition: none !important;
            }

        }

        .admin-sidebar-tooltip {
            -webkit-backdrop-filter: blur(14px) saturate(1.15);
            backdrop-filter: blur(14px) saturate(1.15);
            background: rgba(17, 24, 39, 0.94);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 9px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.24), 0 2px 7px rgba(15, 23, 42, 0.16);
            color: #f8fafc;
            font-size: 12px;
            font-weight: 700;
            left: 0;
            letter-spacing: -0.01em;
            line-height: 1.25;
            max-width: min(220px, calc(100vw - 120px));
            opacity: 0;
            padding: 8px 11px;
            pointer-events: none;
            position: fixed;
            top: 0;
            transform: translate3d(-2px, -50%, 0) scale(0.99);
            transform-origin: left center;
            transition: opacity 85ms ease-out, transform 95ms cubic-bezier(0.2, 0.8, 0.2, 1), visibility 0s linear 95ms;
            visibility: hidden;
            white-space: normal;
            width: max-content;
            z-index: 1100;
        }

        .admin-sidebar-tooltip::before {
            background: rgba(17, 24, 39, 0.94);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            content: '';
            height: 8px;
            left: -5px;
            position: absolute;
            top: 50%;
            transform: translateY(-50%) rotate(45deg);
            width: 8px;
        }

        .admin-sidebar-tooltip.is-visible {
            opacity: 1;
            transform: translate3d(0, -50%, 0) scale(1);
            transition-delay: 0s;
            visibility: visible;
        }

        @media (max-width: 1023px) {
            .admin-sidebar-tooltip {
                display: none !important;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .admin-sidebar-tooltip {
                transition: none;
            }
        }
    </style>

    @yield("style")
    @php($adminResponsiveCss = 'cms-assets/css/admin-responsive.css')
    <link rel="stylesheet" href="{{ asset($adminResponsiveCss) }}?v={{ filemtime(public_path($adminResponsiveCss)) }}" />
    @php($adminUnifiedTablesCss = 'cms-assets/css/admin-unified-tables.css')
    <link rel="stylesheet" href="{{ asset($adminUnifiedTablesCss) }}?v={{ filemtime(public_path($adminUnifiedTablesCss)) }}" />
    <link rel="stylesheet" href="{{ asset('cms-assets/css/catalog-admin.css') }}">
</head>

<body class="antialiased">
<script>
    (function () {
        if (!window.matchMedia('(min-width: 1024px)').matches) {
            return;
        }

        let collapsed = false;
        try {
            const storedState = window.localStorage.getItem('cms.admin.sidebarCollapsed.v2');
            collapsed = storedState === null ? false : storedState === '1';
        } catch (error) {
            collapsed = false;
        }

        document.body.classList.toggle('admin-sidebar-collapsed', collapsed);
    })();
</script>
<div class="wrapper">
    <aside class="navbar navbar-vertical navbar-expand-lg navbar-dark"><div class="container-fluid">
        <button class="navbar-toggler" type="button" data-admin-mobile-nav-toggle aria-controls="admin-navbar-menu" aria-expanded="false" aria-label="{{ __('admin.mobile.open_menu') }}"><span class="navbar-toggler-icon"></span></button>
            <div class="admin-brand-row">
                <h1 class="navbar-brand navbar-brand-autodark">
                    <a href="/cms/admin/" data-admin-sidebar-brand aria-label="Rise CMS">
                        <svg class="admin-brand-mark" fill="none" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
                            <defs>
                                <linearGradient id="riseCmsLogoBg" x1="9" x2="55" y1="55" y2="8" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#1828ff"></stop>
                                    <stop offset="0.52" stop-color="#8b5cf6"></stop>
                                    <stop offset="1" stop-color="#20e3b2"></stop>
                                </linearGradient>
                                <linearGradient id="riseCmsLogoAccent" x1="16" x2="52" y1="12" y2="50" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#f8d35c"></stop>
                                    <stop offset="1" stop-color="#ff5e7d"></stop>
                                </linearGradient>
                            </defs>
                            <rect width="64" height="64" rx="18" fill="url(#riseCmsLogoBg)"></rect>
                            <path fill="url(#riseCmsLogoAccent)" d="M47.5 12.5 55 29.6 43.7 51.5 30.2 38.2l6.2-14.9 11.1-10.8Z" opacity=".92"></path>
                            <path fill="#ffffff" d="M18 48V16h17.2c7.5 0 12.2 4.1 12.2 10.5 0 4.5-2.4 7.8-6.4 9.4L49 48H38.4l-6.8-10.7h-4.1V48H18Zm9.5-18.7h6.3c2.8 0 4.4-1.2 4.4-3.3 0-2.1-1.6-3.3-4.4-3.3h-6.3v6.6Z"></path>
                            <path fill="#ffffff" d="M18 52h21v3.8H18V52Z" opacity=".86"></path>
                        </svg>
                        <svg class="admin-brand-expand-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <rect x="3" y="3" width="18" height="18" rx="4"></rect>
                            <path d="M9 3v18"></path>
                        </svg>
                        <span class="admin-brand-wordmark" aria-label="{{ __('admin_extra.25654c797a97') }}">
                            <span aria-hidden="true">Rise</span>
                            <span class="admin-brand-wordmark-badge" aria-hidden="true">BO</span>
                        </span>
                    </a>
                </h1>
                <button type="button" class="opened admin-collapse-trigger" id="collapseMenu" aria-label="{{ __('admin.navigation.collapse') }}" aria-expanded="true" aria-controls="admin-navbar-menu" data-collapse-label="{{ __('admin.navigation.collapse') }}" data-expand-label="{{ __('admin.navigation.expand') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <rect x="3" y="3" width="18" height="18" rx="4"></rect>
                        <path d="M9 3v18"></path>
                    </svg>
                </button>
            </div>

        <div class="collapse navbar-collapse" id="admin-navbar-menu">
            <div class="admin-mobile-drawer-head"><strong>Rise CMS</strong><button class="admin-mobile-nav-close" type="button" data-admin-mobile-nav-close aria-label="{{ __('admin.mobile.close_menu') }}">×</button></div>
            <ul class="navbar-nav pt-lg-3">
                @include('cms.admin.catalog.navigation')
            </ul>
        </div>
    </div></aside>
    <div class="admin-mobile-nav-backdrop" data-admin-mobile-nav-backdrop hidden></div>
    <header class="navbar" style="padding:15px 0!important"><div class="container-fluid"><button type="button" class="admin-mobile-menu-trigger" data-admin-mobile-nav-toggle aria-controls="admin-navbar-menu" aria-expanded="false" aria-label="{{ __('admin.mobile.open_menu') }}" data-open-label="{{ __('admin.mobile.open_menu') }}" data-close-label="{{ __('admin.mobile.close_menu') }}"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button><a href="{{ route('cms.front.home') }}">{{ __('cms_public.shell.home') }}</a><div class="admin-language-switch" role="group" aria-label="{{ __('admin.language.label') }}"><a href="{{ $adminLocaleUrl('en') }}" lang="en">EN</a><a href="{{ $adminLocaleUrl('tr') }}" lang="tr">TR</a></div><a href="{{ route('cms.logout') }}">{{ __('cms_public.shell.logout') }}</a></div></header>
    <div class="page-wrapper"><div class="parca"></div><div class="yama"></div><div class="admin-page-content"><a href="{{ route('cms.admin.sportsbook.index') }}">Sportsbook operations</a> <a href="{{ route('cms.admin.betting.liveChessCockpit') }}">Chess Live Cockpit</a>
@yield('content')</div></div>
</div>
<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{asset("vendor/bootstrap-table/bootstrap-table.min.js")}}"></script>
<script src="{{asset("vendor/toastr/toastr.min.js")}}"></script>
<script>
    window.CMS_ADMIN_UI = {
        tableLocale: @json($adminLocale === 'tr' ? 'tr-TR' : 'en-US'),
        successMessage: @json(__('admin.common.success_message')),
        information: @json(__('admin.common.information')),
        warning: @json(__('admin.common.warning')),
        error: @json(__('admin.common.error')),
        requestFailedRefresh: @json(__('admin.common.request_failed_refresh')),
        active: @json(__('admin.common.active')),
        passive: @json(__('admin.common.passive')),
        noImage: @json(__('admin.common.no_image')),
        tableLoading: @json(__('admin.common.table_loading')),
        tableRecordsPerPage: @json(__('admin.common.table_records_per_page')),
        tableShowingRows: @json(__('admin.common.table_showing_rows')),
        tableShowingRowsFiltered: @json(__('admin.common.table_showing_rows_filtered')),
        tableDetailRows: @json(__('admin.common.table_detail_rows')),
        tableSearch: @json(__('admin.common.table_search')),
        tableNoMatches: @json(__('admin.common.table_no_matches')),
        tableAllRows: @json(__('admin.common.table_all_rows')),
        tableClearSearch: @json(__('admin.common.table_clear_search'))
    };
</script>
<script src="{{ asset('cms-assets/js/common.js') }}?v={{ filemtime(public_path('cms-assets/js/common.js')) }}"></script>
<script src="{{ asset('cms-assets/js/admin-notice.js') }}?v={{ filemtime(public_path('cms-assets/js/admin-notice.js')) }}"></script>
<script>
    (function () {
        const mobileQuery = window.matchMedia('(max-width: 1023px)');
        const toggler = document.querySelector('.navbar.navbar-vertical .navbar-toggler[aria-controls="admin-navbar-menu"]');
        const navToggleButtons = document.querySelectorAll('[data-admin-mobile-nav-toggle]');
        const menu = document.getElementById('admin-navbar-menu');
        const backdrop = document.querySelector('[data-admin-mobile-nav-close].admin-mobile-nav-backdrop');
        const closeButtons = document.querySelectorAll('[data-admin-mobile-nav-close]');
        const searchPanel = document.querySelector('[data-admin-mobile-search-panel]');
        const searchToggle = document.querySelector('[data-admin-mobile-search-toggle]');
        const searchInput = document.getElementById('admin-mobile-search-input');
        const sidebarDropdownToggles = menu ? menu.querySelectorAll('.dropdown-toggle') : [];

        if (!toggler || !menu) {
            return;
        }

        let closeBackdropTimer = null;
        let lastMobileNavToggleAt = 0;
        let lastMobileDropdownToggleAt = 0;

        function isMenuExpanded() {
            return menu.classList.contains('show')
                || document.body.classList.contains('admin-mobile-nav-open');
        }

        function setSearchExpanded(isExpanded) {
            if (! searchPanel || ! searchToggle) {
                return;
            }

            searchPanel.hidden = ! isExpanded;
            searchToggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
            document.body.classList.toggle('admin-mobile-search-open', isExpanded);

            if (isExpanded && searchInput) {
                window.requestAnimationFrame(function () {
                    searchInput.focus({ preventScroll: true });
                    searchInput.select();
                });
            }
        }

        function setSidebarBootstrapDropdownsEnabled(isEnabled) {
            sidebarDropdownToggles.forEach(function (trigger) {
                if (isEnabled) {
                    if (trigger.dataset.adminBootstrapToggle) {
                        trigger.setAttribute('data-bs-toggle', trigger.dataset.adminBootstrapToggle);
                        delete trigger.dataset.adminBootstrapToggle;
                    }

                    if (trigger.dataset.adminLegacyToggle) {
                        trigger.setAttribute('data-toggle', trigger.dataset.adminLegacyToggle);
                        delete trigger.dataset.adminLegacyToggle;
                    }

                    return;
                }

                const bootstrapToggle = trigger.getAttribute('data-bs-toggle');
                const legacyToggle = trigger.getAttribute('data-toggle');

                if (bootstrapToggle) {
                    trigger.dataset.adminBootstrapToggle = bootstrapToggle;
                    trigger.removeAttribute('data-bs-toggle');
                }

                if (legacyToggle) {
                    trigger.dataset.adminLegacyToggle = legacyToggle;
                    trigger.removeAttribute('data-toggle');
                }
            });
        }

        function closeAllDropdowns(exceptDropdown) {
            menu.querySelectorAll('.dropdown.show').forEach(function (openDropdown) {
                if (exceptDropdown && openDropdown === exceptDropdown) {
                    return;
                }

                openDropdown.classList.remove('show');
            });

            menu.querySelectorAll('.dropdown-menu.show').forEach(function (openMenu) {
                if (exceptDropdown && exceptDropdown.contains(openMenu)) {
                    return;
                }

                openMenu.classList.remove('show');
            });

            menu.querySelectorAll('.dropdown-toggle[aria-expanded="true"], .dropdown-toggle.show').forEach(function (openTrigger) {
                if (exceptDropdown && exceptDropdown.contains(openTrigger)) {
                    return;
                }

                openTrigger.classList.remove('show');
                openTrigger.setAttribute('aria-expanded', 'false');
            });
        }

        function setMenuExpanded(isExpanded) {
            if (closeBackdropTimer) {
                window.clearTimeout(closeBackdropTimer);
                closeBackdropTimer = null;
            }

            if (backdrop && isExpanded) {
                backdrop.hidden = false;
            }

            menu.classList.toggle('show', isExpanded);
            navToggleButtons.forEach(function (button) {
                button.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                if (button.dataset.openLabel) {
                    button.setAttribute('aria-label', isExpanded ? button.dataset.closeLabel : button.dataset.openLabel);
                }
            });
            document.body.classList.toggle('admin-mobile-nav-open', isExpanded);

            if (isExpanded) {
                setSearchExpanded(false);
                openActiveDropdown();
            } else {
                closeAllDropdowns();

                if (backdrop) {
                    closeBackdropTimer = window.setTimeout(function () {
                        if (!document.body.classList.contains('admin-mobile-nav-open')) {
                            backdrop.hidden = true;
                        }
                    }, 240);
                }
            }
        }

        function setDropdownExpanded(trigger, willOpen) {
            const dropdown = trigger.closest('.dropdown');
            const dropdownMenu = dropdown ? dropdown.querySelector('.dropdown-menu') : null;

            if (!dropdownMenu) {
                return false;
            }

            closeAllDropdowns(dropdown);

            dropdownMenu.classList.toggle('show', willOpen);
            dropdown.classList.toggle('show', willOpen);
            trigger.classList.toggle('show', willOpen);
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

            return true;
        }

        function isDropdownExpanded(trigger) {
            const dropdown = trigger.closest('.dropdown');
            const dropdownMenu = dropdown ? dropdown.querySelector('.dropdown-menu') : null;

            return !!(
                dropdown && (
                    dropdown.classList.contains('show')
                    || dropdownMenu && dropdownMenu.classList.contains('show')
                    || trigger.classList.contains('show')
                    || trigger.getAttribute('aria-expanded') === 'true'
                )
            );
        }

        function openActiveDropdown() {
            if (!mobileQuery.matches) {
                return;
            }

            closeAllDropdowns();

            const activeTrigger = menu.querySelector('.dropdown-toggle.activeSideItem');
            if (activeTrigger) {
                setDropdownExpanded(activeTrigger, true);
            }
        }

        function handleMobileDropdownToggle(event) {
            if (!mobileQuery.matches) {
                return;
            }

            const trigger = event.target.closest('#admin-navbar-menu .dropdown-toggle');
            if (!trigger) {
                return;
            }

            const dropdown = trigger.closest('.dropdown');
            const dropdownMenu = dropdown ? dropdown.querySelector('.dropdown-menu') : null;

            if (!dropdownMenu) {
                return;
            }

            const now = Date.now();
            if (event.type === 'click' && now - lastMobileDropdownToggleAt < 420) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                return;
            }

            lastMobileDropdownToggleAt = now;
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            event.adminNavHandled = true;
            setDropdownExpanded(trigger, !isDropdownExpanded(trigger));
        }

        function handleMobileNavToggle(event) {
            if (!mobileQuery.matches) {
                return;
            }

            const now = Date.now();
            if (event.type === 'click' && now - lastMobileNavToggleAt < 120) {
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();
                return;
            }

            lastMobileNavToggleAt = now;
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            setMenuExpanded(!isMenuExpanded());
        }

        navToggleButtons.forEach(function (button) {
            button.addEventListener('pointerup', handleMobileNavToggle, true);
            button.addEventListener('click', handleMobileNavToggle, true);
        });

        if (searchToggle) {
            searchToggle.addEventListener('click', function (event) {
                if (! mobileQuery.matches) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                setMenuExpanded(false);
                setSearchExpanded(! document.body.classList.contains('admin-mobile-search-open'));
            });
        }

        document.addEventListener('pointerup', handleMobileDropdownToggle, true);
        document.addEventListener('click', handleMobileDropdownToggle, true);

        sidebarDropdownToggles.forEach(function (trigger) {
            trigger.addEventListener('pointerup', handleMobileDropdownToggle, true);
            trigger.addEventListener('click', handleMobileDropdownToggle, true);
        });

        closeButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                if (!mobileQuery.matches) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                setMenuExpanded(false);
            });
        });

        menu.addEventListener('click', function (event) {
            if (!mobileQuery.matches) {
                return;
            }

            if (event.target.closest('.dropdown-toggle')) {
                handleMobileDropdownToggle(event);
                return;
            }

            const link = event.target.closest('a');
            if (!link || link.classList.contains('dropdown-toggle') || link.closest('.dropdown-menu') && link.getAttribute('href') === '#') {
                return;
            }

            if (link.getAttribute('href') && link.getAttribute('href') !== '#') {
                setMenuExpanded(false);
            }
        });

        document.addEventListener('click', function (event) {
            if (!mobileQuery.matches || !menu.classList.contains('show')) {
                if (
                    mobileQuery.matches
                    && document.body.classList.contains('admin-mobile-search-open')
                    && searchPanel
                    && searchToggle
                    && ! searchPanel.contains(event.target)
                    && ! searchToggle.contains(event.target)
                ) {
                    setSearchExpanded(false);
                }

                return;
            }

            if (menu.contains(event.target) || Array.from(navToggleButtons).some(function (button) { return button.contains(event.target); })) {
                return;
            }

            setMenuExpanded(false);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape' || ! mobileQuery.matches) {
                return;
            }

            if (menu.classList.contains('show')) {
                setMenuExpanded(false);
                const dockToggle = document.querySelector('.admin-mobile-menu-trigger');
                (dockToggle || toggler).focus();
                return;
            }

            if (document.body.classList.contains('admin-mobile-search-open')) {
                setSearchExpanded(false);
                searchToggle && searchToggle.focus();
            }
        });

        mobileQuery.addEventListener('change', function (event) {
            if (!event.matches) {
                setSidebarBootstrapDropdownsEnabled(true);
                document.body.classList.remove('admin-mobile-nav-open');
                document.body.classList.remove('admin-mobile-search-open');
                navToggleButtons.forEach(function (button) {
                    button.setAttribute('aria-expanded', 'false');
                });
                setSearchExpanded(false);
                if (backdrop) {
                    backdrop.hidden = true;
                }
            } else {
                setSidebarBootstrapDropdownsEnabled(false);
                setMenuExpanded(false);
            }
        });

        if (mobileQuery.matches) {
            setSidebarBootstrapDropdownsEnabled(false);
            setMenuExpanded(false);
        }
    })();
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const readyTimers = new WeakMap();

        document.addEventListener('show.bs.modal', function (event) {
            const modal = event.target;
            if (!modal || !modal.classList) {
                return;
            }

            const previousTimer = readyTimers.get(modal);
            if (previousTimer) {
                window.clearTimeout(previousTimer);
            }

            modal.classList.remove('is-ready');
            modal.classList.add('is-hydrating');
        });

        document.addEventListener('shown.bs.modal', function (event) {
            const modal = event.target;
            if (!modal || !modal.classList) {
                return;
            }

            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    const timer = window.setTimeout(function () {
                        readyTimers.delete(modal);
                        modal.classList.remove('is-hydrating');
                        modal.classList.add('is-ready');
                    }, 160);

                    readyTimers.set(modal, timer);
                });
            });
        });

        document.addEventListener('hidden.bs.modal', function (event) {
            const modal = event.target;
            if (!modal || !modal.classList) {
                return;
            }

            const previousTimer = readyTimers.get(modal);
            if (previousTimer) {
                window.clearTimeout(previousTimer);
                readyTimers.delete(modal);
            }

            modal.classList.remove('is-hydrating', 'is-ready');
        });
    });
</script>
<script>
        (function () {
            const collapseButton = document.getElementById('collapseMenu');
            const collapseButtonHome = collapseButton ? collapseButton.parentElement : null;
            const dashboardRow = document.getElementById('adminDashboardRow');
            const collapseText = document.getElementById('collapseText');
            const sidebarRoot = document.querySelector('.navbar.navbar-vertical.navbar-expand-lg');
            const sidebarBrandLink = sidebarRoot ? sidebarRoot.querySelector('[data-admin-sidebar-brand]') : null;
            const sidebarMenu = document.getElementById('admin-navbar-menu');
            const sidebarSettingsButton = document.getElementById('adminSidebarSettings');
            const sidebarSettingsRoot = sidebarSettingsButton ? sidebarSettingsButton.closest('.admin-sidebar-settings') : null;
            const collapsedSidebarDropdownRoots = sidebarMenu
                ? Array.from(sidebarMenu.querySelectorAll(':scope > .navbar-nav > .nav-item.dropdown:not(.admin-mobile-settings-nav)'))
                : [];
            const sidebarTooltip = document.getElementById('adminSidebarTooltip');
            const sidebarStorageKey = 'cms.admin.sidebarCollapsed.v2';
            const desktopQuery = window.matchMedia('(min-width: 1024px)');
            let tooltipShowTimer = null;
            let tooltipHideTimer = null;
            let tooltipScrollTimer = null;
            let tooltipTrigger = null;
            let settingsPopoverShowTimer = null;
            let settingsPopoverHideTimer = null;
            let collapsedDropdownPopoverRoot = null;
            let collapsedDropdownPopoverShowTimer = null;
            let collapsedDropdownPopoverHideTimer = null;
            const collapsedDropdownOriginalExpanded = new WeakMap();

            function sidebarTooltipItems() {
                return sidebarRoot
                    ? Array.from(sidebarRoot.querySelectorAll('#admin-navbar-menu > .navbar-nav > .nav-item > .nav-link, .admin-sidebar-footer-link, #collapseMenu'))
                    : [];
            }

            function tooltipLabel(trigger) {
                const label = trigger ? trigger.querySelector('.nav-link-title') : null;

                if (label) {
                    return label.textContent.replace(/\s+/g, ' ').trim();
                }

                return trigger ? (trigger.getAttribute('aria-label') || '').trim() : '';
            }

            function clearTooltipTimers() {
                if (tooltipShowTimer) {
                    window.clearTimeout(tooltipShowTimer);
                    tooltipShowTimer = null;
                }
                if (tooltipHideTimer) {
                    window.clearTimeout(tooltipHideTimer);
                    tooltipHideTimer = null;
                }
                if (tooltipScrollTimer) {
                    window.clearTimeout(tooltipScrollTimer);
                    tooltipScrollTimer = null;
                }
            }

            function hideSidebarTooltip(immediate = false) {
                if (!sidebarTooltip) {
                    return;
                }

                if (tooltipShowTimer) {
                    window.clearTimeout(tooltipShowTimer);
                    tooltipShowTimer = null;
                }
                if (tooltipScrollTimer) {
                    window.clearTimeout(tooltipScrollTimer);
                    tooltipScrollTimer = null;
                }

                const hide = function () {
                    if (tooltipTrigger) {
                        tooltipTrigger.removeAttribute('aria-describedby');
                    }
                    tooltipTrigger = null;
                    sidebarTooltip.classList.remove('is-visible');
                    sidebarTooltip.setAttribute('aria-hidden', 'true');
                    tooltipHideTimer = null;
                };

                if (immediate) {
                    if (tooltipHideTimer) {
                        window.clearTimeout(tooltipHideTimer);
                    }
                    hide();
                    return;
                }

                tooltipHideTimer = window.setTimeout(hide, 35);
            }

            function positionSidebarTooltip(trigger) {
                if (!sidebarTooltip || !trigger) {
                    return;
                }

                const triggerRect = trigger.getBoundingClientRect();
                const tooltipHeight = sidebarTooltip.offsetHeight;
                const viewportPadding = 12;
                const centeredTop = triggerRect.top + (triggerRect.height / 2);
                const minTop = viewportPadding + (tooltipHeight / 2);
                const maxTop = window.innerHeight - viewportPadding - (tooltipHeight / 2);

                sidebarTooltip.style.left = Math.round(triggerRect.right + 7) + 'px';
                sidebarTooltip.style.top = Math.round(Math.min(Math.max(centeredTop, minTop), maxTop)) + 'px';
            }

            function showSidebarTooltip(trigger, immediate = false) {
                if (
                    !sidebarTooltip
                    || !trigger
                    || !desktopQuery.matches
                    || !document.body.classList.contains('admin-sidebar-collapsed')
                ) {
                    return;
                }

                if (trigger.matches('#admin-navbar-menu > .navbar-nav > .nav-item.dropdown > .dropdown-toggle')) {
                    return;
                }

                const label = trigger.dataset.adminSidebarTooltip || '';
                if (!label) {
                    return;
                }

                clearTooltipTimers();
                const show = function () {
                    if (!document.body.classList.contains('admin-sidebar-collapsed')) {
                        return;
                    }

                    if (tooltipTrigger && tooltipTrigger !== trigger) {
                        tooltipTrigger.removeAttribute('aria-describedby');
                    }
                    tooltipTrigger = trigger;
                    sidebarTooltip.textContent = label;
                    sidebarTooltip.setAttribute('aria-hidden', 'false');
                    trigger.setAttribute('aria-describedby', sidebarTooltip.id);
                    sidebarTooltip.classList.add('is-visible');
                    positionSidebarTooltip(trigger);
                    tooltipShowTimer = null;
                };

                tooltipShowTimer = window.setTimeout(show, immediate ? 0 : 45);
            }

            function prepareSidebarTooltips() {
                sidebarTooltipItems().forEach(function (item) {
                    const label = tooltipLabel(item);
                    if (!label) {
                        return;
                    }

                    item.dataset.adminSidebarTooltip = label;
                    if (!item.hasAttribute('aria-label')) {
                        item.setAttribute('aria-label', label);
                    }

                    item.addEventListener('mouseenter', function () {
                        showSidebarTooltip(item);
                    });
                    item.addEventListener('mouseleave', function () {
                        hideSidebarTooltip();
                    });
                    item.addEventListener('focus', function () {
                        showSidebarTooltip(item, true);
                    });
                    item.addEventListener('blur', function () {
                        hideSidebarTooltip();
                    });
                });
            }

            function setSidebarSettingsMenuOpen(open) {
                if (!sidebarSettingsButton) {
                    return;
                }

                const settingsMenu = sidebarSettingsButton.nextElementSibling;
                sidebarSettingsButton.classList.toggle('show', open);
                sidebarSettingsButton.setAttribute('aria-expanded', open ? 'true' : 'false');

                if (settingsMenu && settingsMenu.classList.contains('admin-sidebar-settings-menu')) {
                    settingsMenu.classList.toggle('show', open);
                    if (!open) {
                        settingsMenu.classList.remove('is-collapsed-popover');
                        settingsMenu.style.removeProperty('left');
                        settingsMenu.style.removeProperty('top');
                    }
                }
            }

            function clearSettingsPopoverTimers() {
                if (settingsPopoverShowTimer) {
                    window.clearTimeout(settingsPopoverShowTimer);
                    settingsPopoverShowTimer = null;
                }
                if (settingsPopoverHideTimer) {
                    window.clearTimeout(settingsPopoverHideTimer);
                    settingsPopoverHideTimer = null;
                }
            }

            function collapsedDropdownParts(root) {
                if (!root) {
                    return { trigger: null, menu: null };
                }

                const children = Array.from(root.children);

                return {
                    trigger: children.find(function (child) {
                        return child.classList.contains('dropdown-toggle');
                    }) || null,
                    menu: children.find(function (child) {
                        return child.classList.contains('dropdown-menu');
                    }) || null,
                };
            }

            function clearCollapsedDropdownPopoverTimers() {
                if (collapsedDropdownPopoverShowTimer) {
                    window.clearTimeout(collapsedDropdownPopoverShowTimer);
                    collapsedDropdownPopoverShowTimer = null;
                }
                if (collapsedDropdownPopoverHideTimer) {
                    window.clearTimeout(collapsedDropdownPopoverHideTimer);
                    collapsedDropdownPopoverHideTimer = null;
                }
            }

            function setCollapsedDropdownPopoverOpen(root, open) {
                const parts = collapsedDropdownParts(root);
                if (!parts.trigger || !parts.menu) {
                    return;
                }

                if (open) {
                    if (!collapsedDropdownOriginalExpanded.has(parts.trigger)) {
                        collapsedDropdownOriginalExpanded.set(parts.trigger, parts.trigger.getAttribute('aria-expanded'));
                    }
                    parts.trigger.setAttribute('aria-expanded', 'true');
                    parts.trigger.classList.add('is-collapsed-popover-trigger');
                    parts.menu.classList.add('is-collapsed-popover');
                    return;
                }

                const originalExpanded = collapsedDropdownOriginalExpanded.get(parts.trigger);
                if (originalExpanded === null || typeof originalExpanded === 'undefined') {
                    parts.trigger.setAttribute('aria-expanded', 'false');
                } else {
                    parts.trigger.setAttribute('aria-expanded', originalExpanded);
                }
                collapsedDropdownOriginalExpanded.delete(parts.trigger);
                parts.trigger.classList.remove('is-collapsed-popover-trigger');
                parts.menu.classList.remove('is-collapsed-popover');
                parts.menu.style.removeProperty('--admin-collapsed-popover-arrow-top');
                parts.menu.style.removeProperty('left');
                parts.menu.style.removeProperty('top');
            }

            function hideCollapsedDropdownPopover(root = collapsedDropdownPopoverRoot, immediate = false) {
                if (!root || (collapsedDropdownPopoverRoot && root !== collapsedDropdownPopoverRoot)) {
                    return;
                }

                if (collapsedDropdownPopoverShowTimer) {
                    window.clearTimeout(collapsedDropdownPopoverShowTimer);
                    collapsedDropdownPopoverShowTimer = null;
                }

                const hide = function () {
                    setCollapsedDropdownPopoverOpen(root, false);
                    if (collapsedDropdownPopoverRoot === root) {
                        collapsedDropdownPopoverRoot = null;
                    }
                    collapsedDropdownPopoverHideTimer = null;
                };

                if (immediate) {
                    if (collapsedDropdownPopoverHideTimer) {
                        window.clearTimeout(collapsedDropdownPopoverHideTimer);
                    }
                    hide();
                    return;
                }

                collapsedDropdownPopoverHideTimer = window.setTimeout(hide, 80);
            }

            function showCollapsedDropdownPopover(root, immediate = false) {
                const parts = collapsedDropdownParts(root);
                if (
                    !parts.trigger
                    || !parts.menu
                    || !desktopQuery.matches
                    || !document.body.classList.contains('admin-sidebar-collapsed')
                ) {
                    return;
                }

                clearCollapsedDropdownPopoverTimers();
                hideCollapsedSettingsPopover(true);
                hideSidebarTooltip(true);

                if (collapsedDropdownPopoverRoot && collapsedDropdownPopoverRoot !== root) {
                    setCollapsedDropdownPopoverOpen(collapsedDropdownPopoverRoot, false);
                }
                collapsedDropdownPopoverRoot = root;

                collapsedDropdownPopoverShowTimer = window.setTimeout(function () {
                    if (
                        collapsedDropdownPopoverRoot !== root
                        || !document.body.classList.contains('admin-sidebar-collapsed')
                    ) {
                        return;
                    }

                    setCollapsedDropdownPopoverOpen(root, true);

                    const triggerRect = parts.trigger.getBoundingClientRect();
                    const menuHeight = parts.menu.offsetHeight;
                    const viewportPadding = 12;
                    const desiredTop = triggerRect.top + (triggerRect.height / 2) - (menuHeight / 2);
                    const maxTop = window.innerHeight - viewportPadding - menuHeight;
                    const top = Math.max(viewportPadding, Math.min(desiredTop, maxTop));
                    const arrowTop = Math.max(14, Math.min(
                        triggerRect.top + (triggerRect.height / 2) - top,
                        menuHeight - 14
                    ));

                    parts.menu.style.setProperty('left', Math.round(triggerRect.right + 7) + 'px', 'important');
                    parts.menu.style.setProperty('top', Math.round(top) + 'px', 'important');
                    parts.menu.style.setProperty('--admin-collapsed-popover-arrow-top', Math.round(arrowTop) + 'px');
                    collapsedDropdownPopoverShowTimer = null;
                }, immediate ? 0 : 45);
            }

            function showCollapsedSettingsPopover(immediate = false) {
                if (
                    !sidebarSettingsButton
                    || !desktopQuery.matches
                    || !document.body.classList.contains('admin-sidebar-collapsed')
                ) {
                    return;
                }

                clearSettingsPopoverTimers();
                hideCollapsedDropdownPopover(undefined, true);
                hideSidebarTooltip(true);
                settingsPopoverShowTimer = window.setTimeout(function () {
                    const settingsMenu = sidebarSettingsButton.nextElementSibling;
                    if (!settingsMenu || !document.body.classList.contains('admin-sidebar-collapsed')) {
                        return;
                    }

                    settingsMenu.classList.add('is-collapsed-popover');
                    setSidebarSettingsMenuOpen(true);

                    const triggerRect = sidebarSettingsButton.getBoundingClientRect();
                    const menuHeight = settingsMenu.offsetHeight;
                    const viewportPadding = 12;
                    const desiredTop = triggerRect.bottom - menuHeight;
                    const maxTop = window.innerHeight - viewportPadding - menuHeight;
                    const top = Math.max(viewportPadding, Math.min(desiredTop, maxTop));

                    settingsMenu.style.setProperty('left', Math.round(triggerRect.right + 7) + 'px', 'important');
                    settingsMenu.style.setProperty('top', Math.round(top) + 'px', 'important');
                    settingsPopoverShowTimer = null;
                }, immediate ? 0 : 45);
            }

            function hideCollapsedSettingsPopover(immediate = false) {
                if (!sidebarSettingsButton) {
                    return;
                }

                if (settingsPopoverShowTimer) {
                    window.clearTimeout(settingsPopoverShowTimer);
                    settingsPopoverShowTimer = null;
                }

                const settingsMenu = sidebarSettingsButton.nextElementSibling;
                const hide = function () {
                    if (settingsMenu && settingsMenu.classList.contains('is-collapsed-popover')) {
                        setSidebarSettingsMenuOpen(false);
                    }
                    settingsPopoverHideTimer = null;
                };

                if (immediate) {
                    if (settingsPopoverHideTimer) {
                        window.clearTimeout(settingsPopoverHideTimer);
                    }
                    hide();
                    return;
                }

                settingsPopoverHideTimer = window.setTimeout(hide, 80);
            }

            function syncSidebarBrand(collapsed) {
                if (!sidebarBrandLink) return;
                sidebarBrandLink.setAttribute('aria-label', collapsed ? (collapseButton.dataset.expandLabel || 'Expand') : @js(__('admin_extra.25654c797a97')));
                if (collapsed) {
                    sidebarBrandLink.setAttribute('role', 'button');
                    sidebarBrandLink.setAttribute('aria-expanded', 'false');
                    sidebarBrandLink.setAttribute('aria-controls', 'admin-navbar-menu');
                } else {
                    ['role', 'aria-expanded', 'aria-controls'].forEach(function (attribute) { sidebarBrandLink.removeAttribute(attribute); });
                }
            }

            function positionSidebarCollapseButton(collapsed) {
                if (!collapseButton || !collapseButtonHome) return;
                const destination = desktopQuery.matches && !collapsed && dashboardRow
                    ? dashboardRow : collapseButtonHome;
                if (collapseButton.parentElement === destination) return;
                const hadFocus = document.activeElement === collapseButton;
                destination.appendChild(collapseButton);
                if (hadFocus) collapseButton.focus({ preventScroll: true });
            }

            function setSidebarCollapsed(collapsed, persist = true) {
                if (!collapseButton) {
                    return;
                }

                hideCollapsedSettingsPopover(true);
                hideCollapsedDropdownPopover(undefined, true);
                const collapseButtonHadFocus = document.activeElement === collapseButton;
                document.body.classList.toggle('admin-sidebar-collapsed', collapsed);
                positionSidebarCollapseButton(collapsed);
                syncSidebarBrand(collapsed);
                if (collapsed && collapseButtonHadFocus && sidebarBrandLink) {
                    sidebarBrandLink.focus({ preventScroll: true });
                }
                hideSidebarTooltip(true);
                collapseButton.classList.toggle('opened', !collapsed);
                collapseButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                const collapseLabel = collapseButton.dataset.collapseLabel || 'Collapse';
                const expandLabel = collapseButton.dataset.expandLabel || 'Expand';
                collapseButton.setAttribute('aria-label', collapsed ? expandLabel : collapseLabel);
                collapseButton.dataset.adminSidebarTooltip = collapsed ? expandLabel : collapseLabel;
                if (collapsed) {
                    setSidebarSettingsMenuOpen(false);
                }

                if (collapseText) {
                    collapseText.textContent = collapsed ? expandLabel : collapseLabel;
                }

                if (persist) {
                    try {
                        window.localStorage.setItem(sidebarStorageKey, collapsed ? '1' : '0');
                    } catch (error) {
                    }
                }
            }

            function readStoredSidebarState() {
                try {
                    const storedState = window.localStorage.getItem(sidebarStorageKey);

                    return storedState === null ? true : storedState === '1';
                } catch (error) {
                    return true;
                }
            }

            function syncSidebarForViewport() {
                if (desktopQuery.matches) {
                    setSidebarCollapsed(readStoredSidebarState(), false);
                    return;
                }

                document.body.classList.remove('admin-sidebar-collapsed');
                positionSidebarCollapseButton(false);
                syncSidebarBrand(false);
                hideSidebarTooltip(true);
                if (collapseButton) {
                    collapseButton.classList.add('opened');
                    collapseButton.setAttribute('aria-expanded', 'true');
                    collapseButton.setAttribute('aria-label', collapseButton.dataset.collapseLabel || 'Collapse');
                }
                if (collapseText) {
                    collapseText.textContent = collapseButton && collapseButton.dataset.collapseLabel
                        ? collapseButton.dataset.collapseLabel
                        : 'Collapse';
                }
            }

            if (collapseButton) {
                collapseButton.addEventListener('click', function () {
                    if (!desktopQuery.matches) {
                        return;
                    }

                    setSidebarCollapsed(!document.body.classList.contains('admin-sidebar-collapsed'));
                });
            }

            if (sidebarRoot) {
                sidebarRoot.addEventListener('click', function (event) {
                    if (
                        !desktopQuery.matches
                        || !document.body.classList.contains('admin-sidebar-collapsed')
                        || !(event.target instanceof Element)
                    ) {
                        return;
                    }

                    if (event.target.closest('[data-admin-sidebar-brand]')) {
                        event.preventDefault();
                        setSidebarCollapsed(false);
                        collapseButton.focus({ preventScroll: true });
                        return;
                    }

                    const interactiveTarget = event.target.closest(
                        'a, button, input, select, textarea, label, summary, [role="button"], [contenteditable="true"]'
                    );

                    if (interactiveTarget) {
                        return;
                    }

                    setSidebarCollapsed(false);
                    collapseButton.focus({ preventScroll: true });
                });
            }

            if (sidebarBrandLink) {
                sidebarBrandLink.addEventListener('keydown', function (event) {
                    if (event.key === ' ' && desktopQuery.matches && document.body.classList.contains('admin-sidebar-collapsed')) {
                        event.preventDefault();
                        sidebarBrandLink.click();
                    }
                });
            }

            if (sidebarSettingsRoot) {
                sidebarSettingsRoot.addEventListener('mouseenter', function () {
                    showCollapsedSettingsPopover();
                });
                sidebarSettingsRoot.addEventListener('mouseleave', function () {
                    hideCollapsedSettingsPopover();
                });
                sidebarSettingsRoot.addEventListener('focusin', function () {
                    showCollapsedSettingsPopover(true);
                });
                sidebarSettingsRoot.addEventListener('focusout', function (event) {
                    if (!sidebarSettingsRoot.contains(event.relatedTarget)) {
                        hideCollapsedSettingsPopover();
                    }
                });
            }

            collapsedSidebarDropdownRoots.forEach(function (root) {
                root.addEventListener('mouseenter', function () {
                    showCollapsedDropdownPopover(root);
                });
                root.addEventListener('mouseleave', function () {
                    hideCollapsedDropdownPopover(root);
                });
                root.addEventListener('focusin', function () {
                    showCollapsedDropdownPopover(root, true);
                });
                root.addEventListener('focusout', function (event) {
                    if (!root.contains(event.relatedTarget)) {
                        hideCollapsedDropdownPopover(root);
                    }
                });
            });

            document.addEventListener('click', function (event) {
                const dropdownTrigger = event.target.closest('.navbar.navbar-vertical .dropdown-toggle');

                if (!dropdownTrigger || !desktopQuery.matches || !document.body.classList.contains('admin-sidebar-collapsed')) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                hideCollapsedDropdownPopover(undefined, true);
                setSidebarCollapsed(false);
                dropdownTrigger.focus({ preventScroll: true });

                if (dropdownTrigger === sidebarSettingsButton) {
                    window.setTimeout(function () {
                        setSidebarSettingsMenuOpen(true);
                    }, 190);
                }
            }, true);

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    hideSidebarTooltip(true);
                    hideCollapsedSettingsPopover(true);
                    hideCollapsedDropdownPopover(undefined, true);
                }
            });

            if (sidebarMenu) {
                sidebarMenu.addEventListener('scroll', function () {
                    const focusedItem = document.activeElement && document.activeElement.matches('[data-admin-sidebar-tooltip]')
                        ? document.activeElement
                        : null;
                    hideSidebarTooltip(true);
                    hideCollapsedDropdownPopover(undefined, true);

                    if (focusedItem && document.body.classList.contains('admin-sidebar-collapsed')) {
                        tooltipScrollTimer = window.setTimeout(function () {
                            tooltipScrollTimer = null;
                            showSidebarTooltip(focusedItem, true);
                        }, 45);
                    }
                }, true);
            }

            window.addEventListener('resize', function () {
                hideSidebarTooltip(true);
                hideCollapsedSettingsPopover(true);
                hideCollapsedDropdownPopover(undefined, true);
            });

            prepareSidebarTooltips();

            syncSidebarForViewport();

            if (typeof desktopQuery.addEventListener === 'function') {
                desktopQuery.addEventListener('change', syncSidebarForViewport);
            } else if (typeof desktopQuery.addListener === 'function') {
                desktopQuery.addListener(syncSidebarForViewport);
            }
        })();
</script>
@yield('script')
</body>
</html>
