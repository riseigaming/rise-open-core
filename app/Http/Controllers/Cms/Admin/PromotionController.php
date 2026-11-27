<?php

namespace App\Http\Controllers\Cms\Admin;

use App\Helper\UploadHelper;
use App\Http\Controllers\Controller;
use App\Models\CmsLanguage;
use App\Models\CmsPromotion;
use App\Models\CmsPromotionTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
class PromotionController extends Controller
{
    public function index(){
        return view("cms.admin.promotions.index");
    }

    public function getAjaxList(Request $request) {
        $promotionsQuery = CmsPromotion::with("translations")->when($request->search, function ($q) use($request){
            $search = implode("%", explode(" ", $request->search));
            return $q->whereHas("translation", function ($sq) use($search){
                return $sq->where("listing_title", "like", "%$search%")
                        ->where("detail_title", "like", "%$search%")
                        ->where("short_description", "like", "%$search%")
                        ->where("description", "like", "%$search%");
            });
        });

        $promotionCount = (clone $promotionsQuery)->count();

        $sort = (string) $request->get('sort', 'rank');
        $sort = ['campaignTypeText' => 'is_permanent', 'topLineText' => 'show_in_top_line'][$sort] ?? $sort;
        $direction = strtolower((string) $request->get('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $languageId = CmsLanguage::where('code', app()->getLocale())->value('id')
            ?: CmsLanguage::where('is_default', 1)->value('id');
        if (in_array($sort, ['listing_title', 'detail_title'], true)) {
            $sort = CmsPromotionTranslation::select($sort)->whereColumn('cms_promotion_id', 'cms_promotions.id')
                ->whereNotNull($sort)->whereRaw("TRIM(`{$sort}`) <> ''")
                ->orderByRaw('cms_language_id = ? DESC', [$languageId])->orderBy('cms_language_id')->limit(1);
        } elseif (!in_array($sort, ['id','rank','status','thumbnail','detail_image','redirect_url','is_permanent','show_in_top_line'], true)) {
            abort(422, 'Invalid promotion sort field.');
        }
        $promotions = $promotionsQuery
            ->when(Schema::hasColumn('cms_promotions', 'show_on_homepage_top'), function ($query) {
                $query->orderByDesc('show_on_homepage_top');
            })
            ->orderBy($sort, $direction)
            ->orderBy("id", "ASC")
            ->offset(max(0, (int) $request->get("offset", 0)))
            ->limit(min(250, max(1, (int) $request->get("limit", 10))))
            ->get();

        foreach ($promotions as $p){
            $translation = $p->translations->first(fn ($row) => (int) $row->cms_language_id === (int) $languageId && filled($row->listing_title))
                ?? $p->translations->first(fn ($row) => filled($row->listing_title)) ?? $p->translations->first();
            $p->campaignTypeText = (int) $p->is_permanent === 1 ? __('admin.ui.normal') : __('admin.promotions.timed');
            $p->topLineText = (int) ($p->show_in_top_line ?? 0) === 1 ? __('admin.ui.yes') : __('admin.ui.no');
            $p->listing_title = $translation->listing_title ?? "";
            $p->detail_title = $translation->detail_title ?? "";
            $p->short_description = $translation->short_description ?? "";
            $p->description = $translation->description ?? "";
        }

        $data = [
            "rows" => $promotions,
            "total" => $promotionCount
        ];

        return response()->json($data);
    }

    public function create(){
        $languages = CmsLanguage::query()->where("status", 1)->get()->toArray();


        $data = [
            "languages" => $languages,
        ];
        return view("cms.admin.promotions.create", $data);
    }

    public function edit($id){

        $languages = CmsLanguage::query()->where("status", 1)->get()->toArray();
        $promotion = CmsPromotion::with("translations")->find($id);

        $transData = [];

        foreach ($promotion->translations->toArray() as $translation){
            $transData[$translation["cms_language_id"]] = $translation;
        }

        $data = [
            "languages" => $languages,
            "promotion" => $promotion,
            "transData" => $transData,
        ];

        return view("cms.admin.promotions.edit", $data);
    }
    public function store(Request $request){

        try {
            $partnerData = $this->normalizePromotionPayload($request);

            if($request->hasFile("thumbnail")){
                $partnerData["thumbnail"] = UploadHelper::upload(
                    $request->file("thumbnail"),
                    Str::slug("thumbnail-".uniqid()),
                    "promotions",
                    477,
                    588,
                );
            }
            if($request->hasFile("detail_image")){
                $partnerData["detail_image"] = UploadHelper::upload(
                    $request->file("detail_image"),
                    Str::slug("detail_image-".uniqid()),
                    "promotions",
                    1520,
                    570,
                );
            }

            $store = DB::transaction(function () use ($partnerData, $request) {
                $promotion = CmsPromotion::query()->create($partnerData);
                foreach ($request->input('langContent', []) as $content) {
                    $promotion->translations()->create($content);
                }
                return $promotion;
            });
            if ($store) {
                return response()->json([
                    "status" => 200,
                    "message" => __('admin.copy.record_added')
                ]);
            }

        }catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        }catch (\InvalidArgumentException $exception) {
            return response()->json([
                "status" => 422,
                "message" => $exception->getMessage(),
            ], 422);
        }catch (\Throwable $th){
            \Illuminate\Support\Facades\Log::error('CMS promotion upload/store failed.', ['exception' => $th]);

            return response()->json([
                "status" => 500,
                "message" => __('admin.copy.unexpected_error')
            ], 500);
        }
    }

    public function bulkAction(Request $request){
        $ids = explode(",", $request->get("ids",""));

        if(count($ids) <= 0 ){
            return response()->json([
                "status" => 400,
                "message" => __('admin.copy.selection_required')
            ]);
        }
        $partner = CmsPromotion::whereIn("id", $ids);
        $status = false;
        if($request->get("action") == "active"){
            $status = DB::transaction(function () use ($partner): int {
                return $partner->update(["status" => "active"]);
            });
        }elseif($request->get("action") == "passive"){
            $status = $partner->update(["status" => "passive"]);
        }elseif($request->get("action") == "delete"){
            $status = $partner->delete();
        }

        if(!$status){
            return response()->json([
                "status" => 500,
                "message" => __('admin.copy.generic_error')
            ]);
        }

        return response()->json([
            "status" => 201,
            "message" => __('admin.copy.action_success')
        ]);
    }

    public function delete($id){
        $type = CmsPromotion::find($id);

        if($type->delete()){
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

    public function update(Request $request){

        try {
            $partner = CmsPromotion::find($request->get("promotionId"));
            $partnerData = $this->normalizePromotionPayload($request, $partner);

            if($request->hasFile("thumbnail")){
                $partnerData["thumbnail"] = UploadHelper::upload(
                    $request->file("thumbnail"),
                    Str::slug("thumbnail-".uniqid()),
                    "promotions",
                    $request->filled("end_date") ? 100 : 477,
                    $request->filled("end_date") ? 100 : 588,
                );
            }
            if($request->hasFile("detail_image")){
                $partnerData["detail_image"] = UploadHelper::upload(
                    $request->file("detail_image"),
                    Str::slug("detail_image-".uniqid()),
                    "promotions",
                    1520,
                    570,
                );
            }
            $langContent = $request->get("langContent", []);
            $update = DB::transaction(function () use ($partner, $partnerData, $langContent): bool {
                $lockedPromotion = CmsPromotion::query()->whereKey($partner->id)->lockForUpdate()->firstOrFail();
                $saved = $lockedPromotion->update($partnerData);
                if ($saved) {
                    foreach ($langContent as $lc) {
                        $transData = $lc;
                        $transData["cms_promotion_id"] = $lockedPromotion->id;
                        CmsPromotionTranslation::query()->updateOrCreate([
                            "cms_promotion_id" => $lockedPromotion->id,
                            "cms_language_id" => $lc["cms_language_id"],
                        ], $transData);
                    }
                }
                return $saved;
            });

            if($update){
                return response()->json([
                    "status" => 200,
                    "message" => __('admin.copy.record_added')
                ]);
            }

        }catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        }catch (\InvalidArgumentException $exception) {
            return response()->json([
                "status" => 422,
                "message" => $exception->getMessage(),
            ], 422);
        }catch (\Throwable $th){
            \Illuminate\Support\Facades\Log::error('CMS promotion upload/update failed.', ['exception' => $th]);

            return response()->json([
                "status" => 500,
                "message" => __('admin.copy.unexpected_error')
            ], 500);
        }
    }

    

    

    public function order()
    {
        $promotions = CmsPromotion::with("translations")
            ->orderBy("rank")
            ->orderBy("id")
            ->get();

        return view("cms.admin.promotions.order", [
            "promotions" => $promotions,
            "pageTitle" => __('admin.copy.campaign_order'),
        ]);
    }

    public function updateOrder(Request $request)
    {
        $orderedIds = array_values(array_filter($request->input("ordered_ids", [])));

        if (count($orderedIds) === 0) {
            return response()->json([
                "status" => 400,
                "message" => __('admin.promotions.order_selection_required'),
            ], 400);
        }

        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $promotionId) {
                CmsPromotion::query()
                    ->where("id", $promotionId)
                    ->update([
                        "rank" => $index + 1,
                    ]);
            }
        });

