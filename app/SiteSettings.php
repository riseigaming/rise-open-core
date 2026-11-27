<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Throwable;

class SiteSettings extends Model
{
    protected $table = 'site_settings';
    //
    protected $fillable = [
        'key', 'value'
    ];

    public static function getSetting($key){
       $settings = self::cachedSettings();

       return $settings->firstWhere('key', $key)?->value ?? '';
    }

    private static function cachedSettings()
    {
        $settings = Session::get('settings');

        if (is_iterable($settings)) {
            return collect($settings);
        }

        try {
            $settings = Schema::hasTable('site_settings')
                ? self::query()->get()
                : collect();
        } catch (Throwable $throwable) {
            $settings = collect();
        }

        Session::put('settings', $settings);

        return collect($settings);
    }
}
