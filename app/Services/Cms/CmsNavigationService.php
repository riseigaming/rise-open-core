<?php

namespace App\Services\Cms;

use App\Models\CmsMenu;
use App\Support\B1ContentUrl;
use Illuminate\Support\Facades\Schema;

class CmsNavigationService
{
    public const SCOPES = ['sidebar' => 'global_sidebar', 'hero' => 'global_hero'];


    public function stored(string $scope): ?array
    {
        if (!isset(self::SCOPES[$scope]) || !Schema::hasTable('cms_menus')) {
            return null;
        }

        $json = CmsMenu::query()->where('name', self::SCOPES[$scope])->value('json');
        $items = is_string($json) ? json_decode($json, true) : null;

        return is_array($items) && ($items === [] || array_keys($items) === range(0, count($items) - 1)) ? $items : null;
    }

    public function editorItems(string $scope, array $legacy, array $locales): array
    {
        $saved = $this->stored($scope);
        if ($saved !== null) {
            return $saved;
        }

        $originalLocale = app()->getLocale();
        $items = [];
        try {
            foreach (array_unique(array_merge(['tr', 'en', 'es'], $locales)) as $locale) {
                app()->setLocale($locale);
                $approved = $this->defaults($locale);
                $items = $this->mergeLocale($items, $approved, $locale, $scope);
            }
        } finally {
            app()->setLocale($originalLocale);
        }

        return $items;
    }

    private function mergeLocale(array $items, array $approved, string $locale, string $prefix): array
    {
        foreach ($approved as $index => $item) {
            $id = $prefix . '-' . $index;
            $items[$index]['id'] = $id;
            foreach (['text', 'href', 'target', 'title', 'badge', 'icon'] as $field) {
                $items[$index][$field . '_' . $locale] = (string) ($field === 'title'
                    ? ($item['description'] ?? $item['title'] ?? '')
                    : ($item[$field] ?? ($field === 'target' ? '_self' : '')));
            }
            $items[$index]['visibility'] = $item['visibility'] ?? 'all';
            $items[$index]['variant'] = '';
            $items[$index]['children'] = $this->mergeLocale(
                $items[$index]['children'] ?? [], $item['submenu'] ?? $item['children'] ?? [], $locale, $id
            );
        }

        return $items;
    }

    public function sidebar(array $legacy, string $locale): array
    {
        $saved = $this->stored('sidebar');
        return $saved === null ? $this->visible($this->defaults($locale)) : $this->localize($saved, $locale);
    }

    public function hero(string $locale): array
    {
        $saved = $this->stored('hero');
        return $saved === null ? $this->visible($this->defaults($locale)) : $this->localize($saved, $locale);
    }

    // Saved labels and destinations are data: never infer destinations from translated labels.
    public function localize(array $items, string $locale): array
    {
        return $this->visible(array_map(function (array $item) use ($locale): array {
            foreach (['text', 'href', 'target', 'title', 'badge', 'icon'] as $field) {
                $item[$field] = ($item[$field . '_' . $locale] ?? '') ?: ($item[$field . '_tr'] ?? $item[$field] ?? '');
            }
            $item['target'] = $item['target'] ?: '_self';
            $item['children'] = $this->localize($item['children'] ?? [], $locale);
            return $item;
        }, $items));
    }

    private function visible(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (($item['visibility'] ?? 'all') === 'authenticated' && !auth('cms')->check()) {
                continue;
            }
            if (B1ContentUrl::isHiddenPageLink((string) ($item['href'] ?? ''))) {
                continue;
            }
            $item['children'] = $this->visible($item['children'] ?? []);
            $result[] = $item;
        }

        return $result;
    }
    public function isCurrent(string $href): bool
    {
        $parts = parse_url($href);
        if ($parts === false || isset($parts['host']) && $parts['host'] !== request()->getHost()) return false;
        if (($parts['path'] ?? '/') !== '/'.ltrim(request()->path(), '/')) return false;
        parse_str($parts['query'] ?? '', $query);
        $current = request()->only(['q', 'type', 'provider', 'page']);
        $current = array_filter($current, fn($v) => $v !== '' && $v !== null);
        return $query == $current;
    }

    public function informationItems(string $locale): array
    {
        $items = [];
        foreach (\App\Support\WinxInformationPages::PAGES as $page => $definition) {
            $items[] = $this->item(trans('journey.pages.'.$page, [], $locale), route('cms.staticPage', $page), 'info');
        }
        return $items;
    }

    private function item(string $label, string $href, string $icon, array $children = [], string $visibility = 'all'): array
    {
        return ['text'=>$label,'href'=>$href,'target'=>'_self','title'=>'','badge'=>'','icon'=>$icon,'children'=>$children,'visibility'=>$visibility];
    }

    private function defaults(string $locale): array
    {
        $categories = [];
        foreach (array_keys(\App\Models\CmsGame::TYPES) as $type) {
            $categories[] = $this->item(trans('journey.types.'.$type, [], $locale), route('cms.games.index', ['type'=>$type]), 'casino');
        }
        $categories[] = $this->item(trans('journey.providers', [], $locale), route('cms.games.index').'#catalog-providers', 'casino');
        $guides = [];
        foreach (\App\Support\GlobalGuides::SLUGS as $slug) {
            $guides[] = $this->item(trans('journey.guides.'.$slug.'.title', [], $locale), route('cms.front.learnGuide', $slug), 'learn');
        }
        $items = [
            $this->item(trans('cms_public.shell.home', [], $locale), route('cms.front.home'), 'home'),
            $this->item('Sportsbook', route('cms.front.sportsbook'), 'sport'),
            $this->item(trans('journey.catalog', [], $locale), route('cms.games.index'), 'casino', $categories),
            $this->item(trans('cms_public.promotions.title', [], $locale), route('cms.promotions'), 'promotions'),
            $this->item(trans('journey.learn', [], $locale), route('cms.front.learnCenter'), 'learn', $guides),
            $this->item(trans('journey.information', [], $locale), route('cms.information'), 'info', $this->informationItems($locale)),
            $this->item(trans('journey.account', [], $locale), route('cms.front.sessions'), 'account', [
                $this->item(trans('global.sessions', [], $locale), route('cms.front.sessions'), 'account'),
                $this->item(trans('global.b1.change_password', [], $locale), route('cms.front.editPassword'), 'account'),
            ], 'authenticated'),
        ];
        if(config('chess_integration.front_enabled'))array_splice($items,2,0,[$this->item('ChessBet',route('cms.games.detail','chess-bet'),'sport')]);
        return $items;
    }
}
