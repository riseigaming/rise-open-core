<?php

namespace App\Services\CmsStaff;

use App\Models\CmsStaffPermission;
use App\Models\CmsStaffProfile;
use App\Models\CmsUser;
use Illuminate\Support\Facades\Schema;

class CmsStaffPermissionService
{
    public const ACCESS = 'cms.staff.access';
    public const SUPPORT_VIEW = 'support.conversations.view';
    public const SUPPORT_REPLY = 'support.conversations.reply';
    public const SUPPORT_BONUS_GRANT = 'support.bonus.grant';
    public const SUPPORT_EXTERNAL_GAMES_MANAGE = 'support.external_games.manage';
    public const DEPOSITS_VIEW = 'deposits.requests.view';
    public const DEPOSITS_APPROVE = 'deposits.requests.approve';
    public const DEPOSITS_REJECT = 'deposits.requests.reject';
    public const FINANCE_MANUAL_OVERRIDE = 'finance.manual.override';
    public const POKER_SHARED_VIEW = 'poker.shared.view';
    public const POKER_SHARED_MANAGE = 'poker.shared.manage';
    public const POKER_RESERVE_MANAGE = 'poker.reserve.manage';

    public const CATALOG_PROVIDERS = 'catalog.providers.manage';
    public const CATALOG_GAMES = 'catalog.games.manage';
    public const CATALOG_CURATION = 'catalog.curation.manage';
    public const CATALOG_PERMISSIONS = [self::ACCESS, self::CATALOG_PROVIDERS, self::CATALOG_GAMES, self::CATALOG_CURATION];

    public const KNOWN_PERMISSIONS = [
        self::CATALOG_PROVIDERS, self::CATALOG_GAMES, self::CATALOG_CURATION,
        self::ACCESS,
        self::SUPPORT_VIEW,
        self::SUPPORT_REPLY,
        self::SUPPORT_BONUS_GRANT,
        self::SUPPORT_EXTERNAL_GAMES_MANAGE,
        self::DEPOSITS_VIEW,
        self::DEPOSITS_APPROVE,
        self::DEPOSITS_REJECT,
        self::FINANCE_MANUAL_OVERRIDE,
        self::POKER_SHARED_VIEW,
        self::POKER_SHARED_MANAGE,
        self::POKER_RESERVE_MANAGE,
    ];

    public function isAdmin(?CmsUser $user): bool
    {
        return $user instanceof CmsUser && (string) $user->role === 'admin';
    }

    public function has(?CmsUser $user, string $permissionKey): bool
    {
        $permissionKey = trim($permissionKey);

        if (!$user instanceof CmsUser || $permissionKey === '') {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        if (!$this->staffTablesAvailable() || !$this->hasActiveProfile($user)) {
            return false;
        }

        if ($permissionKey !== self::ACCESS && !$this->permissionEnabled($user, self::ACCESS)) {
            return false;
        }

        return $this->permissionEnabled($user, $permissionKey);
    }

    public function allowedPermissionKeys(?CmsUser $user): array
    {
        if (!$user instanceof CmsUser) {
            return [];
        }

        if ($this->isAdmin($user)) {
            return self::KNOWN_PERMISSIONS;
        }

        if (!$this->has($user, self::ACCESS)) {
            return [];
        }

        return CmsStaffPermission::query()
            ->where('cms_user_id', $user->id)
            ->where('enabled', true)
            ->pluck('permission_key')
            ->filter(fn ($permissionKey) => in_array($permissionKey, self::KNOWN_PERMISSIONS, true))
            ->values()
            ->all();
    }

    public function canManagePokerReserve(?CmsUser $user): bool
    {
        return $this->has($user, self::POKER_SHARED_MANAGE)
            && $this->has($user, self::POKER_RESERVE_MANAGE);
    }

    public function hasActiveProfile(CmsUser $user): bool
    {
        if (!Schema::hasTable('cms_staff_profiles')) {
            return false;
        }

        return CmsStaffProfile::query()
            ->where('cms_user_id', $user->id)
            ->where('active', true)
            ->exists();
    }

    private function permissionEnabled(CmsUser $user, string $permissionKey): bool
    {
        if (!Schema::hasTable('cms_staff_permissions')) {
            return false;
        }

        return CmsStaffPermission::query()
            ->where('cms_user_id', $user->id)
            ->where('permission_key', $permissionKey)
            ->where('enabled', true)
            ->exists();
    }

    private function staffTablesAvailable(): bool
    {
        return Schema::hasTable('cms_staff_profiles')
            && Schema::hasTable('cms_staff_permissions');
    }
}
