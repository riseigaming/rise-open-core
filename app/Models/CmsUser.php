<?php

namespace App\Models;

use App\Services\Security\CmsPasswordIntegrityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CmsUser extends Authenticatable
{
    use HasFactory, SoftDeletes, Notifiable;

    public const WEEKLY_WINNER_VIRTUAL_SOURCE = 'weekly_winner_virtual';
    public const WEEKLY_WINNER_CONVERTED_SOURCE = 'weekly_winner_converted';

    protected $table = "cms_users";
    protected $fillable = [
        "first_name",
        "last_name",
        "username",
        "email",
        "phone",
        "phone_normalized",
        "birth_date",
        "birth_city",
        "country_id",
        "registration_source",
        "city_id",
        "state_id",
        "language",
        "currency",
        "personal_id",
        "player_id",
        "external_id",
        "user_type_id",
        "gender",
        "status",
        "role",
        "balance",
        "parent_id",
        "last_seen",
        "user_color",
        "activity_point",
        "is_chat_blocked",
        "is_private",
        "presence_status",
        "profile_card_visibility",
        "follower_visible_sections",
        "follow_request_policy",
        "follow_request_permission",
        "message_permission",
        "group_chat_permission",
        "sports_room_auto_share_accepted_coupon",
        "created_at",
        "updated_at",
        "deleted_at",
        "email_verify_hash",
        "email_verified_at",
        "marketing_email_opt_in",
        "marketing_email_opted_out_at",
        "transactional_email_opt_in",
        "transactional_email_opted_out_at",
        "persistent_game_picture_in_picture_disabled",
        "remember_token",
        "slot_bet_limit",
        "live_bet_limit",
        "partner_id",
        "promotion_code_id",
        "promotion_link_id",
        "promotion_source_id",
        "created_by_admin",
    ];

    protected $guarded = ["id"];
    protected $hidden = [
        'password',
        'password_raw',
        'remember_token',
    ];

    protected $casts = [
        'follower_visible_sections' => 'array',
        'sports_room_auto_share_accepted_coupon' => 'boolean',
        'marketing_email_opt_in' => 'boolean',
        'marketing_email_opted_out_at' => 'datetime',
        'transactional_email_opt_in' => 'boolean',
        'transactional_email_opted_out_at' => 'datetime',
        'persistent_game_picture_in_picture_disabled' => 'boolean',
        'is_smoke_account' => 'boolean',
    ];
    protected $appends = ["full_name", "modified_last_seen", "modified_created_at"];

    protected static function booted(): void
    {
        static::saving(function (CmsUser $user): void {
            // The nullable legacy column remains only until its targeted cleanup
            // migration is deployed. No runtime write may restore plaintext.
            if ($user->getAttribute('password_raw') !== null) {
                $user->setAttribute('password_raw', null);
            }
            if ($user->isDirty('phone')) {
                $attributes = app(\App\Services\Identity\PhoneIdentityService::class)
                    ->attributesForUser($user, $user->phone);
                $user->setAttribute('phone', $attributes['phone']);
                if (Schema::hasColumn('cms_users', 'phone_normalized')) {
                    $user->setAttribute('phone_normalized', $attributes['phone_normalized']);
                }
            }
            if ($user->isDirty('password')) {
                app(CmsPasswordIntegrityService::class)->auditPasswordMutation(
                    $user,
                    $user->getOriginal('password'),
                    $user->password,
                    'cms_user_model_saving'
                );
            }
        });
    }

    public function userType(){
        return $this->belongsTo(CmsUserType::class, "user_type_id");
    }

    public function getFullnameAttribute(){
        if($this->first_name){
            return $this->first_name . " ". $this->last_name;
        }

        return $this->username;
    }

    public function getModifiedLastSeenAttribute(){

        if(!is_null($this->last_seen)){
            return date("d/m/Y H:i:s", strtotime($this->last_seen));
        }

        return "-";
    }
    public function getModifiedCreatedAtAttribute(){

        if(!is_null($this->created_at)){
            return date("d/m/Y H:i:s", strtotime($this->created_at));
        }

        return "-";
    }

    public function subAccounts(){
        return $this->hasMany(self::class, "parent_id");
    }

    public function isWeeklyWinnerVirtual(): bool { return (string)$this->registration_source === self::WEEKLY_WINNER_VIRTUAL_SOURCE; }

    public function parentAccount(){
        return $this->belongsTo(self::class, "parent_id");
    }

    public function state(){
        return $this->belongsTo(CmsState::class, "state_id");
    }

    public function city(){
        return $this->belongsTo(CmsCity::class, "city_id");
    }

    public function country(){
        return $this->belongsTo(CmsCountry::class, "country_id");
    }

    public function sessions(){
        return $this->hasMany(CmsSession::class, "cms_user_id");
    }

    public function scopeWeeklyWinnerVirtual($query)
    {
        return $query->where('registration_source', self::WEEKLY_WINNER_VIRTUAL_SOURCE);
    }

    public function scopeWithoutWeeklyWinnerVirtual($query)
    {
        if (!Schema::hasColumn($query->getModel()->getTable(), 'registration_source')) {
            return $query;
        }

        return $query->where(function ($query) {
            $query->whereNull('registration_source')
                ->orWhere('registration_source', '<>', self::WEEKLY_WINNER_VIRTUAL_SOURCE);
        });
    }

    public function scopeWithoutAnalyticsTestAccounts($query)
    {
        $query->withoutWeeklyWinnerVirtual();
        if (!Schema::hasColumn($query->getModel()->getTable(), 'is_smoke_account')) {
            return $query;
        }

        return $query->where(function ($query) {
            $query->whereNull('is_smoke_account')->orWhere('is_smoke_account', false);
        });
    }

    public function isOperatorSmoke(): bool
    {
        return (bool) $this->is_smoke_account;
    }

    public static function createSmokeAccount(array $attributes): self
    {
        if (!Schema::hasColumn('cms_users', 'is_smoke_account')) {
            throw new \RuntimeException('Smoke account marker migration is required before creating a smoke user.');
        }

        $user = new self();
        $user->fill($attributes);
        $user->forceFill(['is_smoke_account' => true]);
        $user->save();

        return $user;
    }

}
