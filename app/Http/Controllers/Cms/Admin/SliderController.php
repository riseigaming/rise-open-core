<?php

namespace App\Http\Controllers\Cms\Admin;

use App\Helper\UploadHelper;
use App\Http\Controllers\Controller;
use App\Models\CmsLanguage;
use App\Models\CmsSiteSetting;
use App\Models\CmsSlider;
use App\Models\CmsSliderTranslation;
use App\Support\CmsThemeResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class SliderController extends Controller
{
    private const SLIDER_STORAGE_IMAGE_PATH = "sliders";
    private const SLIDER_PUBLIC_IMAGE_PATH = "cms-assets/sliders";
    private const RESPONSIVE_IMAGE_EXTENSIONS = ["jpg", "jpeg", "png", "webp", "avif"];

    public function index(Request $request){
        if ($request->get("tab") === "archive") {
            return redirect()->route("cms.admin.sliders.archive");
        }

        $placement = $this->resolvePlacement($request->get("placement", CmsSlider::PLACEMENT_HOME));
        $themeKey = $this->managedThemeKey();
        $firstSliderId = $this->scopeSliderQuery(CmsSlider::query(), $themeKey)
            ->where("placement", $placement)
            ->orderBy("rank", "ASC")
            ->orderBy("id", "DESC")
            ->value("id");

        if ($firstSliderId) {
            return redirect()->route("cms.admin.sliders.edit", ["id" => $firstSliderId]);
        }

        return redirect()->route("cms.admin.sliders.create", [
            "placement" => $placement,
            "theme_key" => $themeKey,
        ]);
    }

    public function getAjaxList(Request $request) {
        $placement = $this->resolvePlacement($request->get("placement", CmsSlider::PLACEMENT_HOME));
        $themeKey = $this->managedThemeKey();
        $allowedSortFields = ["id", "rank", "status"];
        $sortField = in_array($request->get("sort"), $allowedSortFields, true)
            ? $request->get("sort")
            : "rank";
        $sortOrder = Str::lower((string) $request->get("order", "ASC")) === "desc" ? "DESC" : "ASC";

        $baseQuery = $this->scopeSliderQuery(CmsSlider::with("translations"), $themeKey)
            ->where("placement", $placement)
            ->when($request->search, function ($q) use($request){
            $search = implode("%", explode(" ", $request->search));
            return $q->whereHas("translations", function ($qe) use($search){
                return $qe->where("title", "LIKE", "%$search%")
                    ->orWhere("description", "LIKE", "$search");
            });
            // return $q->where("name", "like", "%$search%")

        });

        $count = (clone $baseQuery)->count();

        $sliders = $baseQuery
            ->orderBy($sortField, $sortOrder)
            ->orderBy("id", "DESC")
            ->offset($request->get("offset", 0))
            ->limit($request->get("limit", 10))
            ->get();

        foreach ($sliders as $slider){
            $primaryTranslation = $slider->translations[0] ?? null;
            $resolvedImageName = $this->resolveSharedImageName($slider);
            $resolvedImagePath = $this->resolveSharedImagePath($slider) ?: "sliders";

            $slider->title = optional($primaryTranslation)->title;
            $slider->description = optional($primaryTranslation)->description;
            $slider->image = $resolvedImageName;
            $slider->url = optional($primaryTranslation)->url;
            $slider->rank = (int) ($slider->rank ?? 0);
            $slider->theme_scope = $this->themeScopeLabel($slider->theme_key ?? null);
            $slider->image_url = filled($resolvedImageName)
                ? ($this->resolveSliderImageUrl($resolvedImagePath, $resolvedImageName) ?: asset('cms-assets/blank-placeholder.svg'))
                : asset('cms-assets/blank-placeholder.svg');
        }

        $data = [
            "rows" => $sliders,
            "count" => $count
        ];

        return response()->json($data);
    }

    public function create(Request $request){
        $placement = $this->resolvePlacement($request->get("placement", CmsSlider::PLACEMENT_HOME));
        $themeKey = $this->managedThemeKey();
        $languages = CmsLanguage::query()->where("status", 1)->get()->toArray();
        $data = [
            "languages" => $languages,
            "selectedPlacement" => $placement,
            "selectedThemeScope" => $themeKey,
            "themeScopeEnabled" => $this->themeScopeEnabled(),
            "activeThemeLabel" => $this->themeScopeLabel($themeKey),
            "placementLabels" => $this->placementLabels(),
            "sliderOverview" => $this->getSliderOverview($placement, $themeKey),
        ];

        return view("cms.admin.sliders.create", $data);
    }

    public function edit($id){
        $languages = CmsLanguage::query()->where("status", 1)->get()->toArray();
        $themeKey = $this->managedThemeKey();
        $slider = $this->scopeSliderQuery(CmsSlider::with("translations"), $themeKey)->findOrFail($id);
        $transData = [];

        foreach ($slider->translations->toArray() as $translation){
            $transData[$translation["cms_language_id"]] = $translation;
        }

        $data = [
            "languages" => $languages,
            "slider" => $slider,
            "transData" => $transData,
            "selectedPlacement" => $slider->placement,
            "selectedThemeScope" => $slider->theme_key ?? null,
            "themeScopeEnabled" => $this->themeScopeEnabled(),
            "activeThemeLabel" => $this->themeScopeLabel($themeKey),
            "placementLabels" => $this->placementLabels(),
            "sharedSliderImageName" => $this->resolveSharedImageName($slider),
            "responsiveImageSet" => $this->responsiveImageSetForSlider($slider),
            "sliderOverview" => $this->getSliderOverview($slider->placement, $slider->theme_key ?? null),
        ];

        return view("cms.admin.sliders.edit", $data);
    }

    public function archive(Request $request)
    {
        $themeKey = $this->managedThemeKey();
        $archivedSliders = $this->scopeSliderQuery(CmsSlider::onlyTrashed(), $themeKey)
            ->with("translations")
            ->orderBy("deleted_at", "DESC")
            ->orderBy("id", "DESC")
            ->get()
            ->map(function (CmsSlider $slider) {
                $primaryTranslation = $slider->translations[0] ?? null;
                $resolvedImageName = $this->resolveSharedImageName($slider);
                $resolvedImagePath = $this->resolveSharedImagePath($slider) ?: "sliders";

                return [
                    "id" => $slider->id,
                    "placement" => $slider->placement,
                    "placement_label" => $this->placementLabels()[$slider->placement] ?? $slider->placement,
                    "title" => optional($primaryTranslation)->title ?: __('admin.dynamic.untitled_slider'),
                    "deleted_at" => optional($slider->deleted_at)->format("d.m.Y H:i"),
                    "has_image" => filled($resolvedImageName),
                    "image_url" => filled($resolvedImageName)
                        ? ($this->resolveSliderImageUrl($resolvedImagePath, $resolvedImageName) ?: asset('cms-assets/blank-placeholder.svg'))
                        : asset('cms-assets/blank-placeholder.svg'),
                    "theme_scope" => $this->themeScopeLabel($slider->theme_key ?? null),
                    "restore_home_url" => route("cms.admin.sliders.restore", [
                        "id" => $slider->id,
                        "placement" => CmsSlider::PLACEMENT_HOME,
                    ]),
                ];
            })
            ->values();

        return view("cms.admin.sliders.archive", [
            "archivedSliders" => $archivedSliders,
            "selectedPlacement" => "archive",
            "placementLabels" => $this->placementLabels(),
        ]);
    }

    public function store(Request $request){

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $placement = $this->resolvePlacement($request->get("placement", CmsSlider::PLACEMENT_HOME));
            $themeKey = $this->managedThemeKey();
            $sliderData = $this->sliderDataFromRequest($request);
            $sliderData["placement"] = $placement;
            if ($this->themeScopeEnabled()) {
                $sliderData["theme_key"] = $themeKey;
            }
            $sliderData["use_same_image"] = 1;
            $store = CmsSlider::query()->create($sliderData);

            $imagePath = "sliders";
            if($store){
                $langContent = $request->get("langContent", []);
                foreach ($langContent as $lc){
                    $transData = $lc;
                    $transData["cms_slider_id"] = $store->id;
                    CmsSliderTranslation::query()->create($transData);
                }

                $sharedImageName = $this->uploadSharedImage($request, $imagePath, $store, $langContent);
                if (filled($sharedImageName)) {
                    $this->uploadResponsiveVariants($request, $imagePath, $sharedImageName);

                    $store->update([
                        "image_path" => $imagePath,
                        "image_name" => $sharedImageName,
                        "use_same_image" => 1,
                    ]);
                }

                $this->reorderSliderRanks($store, (int) $request->get("rank", 1));
                $this->forgetSliderCache($placement);

                return response()->json([
                    "status" => 200,
                    "message" => __('admin.copy.record_added'),
                    "redirect_url" => route("cms.admin.sliders.edit", ["id" => $store->id]),
                ]);
            }
            });
        }catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        }catch (\InvalidArgumentException $exception) {
            return response()->json([
                "status" => 422,
                "message" => $exception->getMessage(),
            ], 422);
        }catch (\Throwable $th){
            \Illuminate\Support\Facades\Log::error('CMS slider upload/store failed.', ['exception' => $th]);

            return response()->json([
                "status" => 500,
                "message" => __('admin.copy.unexpected_error')
            ], 500);
        }
    }

    public function update(Request $request){

        try {
            return \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $sliderId = $request->get("sliderId");
            $themeKey = $this->managedThemeKey();
            $currentSlider = $this->scopeSliderQuery(CmsSlider::with("translations"), $themeKey)->findOrFail($sliderId);
            $oldPlacement = $currentSlider->placement;
            $oldThemeKey = $currentSlider->theme_key ?? null;
            $newPlacement = $this->resolvePlacement($request->get("placement", $oldPlacement));
            $newThemeKey = $themeKey;

            $sliderData = $this->sliderDataFromRequest($request);
            $sliderData["placement"] = $newPlacement;
            if ($this->themeScopeEnabled()) {
                $sliderData["theme_key"] = $newThemeKey;
            }
            $sliderData["use_same_image"] = 1;
            $update = $currentSlider->update($sliderData);

            $imagePath = "sliders";
            if($update){
                $langContent = $request->get("langContent", []);
                foreach ($langContent as $lc){
                    $transData = $lc;
                    $transData["cms_slider_id"] = $sliderId;
                    CmsSliderTranslation::query()->updateOrCreate([
                            "cms_slider_id" => $sliderId,
                            "cms_language_id" => $lc["cms_language_id"]
                        ],
                        $transData
                    );
                }

                $sharedImageName = $this->uploadSharedImage($request, $imagePath, $currentSlider, $langContent);
                $shouldRemoveSharedImage = $request->boolean("remove_shared_image");
                if (filled($sharedImageName)) {
                    $this->uploadResponsiveVariants($request, $imagePath, $sharedImageName);

                    $currentSlider->update([
                        "image_path" => $imagePath,
                        "image_name" => $sharedImageName,
                        "use_same_image" => 1,
                    ]);
                } elseif ($shouldRemoveSharedImage) {
                    $currentSlider->translations()->update([
                        "image_path" => null,
                        "image_name" => null,
                    ]);
                    $currentSlider->update([
                        "image_path" => null,
                        "image_name" => null,
                        "use_same_image" => 1,
                    ]);
                } elseif (!filled($currentSlider->image_name)) {
                    $legacyImageName = $this->resolveSharedImageName($currentSlider->fresh("translations"));
                    if (filled($legacyImageName)) {
                        $this->uploadResponsiveVariants($request, $imagePath, $legacyImageName);

                        $currentSlider->update([
                            "image_path" => $imagePath,
                            "image_name" => $legacyImageName,
                            "use_same_image" => 1,
                        ]);
                    }
                } else {
                    $this->uploadResponsiveVariants(
                        $request,
                        $currentSlider->image_path ?: $imagePath,
                        $currentSlider->image_name,
                    );
                }

                $this->reorderSliderRanks($currentSlider->fresh(), (int) $request->get("rank", $currentSlider->rank));
                if ($oldPlacement !== $newPlacement || $oldThemeKey !== $newThemeKey) {
                    $this->normalizeSliderRanks($oldPlacement, $oldThemeKey);
                }
                $this->forgetSliderCaches([$oldPlacement, $newPlacement]);

                return response()->json([
                    "status" => 200,
                    "message" => __('admin.copy.record_updated')
                ]);
            }
            });
        }catch (ModelNotFoundException $exception) {
            return response()->json([
                "status" => 404,
                "message" => __('admin.dynamic.slider_outside_theme'),
            ], 404);
        }catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        }catch (\InvalidArgumentException $exception) {
            return response()->json([
                "status" => 422,
                "message" => $exception->getMessage(),
            ], 422);
        }catch (\Throwable $th){
            \Illuminate\Support\Facades\Log::error('CMS slider upload/update failed.', ['exception' => $th]);

            return response()->json([
                "status" => 500,
                "message" => __('admin.copy.unexpected_error')
            ], 500);
        }
    }

    public function bulkAction(Request $request){
        $ids = collect(explode(",", $request->get("ids","")))
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->values()
            ->all();

        if(count($ids) <= 0 ){
            return response()->json([
                "status" => 400,
                "message" => __('admin.copy.selection_required')
            ]);
        }
        $themeKey = $this->managedThemeKey();
        $scopedSliders = $this->scopeSliderQuery(CmsSlider::query(), $themeKey)
            ->whereIn("id", $ids)
            ->get(["id", "placement", "theme_key"]);
        if ($scopedSliders->count() !== count($ids)) {
            return response()->json([
                "status" => 404,
                "message" => __('admin.dynamic.slider_outside_theme'),
            ], 404);
        }

        $affectedScopes = $scopedSliders
            ->map(fn (CmsSlider $slider) => [
                "placement" => $slider->placement,
                "theme_key" => $slider->theme_key ?? null,
            ])
            ->unique(fn (array $scope) => ($scope["placement"] ?? "") . "|" . ($scope["theme_key"] ?? ""))
            ->values();
        $affectedPlacements = $affectedScopes
            ->pluck("placement")
            ->unique()
            ->values()
            ->all();
        $languages = $this->scopeSliderQuery(CmsSlider::query(), $themeKey)->whereIn("id", $ids);
        $status = false;
        if($request->get("action") == "active"){
            $status = $languages->update(["status" => 1]);
        }elseif($request->get("action") == "passive"){
            $status = $languages->update(["status" => 0]);
        }elseif($request->get("action") == "delete"){
            $status = $languages->delete();
            if ($status) {
                foreach ($affectedScopes as $scope) {
                    $this->normalizeSliderRanks($scope["placement"], $scope["theme_key"]);
                }
            }
        }

        if(!$status){
            return response()->json([
                "status" => 500,
                "message" => __('admin.copy.generic_error')
            ]);
        }

        $this->forgetSliderCaches($affectedPlacements);

        return response()->json([
            "status" => 201,
            "message" => __('admin.copy.action_success')
        ]);
    }

    public function delete($id){
        $language = $this->scopeSliderQuery(CmsSlider::query(), $this->managedThemeKey())->findOrFail($id);
        $placement = $language->placement;
        $themeKey = $language->theme_key ?? null;

        if($language->delete()){
            $this->normalizeSliderRanks($placement, $themeKey);
            $this->forgetSliderCache($placement);
            return response()->json([
                "status" => 200,
                "message" => __('admin.copy.record_deleted')
            ]);
        }

        return response()->json([
            "status" => 500,
            "message" => __('admin.copy.generic_error')
        ]);
    }

    public function restore(Request $request, $id)
    {
        try {
            $placement = $this->resolvePlacement($request->get("placement"));
            $themeKey = $this->managedThemeKey();
            $slider = $this->scopeSliderQuery(CmsSlider::onlyTrashed(), $themeKey)->findOrFail($id);
            $oldPlacement = $slider->placement;
            $oldThemeKey = $slider->theme_key ?? null;

            $slider->placement = $placement;
            if ($this->themeScopeEnabled()) {
                $slider->theme_key = $themeKey;
            }
            $slider->status = 0;
            $slider->rank = $this->nextRankForPlacement($placement, $themeKey);
            $slider->save();
            $slider->restore();

            $this->normalizeSliderRanks($placement, $themeKey);
            if ($oldPlacement !== $placement || $oldThemeKey !== $themeKey) {
                $this->normalizeSliderRanks($oldPlacement, $oldThemeKey);
            }
            $this->forgetSliderCaches([$oldPlacement, $placement]);

            return redirect()
                ->route("cms.admin.sliders.edit", ["id" => $slider->id])
                ->with("success", __('admin.dynamic.slider_restored'));
        } catch (\Throwable $th) {
            return redirect()
                ->route("cms.admin.sliders.archive")
                ->with("error", $th->getMessage());
        }
    }

    private function resolveSharedImageName(CmsSlider $slider): ?string
    {
        if (filled($slider->image_name)) {
            return $slider->image_name;
        }

        return optional(collect($slider->translations ?? [])->first(function ($translation) {
            return filled($translation->image_name ?? null);
        }))->image_name;
    }

    private function resolveSharedImagePath(CmsSlider $slider): ?string
    {
        if (filled($slider->image_name)) {
            return $slider->image_path ?: "sliders";
        }

        $translation = collect($slider->translations ?? [])->first(function ($translation) {
            return filled($translation->image_name ?? null);
        });

        return filled(optional($translation)->image_name)
            ? (optional($translation)->image_path ?: "sliders")
            : null;
    }

    private function uploadSharedImage(Request $request, string $imagePath, CmsSlider $slider, array $langContent): ?string
    {
        if (!$request->hasFile("shared_image")) {
            return null;
        }

        $firstTitle = collect($langContent)
            ->pluck("title")
            ->filter()
            ->first();

        return UploadHelper::upload(
            $request->file("shared_image"),
            Str::slug($firstTitle ?: ("slider-" . $slider->id)),
            $imagePath,
            1200,
            450,
            true,
        );
    }

    private function uploadResponsiveVariants(Request $request, string $imagePath, ?string $desktopImageName): void
    {
        if (!filled($desktopImageName)) {
            return;
        }

        foreach (["tablet", "mobile"] as $variant) {
            $inputName = "shared_image_" . $variant;
            if (!$request->hasFile($inputName)) {
                continue;
            }

            $this->storeResponsiveVariant(
                $request->file($inputName),
                $imagePath,
                $this->responsiveVariantImageName($desktopImageName, $variant),
            );
        }
    }

    private function storeResponsiveVariant($image, string $imagePath, string $variantImageName): void
    {
        $mimeType = Str::lower(trim((string) $image->getMimeType()));

        if (!in_array($mimeType, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/avif'], true)) {
            throw new \InvalidArgumentException(__('admin.dynamic.slider_image_types'));
        }

        $imagePath = $this->safeResponsiveImageWritePath($imagePath);
        $variantImageName = $this->sanitizeResponsiveVariantImageName($variantImageName);
        $extension = Str::lower(pathinfo($variantImageName, PATHINFO_EXTENSION));
        $format = in_array($extension, ["jpg", "jpeg"], true) ? "jpg" : $extension;
        $stream = $mimeType === 'image/avif'
            ? $this->validatedAvifContents($image)
            : (string) ImageManager::usingDriver(Driver::class)->decodeSplFileInfo($image)->encodeUsingFileExtension($format);
        $path = $imagePath . "/" . $variantImageName;

        if ($this->isStorageSliderPath($imagePath)) {
            Storage::disk("public")->put($path, $stream);
            return;
        }

        if (!is_dir(public_path(trim($imagePath, "/")))) {
            mkdir(public_path(trim($imagePath, "/")), 0755, true);
        }

        if (file_put_contents(public_path($path), (string) $stream) === false) {
            throw new \RuntimeException('Slider image could not be written.');
        }
    }

    private function validatedAvifContents($image): string
    {
        $sourcePath = $image->getRealPath();
        if (! $sourcePath || ! function_exists('imagecreatefromavif')) {
            throw new \InvalidArgumentException(__('admin.dynamic.avif_unsupported'));
        }

        $decodedImage = @imagecreatefromavif($sourcePath);
        if ($decodedImage === false) {
            throw new \InvalidArgumentException(__('admin.dynamic.avif_invalid'));
        }
        imagedestroy($decodedImage);

        $contents = file_get_contents($sourcePath);
        if ($contents === false) {
            throw new \InvalidArgumentException(__('admin.dynamic.avif_unreadable'));
        }

        return $contents;
    }

    private function responsiveVariantImageName(string $desktopImageName, string $variant): string
    {
        $desktopImageName = basename(str_replace("\\", "/", $desktopImageName));
        $pathInfo = pathinfo($desktopImageName);
        $extension = isset($pathInfo["extension"]) ? "." . $pathInfo["extension"] : "";
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', "-", (string) ($pathInfo["filename"] ?? $desktopImageName));
        $filename = trim((string) $filename, ".-_");

        return ($filename ?: "slider") . "-" . $variant . $extension;
    }

    private function responsiveImageSetForSlider(CmsSlider $slider): array
    {
        $imageName = $this->resolveSharedImageName($slider);
        $imagePath = $this->resolveSharedImagePath($slider) ?: "sliders";
        $desktopUrl = $this->resolveSliderImageUrl($imagePath, $imageName);
        $tabletName = filled($imageName) ? $this->responsiveVariantImageName($imageName, "tablet") : null;
        $mobileName = filled($imageName) ? $this->responsiveVariantImageName($imageName, "mobile") : null;
        $tabletUrl = $this->resolveSliderImageUrl($imagePath, $tabletName);
        $mobileUrl = $this->resolveSliderImageUrl($imagePath, $mobileName);

        return [
            "desktop" => [
                "label" => "Desktop/default",
                "help" => "wide hero crop",
                "dimensions" => "2560x720",
                "status" => filled($desktopUrl) ? "uploaded" : "missing",
                "url" => $desktopUrl,
                "name" => $imageName,
            ],
            "tablet" => [
                "label" => "Tablet",
                "help" => "compact crop",
                "dimensions" => "1600x1020",
                "status" => filled($tabletUrl) ? "uploaded" : (filled($desktopUrl) ? "fallback to desktop" : "missing"),
                "url" => $tabletUrl ?: $desktopUrl,
                "name" => $tabletName,
            ],
            "mobile" => [
                "label" => "Mobile",
                "help" => "portrait crop",
                "dimensions" => "1200x2280",
                "status" => filled($mobileUrl) ? "uploaded" : (filled($desktopUrl) ? "fallback to desktop" : "missing"),
                "url" => $mobileUrl ?: $desktopUrl,
                "name" => $mobileName,
            ],
        ];
    }

    private function getSliderOverview(string $placement, ?string $themeKey = null)
    {
        return $this->scopeSliderQuery(CmsSlider::with("translations"), $themeKey)
            ->where("placement", $placement)
            ->orderBy("rank", "ASC")
            ->orderBy("id", "DESC")
            ->get()
            ->map(function (CmsSlider $slider) {
                $primaryTranslation = $slider->translations[0] ?? null;
                $resolvedImageName = $this->resolveSharedImageName($slider);
                $resolvedImagePath = $this->resolveSharedImagePath($slider) ?: "sliders";

                return [
                    "id" => $slider->id,
                    "rank" => (int) ($slider->rank ?? 0),
                    "placement" => $slider->placement,
                    "theme_scope" => $this->themeScopeLabel($slider->theme_key ?? null),
                    "status" => (int) $slider->status,
                    "status_label" => (int) $slider->status === 1 ? "Aktif" : "Pasif",
                    "toggle_status" => (int) $slider->status === 1 ? 0 : 1,
                    "toggle_label" => (int) $slider->status === 1 ? __('admin.copy.deactivate') : "Aktif Et",
                    "toggle_action" => (int) $slider->status === 1 ? "passive" : "active",
                    "title" => optional($primaryTranslation)->title ?: __('admin.dynamic.untitled_slider'),
                    "has_image" => filled($resolvedImageName),
                    "edit_url" => route("cms.admin.sliders.edit", ["id" => $slider->id]),
                    "image_url" => filled($resolvedImageName)
                        ? ($this->resolveSliderImageUrl($resolvedImagePath, $resolvedImageName) ?: asset('cms-assets/blank-placeholder.svg'))
                        : asset('cms-assets/blank-placeholder.svg'),
                ];
            })
            ->values();
    }

    private function reorderSliderRanks(CmsSlider $slider, int $desiredRank): void
    {
        $orderedIds = $this->scopeSliderQuery(CmsSlider::query(), $slider->theme_key ?? null)
            ->where("placement", $slider->placement)
            ->where("id", "!=", $slider->id)
            ->orderBy("rank", "ASC")
            ->orderBy("id", "DESC")
            ->pluck("id")
            ->values()
            ->all();

        $targetIndex = max(0, min(count($orderedIds), $desiredRank - 1));
        array_splice($orderedIds, $targetIndex, 0, [$slider->id]);

        foreach ($orderedIds as $index => $sliderId) {
            CmsSlider::query()->whereKey($sliderId)->update([
                "rank" => $index + 1,
            ]);
        }
    }

    private function normalizeSliderRanks(?string $placement = null, ?string $themeKey = null): void
    {
        if ($placement === null) {
            foreach (CmsSlider::placements() as $knownPlacement) {
                $this->normalizeSliderRanks($knownPlacement, $themeKey);
            }

            return;
        }

        $this->scopeSliderQuery(CmsSlider::query(), $themeKey)
            ->where("placement", $placement)
            ->orderBy("rank", "ASC")
            ->orderBy("id", "DESC")
            ->pluck("id")
            ->each(function ($sliderId, $index) {
                CmsSlider::query()->whereKey($sliderId)->update([
                    "rank" => $index + 1,
                ]);
            });
    }

    private function nextRankForPlacement(string $placement, ?string $themeKey = null): int
    {
        return ((int) $this->scopeSliderQuery(CmsSlider::query(), $themeKey)
            ->where("placement", $placement)
            ->max("rank")) + 1;
    }

    private function sliderDataFromRequest(Request $request): array
    {
        $request->validate([
            'status' => 'sometimes|in:0,1', 'rank' => 'sometimes|integer|min:1',
            'langContent' => 'sometimes|array', 'langContent.*' => 'array:cms_language_id,title,url,description',
            'langContent.*.cms_language_id' => 'required|integer|distinct|exists:cms_languages,id',
            'langContent.*.title' => 'nullable|string|max:255', 'langContent.*.description' => 'nullable|string',
            'langContent.*.url' => 'nullable|string|max:2048',
        ]);
        foreach ($request->input('langContent', []) as $content) {
            if (filled($content['url'] ?? null) && !\App\Support\B1ContentUrl::allowed($content['url'])) {
                throw ValidationException::withMessages(['langContent' => 'Invalid slider destination.']);
            }
        }
        $sliderData = $request->only((new CmsSlider())->getFillable());

        if (!$this->themeScopeEnabled()) {
            unset($sliderData["theme_key"]);
        }

        return $sliderData;
    }

    private function scopeSliderQuery($query, ?string $themeKey)
    {
        if (!$this->themeScopeEnabled()) {
            return $query;
        }

        return filled($themeKey)
            ? $query->where("theme_key", $themeKey)
            : $query->whereNull("theme_key");
    }

    private function managedThemeKey(): ?string
    {
        $theme = request()->input('theme', request()->input('theme_key', 'global'));
        if ($theme !== 'global') throw ValidationException::withMessages(['theme_key' => 'Only Global is available.']);
        return 'global';
    }

    private function affectedSliderScopes(array $ids)
    {
        if ($this->themeScopeEnabled()) {
            return CmsSlider::whereIn("id", $ids)
                ->get(["placement", "theme_key"])
                ->map(fn (CmsSlider $slider) => [
                    "placement" => $slider->placement,
                    "theme_key" => $slider->theme_key ?? null,
                ])
                ->unique(fn (array $scope) => ($scope["placement"] ?? "") . "|" . ($scope["theme_key"] ?? ""))
                ->values();
        }

        return CmsSlider::whereIn("id", $ids)
            ->pluck("placement")
            ->map(fn ($placement) => [
                "placement" => $placement,
                "theme_key" => null,
            ])
            ->unique(fn (array $scope) => (string) ($scope["placement"] ?? ""))
            ->values();
    }

    private function themeScopeEnabled(): bool
    {
        return Schema::hasTable("cms_sliders") && Schema::hasColumn("cms_sliders", "theme_key");
    }

    private function themeScopeLabels(): array
    {
        return collect(CmsThemeResolver::supportedThemes())
            ->mapWithKeys(fn (array $theme) => [$theme["key"] => $theme["label"]])
            ->all();
    }

    private function themeScopeLabel(?string $themeKey): string
    {
        $labels = $this->themeScopeLabels();

        return $labels[$themeKey ?? ""] ?? __('admin.dynamic.legacy_global_record');
    }

    private function resolvePlacement(?string $placement, string $default = CmsSlider::PLACEMENT_HOME): string
    {
        $resolved = filled($placement) ? (string) $placement : $default;

        if (!in_array($resolved, CmsSlider::placements(), true)) {
            throw ValidationException::withMessages([
                "placement" => __('admin.dynamic.invalid_slider_placement'),
            ]);
        }

        return $resolved;
    }

    private function placementLabels(): array
    {
        return [
            CmsSlider::PLACEMENT_HOME => "Ana Sayfa",
        ];
    }

    private function forgetSliderCaches(array|string $placements): void
    {
        foreach (collect($placements)->filter()->unique() as $placement) {
            $this->forgetSliderCache($placement);
        }
    }

    private function forgetSliderCache(string $placement): void
    {
        if ($placement === CmsSlider::PLACEMENT_HOME) {
            Cache::forget('cms_front_home:sliders');
            $locales = collect(['tr', 'en', 'es'])
                ->merge(Schema::hasTable('cms_languages') ? CmsLanguage::query()->pluck('code') : collect())
                ->filter()
                ->map(fn ($code) => Str::lower((string) $code))
                ->unique();

            $locales->each(fn ($code) => Cache::forget('cms_front_home:sliders:' . preg_replace('/[^a-z0-9_-]/', '', $code)));
            foreach ($this->sliderCacheThemeSuffixes() as $themeSuffix) {
                $locales->each(fn ($code) => Cache::forget('cms_front_home:sliders:' . preg_replace('/[^a-z0-9_-]/', '', $code) . ':theme:' . $themeSuffix));
            }
        }


    }

    private function sliderCacheThemeSuffixes(): array
    {
        return array_values(array_unique(array_merge(['legacy'], CmsThemeResolver::supportedThemeKeys())));
    }

    private function resolveSliderImageUrl(?string $imagePath, ?string $imageName): ?string
    {
        if (!filled($imageName)) {
            return null;
        }

        $imagePath = trim((string) $imagePath, "/");
        $imageName = ltrim((string) $imageName, "/");
        $candidates = collect([
            filled($imagePath) ? $imagePath . "/" . $imageName : null,
            $imageName,
            "storage/sliders/" . $imageName,
        ])->filter()->unique();

        foreach ($candidates as $candidate) {
            if (file_exists(public_path($candidate))) {
                return asset($candidate);
            }
        }

        return null;
    }

    private function isStorageSliderPath(string $imagePath): bool
    {
        return trim($imagePath, "/") === self::SLIDER_STORAGE_IMAGE_PATH;
    }

    private function safeResponsiveImageWritePath(?string $imagePath): string
    {
        $normalizedPath = preg_replace('#/+#', '/', str_replace("\\", "/", trim((string) $imagePath, "/")));

        if ($normalizedPath === self::SLIDER_PUBLIC_IMAGE_PATH) {
            return self::SLIDER_PUBLIC_IMAGE_PATH;
        }

        if ($normalizedPath === self::SLIDER_STORAGE_IMAGE_PATH || $normalizedPath === "storage/" . self::SLIDER_STORAGE_IMAGE_PATH) {
            return self::SLIDER_STORAGE_IMAGE_PATH;
        }

        return self::SLIDER_STORAGE_IMAGE_PATH;
    }

    private function sanitizeResponsiveVariantImageName(string $variantImageName): string
    {
        $variantImageName = basename(str_replace("\\", "/", $variantImageName));
        $pathInfo = pathinfo($variantImageName);
        $extension = Str::lower((string) ($pathInfo["extension"] ?? ""));

        if (!in_array($extension, self::RESPONSIVE_IMAGE_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                "shared_image" => __('admin.dynamic.responsive_slider_types'),
            ]);
        }

        $filename = preg_replace('/[^A-Za-z0-9._-]+/', "-", (string) ($pathInfo["filename"] ?? ""));
        $filename = trim((string) $filename, ".-_");

        if ($filename === "") {
            throw ValidationException::withMessages([
                "shared_image" => __('admin.dynamic.responsive_slider_name_invalid'),
            ]);
        }

        return $filename . "." . $extension;
    }
}
