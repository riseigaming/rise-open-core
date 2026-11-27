<?php

namespace App\Http\Controllers\Cms\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsLanguage;
use App\Models\CmsMenu;
use App\Services\Cms\CmsNavigationService;
use App\Support\B1ContentUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MenuController extends Controller
{
    public function edit(Request $request, CmsNavigationService $navigation){
        $languages = CmsLanguage::query()->where("status", 1)->get()->toArray();
        $slot = (string) $request->query('slot', 'sidebar');
        abort_unless(isset(CmsNavigationService::SCOPES[$slot]), 404);
        $menu = (object) ['json' => json_encode($navigation->editorItems(
            $slot, [], array_column($languages, 'code')
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        return view("cms.admin.menus.edit", [
            "menu" => $menu,
            "languages" => $languages,
            'slot' => $slot,
        ]);
    }

    public function update(Request $request){
        $slot = $request->input('slot', 'sidebar');
        if (!is_string($slot) || !isset(CmsNavigationService::SCOPES[$slot])) {
            return response()->json(['status' => 422, 'message' => __('navigation.invalid_menu')], 422);
        }
        if (!$request->filled('menu')) {
            return response()->json([
                'status' => 422,
                'message' => __('admin.dynamic.menu_data_required'),
            ], 422);
        }

        try {
            $normalizedMenu = $this->normalizeMenuPayload((string) $request->get('menu'));
            if ($slot !== null) {
                $ids = [];
                $saved = CmsMenu::query()->where('name', CmsNavigationService::SCOPES[$slot])->value('json');
                $decoded = json_decode((string) $saved, true);
                $existing = $this->savedMenuItems(is_array($decoded) ? $decoded : []);
                $normalizedMenu = $this->normalizeManagedItems($normalizedMenu, $slot, $ids, $existing);
            }
        } catch (InvalidArgumentException $exception) {
            Log::warning('cms.admin.menu.update.rejected', [
                'reason' => $exception->getMessage(),
                'actor_id' => optional(auth('cms')->user())->id,
            ]);

            return response()->json([
                'status' => 422,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $json = json_encode($normalizedMenu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $update = CmsMenu::updateOrCreate(['name' => CmsNavigationService::SCOPES[$slot]], ['json' => $json]);

        if($update){
            return response()->json([
                "status" => 200,
                "message" => __('admin.dynamic.menu_created')
            ]);
        }

        return response()->json([
            "status" => 500,
            "message" => __('admin.dynamic.menu_create_failed')
        ]);
    }

    private function normalizeMenuPayload(string $rawMenu): array
    {
        try {
            $decodedMenu = json_decode($rawMenu, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException(__('admin.dynamic.menu_json_unreadable'));
        }

        if (!is_array($decodedMenu) || !str_starts_with(ltrim($rawMenu), '[')) {
            throw new InvalidArgumentException(__('admin.dynamic.menu_json_array'));
        }

        if ($this->isAssoc($decodedMenu)) {
            throw new InvalidArgumentException(__('admin.dynamic.menu_json_list'));
        }

        $normalizedMenu = [];

        foreach (array_values($decodedMenu) as $index => $item) {
            $normalizedMenu[] = $this->normalizeMenuItem($item, 'menu[' . $index . ']');
        }

        return $normalizedMenu;
    }

    private function normalizeMenuItem($item, string $path): array
    {
        if (!is_array($item)) {
            throw new InvalidArgumentException(__('admin.dynamic.menu_object', ['path' => $path]));
        }

        $allowedDirectKeys = ['text', 'href', 'target', 'title', 'badge', 'icon', 'children', 'id', 'visibility', 'variant'];
        $allowedLocalizedPrefixes = ['text_', 'href_', 'target_', 'title_', 'badge_', 'icon_'];

        foreach ($item as $key => $value) {
            if (!is_string($key)) {
                throw new InvalidArgumentException(__('admin.dynamic.menu_string_keys', ['path' => $path]));
            }

            $isAllowedLocalizedKey = false;
            foreach ($allowedLocalizedPrefixes as $prefix) {
                if (str_starts_with($key, $prefix) && strlen($key) > strlen($prefix)) {
                    $isAllowedLocalizedKey = true;
                    break;
                }
            }

            if (!in_array($key, $allowedDirectKeys, true) && !$isAllowedLocalizedKey) {
                throw new InvalidArgumentException($path . '.' . $key . ' desteklenmeyen bir alan.');
            }

            if ($key === 'children') {
                if (!is_array($value)) {
                    throw new InvalidArgumentException(__('admin.dynamic.menu_children_list', ['path' => $path]));
                }

                if ($this->isAssoc($value)) {
                    throw new InvalidArgumentException(__('admin.dynamic.menu_children_ordered', ['path' => $path]));
                }

                continue;
            }

            if (!is_null($value) && !is_string($value)) {
                throw new InvalidArgumentException(__('admin.dynamic.menu_value_string', ['path' => $path.'.'.$key]));
            }

            if (($key === 'target' || str_starts_with($key, 'target_')) && filled($value) && !in_array($value, ['_self', '_blank'], true)) {
                throw new InvalidArgumentException(__('admin.dynamic.menu_target_invalid', ['path' => $path.'.'.$key]));
            }
        }

        $normalized = [];
        $hasText = false;
        $hasHref = false;

        foreach ($item as $key => $value) {
            if ($key === 'children') {
                continue;
            }

            $normalizedValue = is_string($value) ? trim($value) : $value;
            $normalized[$key] = $normalizedValue;

            if (($key === 'text' || str_starts_with($key, 'text_')) && filled($normalizedValue)) {
                $hasText = true;
            }

            if (($key === 'href' || str_starts_with($key, 'href_')) && filled($normalizedValue)) {
                $hasHref = true;
            }
        }

        if (!$hasText) {
            throw new InvalidArgumentException(__('admin.dynamic.menu_text_required', ['path' => $path]));
        }

        if (!$hasHref) {
            throw new InvalidArgumentException(__('admin.dynamic.menu_href_required', ['path' => $path]));
        }

        $normalized['children'] = [];

        foreach (array_values($item['children'] ?? []) as $index => $childItem) {
            $normalized['children'][] = $this->normalizeMenuItem($childItem, $path . '.children[' . $index . ']');
        }

        return $normalized;
    }

    private function isAssoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function savedMenuItems(array $items): array
    {
        $byId = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (is_string($item['id'] ?? null)) {
                $byId[$item['id']] = $item;
            }
            if (is_array($item['children'] ?? null)) {
                $byId += $this->savedMenuItems($item['children']);
            }
        }

        return $byId;
    }

    private function normalizeManagedItems(array $items, string $slot, array &$ids, array $existing, int $depth = 0): array
    {
        if ($depth > 1 && $items !== []) {
            throw new InvalidArgumentException(__('navigation.invalid_depth'));
        }

        foreach ($items as &$item) {
            if (!filled($item['text_tr'] ?? $item['text'] ?? null) || !filled($item['href_tr'] ?? $item['href'] ?? null)) {
                throw new InvalidArgumentException(__('navigation.default_language_required'));
            }
            $id = $item['id'] ?? '';
            $id = $id !== '' ? $id : (string) \Illuminate\Support\Str::uuid();
            if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $id) || isset($ids[$id]) || count($ids) >= 200) {
                throw new InvalidArgumentException(__('navigation.invalid_menu'));
            }
            $ids[$id] = true;
            $item['id'] = $id;
            $item['visibility'] = ($item['visibility'] ?? '') ?: 'all';
            $item['variant'] = $item['variant'] ?? '';
            if (!in_array($item['visibility'], ['all', 'authenticated'], true)
                || !in_array($item['variant'], [''], true)
                || ($item['variant'] !== '' && ($slot !== 'hero' || $depth !== 0))) {
                throw new InvalidArgumentException(__('navigation.invalid_menu'));
            }
            foreach ($item as $field => $value) {
                if (($field === 'href' || str_starts_with($field, 'href_')) && filled($value)) {
                    // Preserve an existing link while the page is hidden; do not add a new draft link.
                    $retainedHiddenPage = ($existing[$id][$field] ?? null) === $value
                        && B1ContentUrl::isHiddenPageLink($value);
                    if (!$retainedHiddenPage && !B1ContentUrl::allowed($value)) {
                        throw new InvalidArgumentException(__('navigation.invalid_url'));
                    }
                }
            }
            $item['children'] = $this->normalizeManagedItems($item['children'], $slot, $ids, $existing, $depth + 1);
        }
        unset($item);

        return $items;
    }
}
