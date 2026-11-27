<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_languages')) return;

        $all = DB::table('cms_languages')->orderBy('id')->get();
        if ($all->isNotEmpty()) {
            $fallback = strtolower((string) config('app.fallback_locale', config('app.locale', 'en')));
            $active = $all->filter(fn ($language) => (bool) $language->status);
            if ($active->isEmpty()) {
                $chosen = $all->first(fn ($language) => strtolower((string) $language->code) === $fallback) ?: $all->first();
                DB::table('cms_languages')->where('id', $chosen->id)->update(['status' => 1]);
                $active = DB::table('cms_languages')->where('status', 1)->orderBy('id')->get();
            }
            $existingDefaults = $active->filter(fn ($language) => (bool) $language->is_default);
            $chosen = $existingDefaults->count() === 1
                ? $existingDefaults->first()
                : ($active->first(fn ($language) => strtolower((string) $language->code) === $fallback) ?: $active->first());
            DB::table('cms_languages')->update(['is_default' => 0]);
            DB::table('cms_languages')->where('id', $chosen->id)->update(['is_default' => 1]);
        }

        // This release targets MySQL's generated-column uniqueness semantics.
        // SQLite test environments still execute the deterministic data repair.
        if (DB::getDriverName() === 'mysql' && !Schema::hasColumn('cms_languages', 'active_default_guard')) {
            Schema::table('cms_languages', function (Blueprint $table): void {
                // MySQL permits many NULLs in a unique key. A generated guard makes the
                // invariant hold even for update paths that bypass Eloquent observers.
                $table->unsignedTinyInteger('active_default_guard')
                    ->nullable()
                    ->storedAs('CASE WHEN `status` = 1 AND `is_default` = 1 THEN 1 ELSE NULL END')
                    ->after('is_default');
                $table->unique('active_default_guard', 'cms_languages_one_active_default_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cms_languages') && Schema::hasColumn('cms_languages', 'active_default_guard')) {
            Schema::table('cms_languages', function (Blueprint $table): void {
                $table->dropUnique('cms_languages_one_active_default_unique');
                $table->dropColumn('active_default_guard');
            });
        }
    }
};
