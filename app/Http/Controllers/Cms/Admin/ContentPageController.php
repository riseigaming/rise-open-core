<?php

namespace App\Http\Controllers\Cms\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsContentPage;
use App\Support\CmsContentHtml;
use App\Support\WinxInformationPages;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class ContentPageController extends Controller
{
    public function index(): View
    {
        return view('cms.admin.content-pages.index', ['pages' => CmsContentPage::query()->orderBy('slug')->paginate(25)]);
    }

    public function edit(CmsContentPage $page): View
    {
        return view('cms.admin.content-pages.edit', ['page' => $page]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new CmsContentPage());
    }

    public function update(Request $request, CmsContentPage $page): RedirectResponse
    {
        return $this->save($request, $page);
    }

    private function save(Request $request, CmsContentPage $page): RedirectResponse
    {
        $rules = ['published' => ['required', 'boolean'], 'translations' => ['required', 'array:tr,en,es']];
        if (!$page->exists) {
            $rules['slug'] = ['required', 'string', 'max:80', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/',
                Rule::notIn(array_keys(WinxInformationPages::PAGES)), Rule::unique('cms_content_pages', 'slug')];
        }
        foreach (CmsContentPage::LOCALES as $locale) {
            $rules["translations.$locale"] = ['required', 'array:title,body'];
            $rules["translations.$locale.title"] = ['nullable', 'string', 'max:255'];
            $rules["translations.$locale.body"] = ['nullable', 'string', 'max:20000'];
        }

        $input = $request->validate($rules);
        foreach ($input['translations'] as $locale => &$translation) {
            $translation['title'] = trim((string) ($translation['title'] ?? ''));
            $translation['body'] = CmsContentHtml::render($translation['body'] ?? '');
            $visibleText = html_entity_decode(strip_tags($translation['body']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($input['published'] && ($translation['title'] === '' || !preg_match('/[^\s\p{Z}]/u', $visibleText))) {
                throw ValidationException::withMessages(["translations.$locale.body" => __('content_pages.complete_translation')]);
            }
        }
        unset($translation);

        try {
            $page->fill($input)->save();
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['slug' => __('validation.unique', ['attribute' => 'slug'])]);
        }

        return redirect()->route('cms.admin.content-pages.edit', $page)->with('page_saved', true);
    }
}
