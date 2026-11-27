<?php

namespace App\Models;

use App\Support\LocalEgress;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class CmsPromotion extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ["id"];
    protected $fillable = [
        "thumbnail",
        "detail_image",
        "redirect_url",
        "status",
        "is_permanent",
        "show_in_top_line",
        "show_on_homepage_top",
        "end_date",
        "rank",
    ];

    public function translation(){
        return $this->hasOne(CmsPromotionTranslation::class, "cms_promotion_id")->where("cms_language_id", (session("currentLanguageId") ?? 1));
    }

    public function translations(){
        return $this->hasMany(CmsPromotionTranslation::class, "cms_promotion_id");
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereHas('translation', function (Builder $translation) {
                $translation->whereNotNull('listing_title')
                    ->whereRaw("TRIM(listing_title) <> ''");
            })
            ->where(function (Builder $promotion) {
                $promotion->where('is_permanent', 1)
                    ->orWhere(function (Builder $timed) {
                        $timed->where('is_permanent', 0)
                            ->where(function (Builder $date) {
                                $date->whereNull('end_date')->orWhere('end_date', '>', now());
                            });
                    });
            });
    }

    protected function blankImageDataUri(): string
    {
        return 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"></svg>';
    }

    protected function fallbackImageUrl(): string
    {
        return asset('cms-assets/promotions/promotion-fallback.svg');
    }

    protected function publicStorageUrlIfExists(string $relativePath): ?string
    {
        $normalizedPath = ltrim($relativePath, '/');

        return is_file(public_path($normalizedPath))
            ? url($normalizedPath)
            : null;
    }

    protected function resolveLocalImageUrl(string $imageName): ?string
    {
        $imageName = ltrim(trim($imageName), '/');

        if ($imageName === '' || str_contains($imageName, '..')) {
            return null;
        }

        foreach ([
            'storage/promotions/' . $imageName,
            'cms-assets/promotions/legacy/' . $imageName,
            'cms-assets/promotions/' . $imageName,
        ] as $relativePath) {
            $url = $this->publicStorageUrlIfExists($relativePath);

            if ($url) {
                return $url;
            }
        }

        return null;
    }

    protected function resolveRemoteImageUrl(string $url): string
    {
        if (!LocalEgress::isEnabled()) {
            return $url;
        }

        return (LocalEgress::isUrlAllowed($url) || LocalEgress::isBrowserAssetUrlAllowed($url, 'img'))
            ? $url
            : $this->blankImageDataUri();
    }

    public function getThumbnail(){
        $thumbnail = trim((string) $this->thumbnail);

        if ($thumbnail !== '') {
            if (filter_var($thumbnail, FILTER_VALIDATE_URL)) {
                return $this->resolveRemoteImageUrl($thumbnail);
            }

            return $this->resolveLocalImageUrl($thumbnail)
                ?? $this->fallbackImageUrl();
        }

        return $this->fallbackImageUrl();
    }

    public function getDetailImage(){
        $detailImage = trim((string) $this->detail_image);

        if ($detailImage !== '') {
            if (filter_var($detailImage, FILTER_VALIDATE_URL)) {
                return $this->resolveRemoteImageUrl($detailImage);
            }

            return $this->resolveLocalImageUrl($detailImage)
                ?? $this->getThumbnail();
        }

        return $this->getThumbnail();
    }

    public function getRemainingTime()
    {
        $end = Carbon::parse($this->end_date);
        $now = Carbon::now();

        if ($end->lessThanOrEqualTo($now)) {
            return null;
        }

        $diff = $now->diff($end);

        return "{$diff->days} Gün   {$diff->h}   Saat {$diff->i} Dakika";
    }
}