        return response()->json([
            "status" => 200,
            "message" => __('admin.promotions.order_updated'),
        ]);
    }

    private function normalizePromotionPayload(Request $request, ?CmsPromotion $promotion = null): array
    {
        $request->validate([
            'status' => 'sometimes|in:active,passive', 'end_date' => 'nullable|date',
            'langContent' => 'sometimes|array', 'langContent.*' => 'array:cms_language_id,listing_title,detail_title,short_description,description,redirect_button_text',
            'langContent.*.cms_language_id' => 'required|integer|distinct|exists:cms_languages,id',
        ]);
        $content = [];
        foreach ($request->input('langContent', []) as $translation) {
            foreach (['listing_title', 'detail_title', 'short_description', 'description'] as $field) {
                if (isset($translation[$field]) && !is_string($translation[$field])) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['langContent' => 'Translation text must be a string.']);
                }
                $translation[$field] = $translation[$field] ?? '';
            }
            $content[] = $translation;
        }
        $request->merge(['langContent' => $content]);
        $url = $request->input('redirect_url');
        if (filled($url) && (!is_string($url) || !\App\Support\B1ContentUrl::allowed($url))) {
            throw new \InvalidArgumentException('Invalid promotion destination.');
        }
        $promotionData = $request->only((new CmsPromotion())->getFillable());
        if (! Schema::hasColumn('cms_promotions', 'show_on_homepage_top')) {
            unset($promotionData['show_on_homepage_top']);
        }
        $promotionData['is_permanent'] = (int) $request->get('is_permanent', 0);
        $promotionData['show_in_top_line'] = $promotionData['is_permanent'] === 1
            ? (int) $request->get('show_in_top_line', 0)
            : 0;
        if (Schema::hasColumn('cms_promotions', 'show_on_homepage_top')) {
            $promotionData['show_on_homepage_top'] = (int) $request->get('show_on_homepage_top', 0);
        }
        $promotionData['end_date'] = $promotionData['is_permanent'] === 1
            ? null
            : ($request->filled('end_date') ? $request->get('end_date') : null);
        $promotionData['rank'] = $request->filled('rank')
            ? (int) $request->get('rank')
            : ($promotion ? (int) $promotion->rank : ((int) CmsPromotion::query()->max('rank') + 1));

        return $promotionData;
    }

}
