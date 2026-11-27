@extends('cms.global.index')

@section('pageContent')
    @include('cms.global.content.legal-page', ['legalPage' => data_get($localizedCopy ?? [], 'copy', trans('cms_public.legal_pages.terms_conditions'))])
@endsection
