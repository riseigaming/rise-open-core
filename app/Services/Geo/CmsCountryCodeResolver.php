<?php

namespace App\Services\Geo;

use App\Models\CmsCountry;
use Illuminate\Support\Facades\Schema;

class CmsCountryCodeResolver
{
    public function resolveCountryId(?string $countryCode): ?int
    {
        $countryCode = strtoupper(trim((string) $countryCode));
        if ($countryCode === '' || !Schema::hasTable('cms_countries')) {
            return null;
        }

        $columns = array_values(array_filter(
            ['iso2', 'iso3', 'iso_code_2', 'iso_code_3', 'code'],
            fn (string $column): bool => Schema::hasColumn('cms_countries', $column)
        ));
        if ($columns === []) {
            return null;
        }

        $country = CmsCountry::query()
            ->where(function ($query) use ($columns, $countryCode): void {
                foreach ($columns as $index => $column) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $query->{$method}('UPPER(' . $column . ') = ?', [$countryCode]);
                }
            })
            ->first(['id']);

        return $country instanceof CmsCountry ? (int) $country->id : null;
    }
}
