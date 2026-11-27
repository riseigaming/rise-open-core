@if ($errors->any())
    <ul class="alert alert-danger" role="alert">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif

<form method="post" data-content-page-form action="{{ $page->exists ? route('cms.admin.content-pages.update', $page) : route('cms.admin.content-pages.store') }}">
    @csrf
    @if ($page->exists)
        @method('PUT')
    @else
        <label class="form-label" for="page-slug">{{ __('content_pages.slug') }}</label>
        <input class="form-control mb-3" id="page-slug" name="slug" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" required value="{{ old('slug') }}">
    @endif
    <label class="form-label" for="page-published">{{ __('content_pages.status') }}</label>
    <select class="form-select mb-3" id="page-published" name="published">
        <option value="0" @selected(!(bool) old('published', $page->published))>{{ __('content_pages.draft') }}</option>
        <option value="1" @selected((bool) old('published', $page->published))>{{ __('content_pages.published') }}</option>
    </select>

    <p>{{ __('content_pages.help') }}</p>
    @foreach (\App\Models\CmsContentPage::LOCALES as $locale)
        <fieldset class="mb-3">
            <legend>{{ strtoupper($locale) }}</legend>
            <label class="form-label" for="page-title-{{ $locale }}">{{ __('admin.ui.title') }}</label>
            <input class="form-control mb-2" id="page-title-{{ $locale }}" name="translations[{{ $locale }}][title]" maxlength="255" value="{{ old('translations.'.$locale.'.title', data_get($page->translations, $locale.'.title')) }}">
            <label class="form-label" for="page-body-{{ $locale }}">{{ __('content_pages.body') }}</label>
            <textarea class="form-control" id="page-body-{{ $locale }}" name="translations[{{ $locale }}][body]" rows="5" maxlength="20000">{{ old('translations.'.$locale.'.body', data_get($page->translations, $locale.'.body')) }}</textarea>
        </fieldset>
    @endforeach
    <button class="btn btn-primary" type="submit">{{ __('content_pages.save') }}</button>
</form>
