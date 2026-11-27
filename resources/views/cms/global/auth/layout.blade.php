@extends('cms.global.index')

@section('pageContent')
    <section class="globalSurfacePage" data-global-session-layout>
        @include('cms.global.partials.journey.breadcrumb',['crumbs'=>[__('journey.account')=>null]])
        @auth('cms')
            <nav class="journeyFilterLinks" aria-label="{{ __('journey.account') }}"><a href="{{ route('cms.front.sessions') }}">{{ __('global.sessions') }}</a><a href="{{ route('cms.front.editPassword') }}">{{ __('global.b1.change_password') }}</a><a href="{{ route('cms.front.learnGuide','account-security') }}">{{ __('journey.guides.account-security.title') }}</a></nav>
        @endauth
        @auth('cms')
            @yield('accountContent')
        @else
            <div class="globalSurfaceIntro">
                <h1>{{ trans('global.log_in_to_continue') }}</h1>
                <a class="globalPrimaryAction" href="{{ route('cms.front.login') }}" data-global-auth-open="login">{{ trans('global.log_in') }}</a>
            </div>
        @endauth
    </section>
@endsection
