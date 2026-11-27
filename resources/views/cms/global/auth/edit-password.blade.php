@extends('cms.global.auth.layout')

@section('accountContent')
    <header class="globalFinanceHeader"><h1>{{ trans('cms_profile.update_password') }}</h1></header>
    <form class="globalAuthForm" method="post" action="{{ route('cms.front.updateProfile') }}" data-global-account-form>
        @csrf
        <label>
            <span>{{ trans('cms_profile.current_password') }}</span>
            <input type="password" name="currentPassword" autocomplete="current-password" required>
        </label>
        <label>
            <span>{{ trans('cms_profile.new_password') }}</span>
            <input type="password" name="new_password" autocomplete="new-password" minlength="8" required>
        </label>
        <label>
            <span>{{ trans('cms_profile.password_confirm') }}</span>
            <input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required>
        </label>
        <p class="globalFormNotice" role="status" aria-live="polite" data-global-account-feedback></p>
        <button class="globalPrimaryAction" type="submit">{{ trans('cms_profile.update_password') }}</button>
    </form>
@endsection

@include('cms.global.auth.form-behavior')
