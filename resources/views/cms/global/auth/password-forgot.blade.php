@extends('cms.global.index')

@section('pageContent')
    <section class="globalSurfacePage">
        <div class="globalSurfaceIntro">
            <span class="globalEyebrow">{{ trans('cms_password.security_note') }}</span>
            <h1>{{ trans('cms_password.forgot_title') }}</h1>
            <p>{{ trans('cms_password.forgot_body') }}</p>
        </div>
        <form class="globalAuthForm" method="POST" action="{{ route('cms.password.email') }}">
            @csrf
            @if(session('status'))
                <p class="globalFormNotice" role="status">{{ session('status') }}</p>
            @endif
            @if($errors->any())
                <p class="globalFormNotice" role="alert">{{ $errors->first() }}</p>
            @endif
            <label>
                <span>{{ trans('cms_password.email') }}</span>
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required data-desktop-autofocus>
            </label>
            <button class="globalPrimaryAction" type="submit">{{ trans('cms_password.send') }}</button>
            <a class="globalTextAction" href="{{ route('cms.login') }}">{{ trans('cms_password.back_to_login') }}</a>
        </form>
    </section>
    @include('cms.partials.desktop-form-autofocus')
@endsection
