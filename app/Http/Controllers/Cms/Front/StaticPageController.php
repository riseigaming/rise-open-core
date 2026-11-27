<?php

namespace App\Http\Controllers\Cms\Front;

use App\Http\Controllers\Controller;
use App\Models\CmsContentPage;
use App\Services\Localization\LocalizedCopyResolver;
use App\Support\CmsThemeResolver;
use App\Support\WinxInformationPages;

class StaticPageController extends Controller
{
    public function learn(?string $slug = null)
    {
        $guides = \App\Support\GlobalGuides::all();
        abort_unless($slug === null || isset($guides[$slug]), 404);
        $learnSeoTitle = $slug === null ? __('journey.learn') : $guides[$slug]['title'];
        $learnSeoDescription = $slug === null ? __('journey.learn_intro') : $guides[$slug]['summary'];
        return response()->view('cms.global.learn', compact('guides', 'slug', 'learnSeoTitle', 'learnSeoDescription'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function getPage($pageName, LocalizedCopyResolver $localizedCopy){
        if (!isset(WinxInformationPages::PAGES[$pageName])) {
            $page = CmsContentPage::publishedAtSlug($pageName)->firstOrFail();
            return view('cms.global.content.custom-page', ['copy' => $page->localizedCopy(app()->getLocale())]);
        }
        $viewName = CmsThemeResolver::viewName('content.' . $pageName, defined('activeTheme') ? activeTheme : null);

        if (! view()->exists($viewName)) {
            abort(404);
        }

        $copyKey = [
            'terms-conditions' => 'terms',
            'privacy-policy' => 'privacy',
            'betting-rules' => 'betting_rules',
            'responsible-gaming' => 'responsible_gaming',
            'cookie' => 'cookie',
            'provably-fair' => 'provably_fair',
        ][$pageName] ?? null;

        $resolved = $copyKey !== null ? $localizedCopy->resolve('legal', $copyKey, app()->getLocale()) : null;
        abort_unless(is_array($resolved) && ($resolved['found'] ?? false) && is_array($resolved['copy'] ?? null), 503);
        return frontView("content.$pageName", ['localizedCopy' => $resolved]);
    }

    public function information(LocalizedCopyResolver $localizedCopy)
    {
        abort_unless(in_array(CmsThemeResolver::activePhysicalNamespace(), ['global'], true), 404);
        $informationSections = [];
        foreach (WinxInformationPages::PAGES as $id => $definition) {
            $resolved = $localizedCopy->resolve('legal', $definition['copy'], app()->getLocale());
            abort_unless($resolved['found'] && is_array($resolved['copy']), 503);
            $informationSections[] = [
                'id' => $id,
                'icon' => $definition['icon'],
                'copy' => $resolved['copy'],
            ];
        }

        return frontView('information', compact('informationSections'));
    }
}
