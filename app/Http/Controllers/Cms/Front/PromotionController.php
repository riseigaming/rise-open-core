<?php

namespace App\Http\Controllers\Cms\Front;

use App\Http\Controllers\Controller;
use App\Models\CmsPromotion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PromotionController extends Controller
{
    public function index(){
        $promotions = $this->translatedPromotions()
            ->when(Schema::hasColumn('cms_promotions', 'show_on_homepage_top'), function ($query) {
                $query->orderByDesc('show_on_homepage_top');
            })
            ->orderBy("rank")
            ->orderByDesc("updated_at")
            ->get();

        return frontView("promotions", ["promotions" => $promotions]);
    }

    

    

    public function detail($id){
        $promotion = $this->translatedPromotions()->find($id);

        if( ! $promotion instanceof  CmsPromotion){
            return redirect()->route('cms.promotions');
        }

        return frontView('promotion-detail',[
            "promotion" => $promotion,

        ]);
    }
    public function getDetail(Request  $request){
        $promotion = $this->translatedPromotions()->find($request->get("promotionId"));

        if( ! $promotion instanceof  CmsPromotion){
            return response()->json([
                "message" => trans("cms_alerts.promotion_not_found"),
                "status" => 400,
            ]);
        }

        $promoView = frontView("partials.promotion-modal-inner", [
            "promotion" => $promotion,

        ])->render();

        return response()->json([
            "message" => "success",
            "status" => 200,
            "promotion" => $promotion,
            "promotionView" => $promoView
        ]);
    }

    private function translatedPromotions(): Builder
    {
        return CmsPromotion::query()
            ->with('translation')
            ->publiclyVisible();
    }

    
}
