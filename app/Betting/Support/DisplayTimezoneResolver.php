<?php

namespace App\Betting\Support;

use App\Models\CmsUser;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use DateTimeZone;

class DisplayTimezoneResolver
{
    public const COOKIE_NAME = 'cms_display_timezone';
    public const SESSION_KEY = 'cms_display_timezone';
    public const HEADER_NAME = 'X-Display-Timezone';

    public function resolve(?Request $request = null, ?CmsUser $user = null): string
    {
        $candidates = [];

        if ($user) {
            $candidates[] = data_get($user, 'timezone');
        }

        if ($request) {
            if ($request->hasSession()) {
                $candidates[] = $request->session()->get(self::SESSION_KEY);
            }
            $candidates[] = $request->cookie(self::COOKIE_NAME);
            $candidates[] = $request->header(self::HEADER_NAME);
            $candidates[] = $request->header('X-Client-Timezone');
            $candidates[] = $request->query('timezone');
        }

        $candidates[] = config('app.timezone');
        $candidates[] = 'UTC';

        foreach ($candidates as $candidate) {
            $resolved = $this->normalize($candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return 'UTC';
    }

    public function normalize(mixed $candidate): ?string
    {
        $value = trim((string) $candidate);
        if ($value === '') {
            return null;
        }

        try {
            new DateTimeZone($value);
            return $value;
        } catch (\Throwable) {
            return null;
        }
    }

    public function canonicalStartsAt(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc();
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $trimmed = trim($value);
            if (preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/', $trimmed) === 1) {
                return Carbon::parse($trimmed)->utc();
            }

            return Carbon::parse($trimmed, 'UTC')->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /** One timezone-aware short date for Community cards; source instant is never rewritten. */
    public function formatCommunityStartsAt(mixed $value, ?Request $request = null, ?CmsUser $user = null): ?string
    {
        $instant = $this->canonicalStartsAt($value);

        return $instant?->timezone($this->resolve($request, $user))->format('d.m H:i');
    }

    public function formatStartsAt(mixed $value, string $timezone): array
    {
        $startsAt = $this->canonicalStartsAt($value);
        if (!$startsAt) {
            return [
                'timestamp' => 0,
                'time' => '--:--',
                'date' => '--.--.----',
                'iso' => null,
            ];
        }

        $localized = $startsAt->copy()->timezone($timezone);

        return [
            'timestamp' => $startsAt->timestamp,
            'time' => $localized->format('H:i'),
            'date' => $localized->format('d.m.Y'),
            'iso' => $startsAt->toIso8601String(),
        ];
    }
}
