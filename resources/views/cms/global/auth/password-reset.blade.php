@extends('cms.global.index')

@section('pageContent')
    <section class="globalSurfacePage">
        <div class="globalSurfaceIntro">
            <span class="globalEyebrow">{{ trans('cms_password.security_note') }}</span>
            <h1>{{ trans('cms_password.reset_title') }}</h1>
            <p>{{ trans('cms_password.reset_body') }}</p>
        </div>
        <form class="globalAuthForm" method="POST" action="{{ route('cms.password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            @if($errors->any())
                <p class="globalFormNotice" role="alert">{{ $errors->first() }}</p>
            @endif
            <label>
                <span>{{ trans('cms_password.email') }}</span>
                <input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required>
            </label>
            <label>
                <span>{{ trans('cms_password.password') }}</span>
                <input type="password" name="password" autocomplete="new-password" minlength="8" required data-desktop-autofocus>
            </label>
            <label>
                <span>{{ trans('cms_password.password_confirmation') }}</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
            </label>
            <button class="globalPrimaryAction" type="submit">{{ trans('cms_password.reset') }}</button>
        </form>
    </section>
    @include('cms.partials.desktop-form-autofocus')
@endsection
