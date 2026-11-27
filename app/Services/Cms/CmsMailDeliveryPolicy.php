<?php

namespace App\Services\Cms;

use App\Models\CmsSiteSetting;
use Illuminate\Support\Facades\Schema;

class CmsMailDeliveryPolicy
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PASSIVE = 'passive';

    public function accountMailEnabled(): bool
    {
        if (! (bool) config('mail.features.account_delivery', true)) {
            return false;
        }

        try {
            if (
                ! Schema::hasTable('cms_site_settings')
                || ! Schema::hasColumn('cms_site_settings', 'account_mail_status')
            ) {
                return true;
            }

            $status = CmsSiteSetting::query()->value('account_mail_status');

            return $status !== self::STATUS_PASSIVE;
        } catch (\Throwable $throwable) {
            report($throwable);

            return true;
        }
    }

    public function allows(string $feature): bool
    {
        return $this->accountMailEnabled()
            && (bool) config('mail.features.'.$feature, false);
    }
}
