<?php

namespace App\Services\Identity;

use App\Models\CmsCountry;
use App\Models\CmsUser;
use Illuminate\Support\Facades\Schema;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneIdentityService
{
    public function attributesForUser(CmsUser $user, mixed $value): array
    {
        return $this->attributes($value, (int) $user->country_id, $user->exists ? (int) $user->id : null);
    }

    public function attributes(mixed $value, int $countryId, ?int $exceptUserId = null): array
    {
        $phone = trim((string) $value);
        if ($phone === '') {
            return ['phone' => null, 'phone_normalized' => null];
        }

        if (!Schema::hasColumn('cms_users', 'phone_normalized')) {
            throw new \DomainException('Phone identity storage is unavailable.');
        }

        $normalized = $this->normalize($phone, $countryId);
        $query = CmsUser::withTrashed()->where('phone_normalized', $normalized);
        if ($exceptUserId !== null) {
            $query->whereKeyNot($exceptUserId);
        }
        if ($query->exists()) {
            throw new \DomainException('This phone number is already in use.');
        }

        return ['phone' => $phone, 'phone_normalized' => $normalized];
    }

    public function normalize(string $value, int $countryId): string
    {
        $value = trim($value);
        $region = str_starts_with($value, '+') ? null : $this->countryIso2($countryId);
        if (!str_starts_with($value, '+') && $region === null) {
            throw new \DomainException('A registered country is required for this phone number.');
        }

        try {
            $number = PhoneNumberUtil::getInstance()->parse($value, $region);
        } catch (NumberParseException) {
            throw new \DomainException('Enter a valid phone number.');
        }

        if (!PhoneNumberUtil::getInstance()->isValidNumber($number)) {
            throw new \DomainException('Enter a valid phone number.');
        }

        return PhoneNumberUtil::getInstance()->format($number, PhoneNumberFormat::E164);
    }

    private function countryIso2(int $countryId): ?string
    {
        if ($countryId <= 0 || !Schema::hasTable('cms_countries')) {
            return null;
        }
        $column = collect(['iso2', 'iso_code_2', 'code'])
            ->first(fn (string $candidate) => Schema::hasColumn('cms_countries', $candidate));
        if (!$column) {
            return null;
        }

        $value = strtoupper(trim((string) CmsCountry::query()->whereKey($countryId)->value($column)));
        return preg_match('/^[A-Z]{2}$/D', $value) === 1 ? $value : null;
    }
}
