<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsGame extends Model
{
    public const TYPES = ['slot'=>'Slot','live'=>'Canlı casino','table'=>'Masa oyunları','instant'=>'Hızlı oyunlar'];
    protected $guarded = ['id'];
    protected $casts = ['is_restricted'=>'boolean','is_maintenance'=>'boolean'];
    public function provider() { return $this->belongsTo(Provider::class); }
    public function catalogVisible(): bool { return $this->status === 'active' && !$this->is_restricted && $this->provider?->catalogVisible(); }
    public function launchState(): string
    {
        if ($this->is_maintenance || $this->provider?->is_maintenance) return 'maintenance';
        if ($this->slug==='chess-bet' && $this->provider?->slug===config('chess_wallet.provider_slug')) return 'chess';
        if (app(\App\Services\InternalGames\InternalGameCatalog::class)->allowsNewAdmission($this) && \Illuminate\Support\Facades\Schema::hasTable('wallet_accounts')) return 'available';
        if (app(\App\Services\ExternalGames\ExternalGameAvailabilityService::class)->supports($this)) return 'external';
        return app(\App\Services\InternalGames\InternalGameDemoModeService::class)->isInternalDemoSupported($this) ? 'demo' : 'unsupported';
    }
    public function imageUrl(): string
    {
        if ($this->slug==='dice' && app(\App\Services\InternalGames\InternalGameCatalog::class)->supports($this)) return asset('internal-games/dice/card.svg');
        // Only images produced by this application's upload path can become public URLs.
        return $this->thumbnail_image && preg_match('#\Acatalog/games/[a-f0-9-]+\.(?:png|jpg|webp)\z#D', $this->thumbnail_image)
            ? asset('storage/'.$this->thumbnail_image) : asset('cms-assets/blank-placeholder.svg');
    }
}
