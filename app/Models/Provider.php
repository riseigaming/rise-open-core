<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['is_maintenance'=>'boolean','show_left_panel'=>'boolean','config'=>'array'];
    public function configData(): array { return (array) $this->config; }
    public function games() { return $this->hasMany(CmsGame::class); }
    public function catalogVisible(): bool { return $this->status === 'active' && $this->show_left_panel; }
    public function productKey(): string { return $this->kind === 'internal' ? 'internal_games' : 'external_games'; }
}
