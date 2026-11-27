<?php

namespace App\Services\GrowthAnalytics;

use App\Models\CmsUser;
use Illuminate\Database\Eloquent\Builder;

/** Existing customer-root read query; excludes child and analytics test identities. */
final class GrowthCustomerIdentity
{

    public function roots(): Builder
    {
        return CmsUser::query()->where('role', 'customer')
            ->where(fn ($q) => $q->whereNull('parent_id')->orWhere('parent_id', 0))
            ->withoutAnalyticsTestAccounts();
    }

}
