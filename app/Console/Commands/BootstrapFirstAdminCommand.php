<?php

namespace App\Console\Commands;

use App\Models\CmsUser;
use App\Models\CmsUserType;
use App\Services\Security\CmsPasswordIntegrityService;
use App\Services\Security\RegistrationInputPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class BootstrapFirstAdminCommand extends Command
{
    protected $signature = 'app:admin-bootstrap
        {--name= : First administrator display name}
        {--username= : First administrator username}
        {--email= : First administrator email}
        {--country-code= : Explicit installation country ISO2 code}
        {--country-name= : Explicit installation country display name}
        {--currency-code= : Supported installation fiat code}
        {--password-stdin : Read one password line from standard input instead of prompting}';

    protected $description = 'Create the first CMS administrator and neutral baseline on an empty candidate.';

    public function handle(): int
    {
        if (trim((string) config('app.key')) === '') {
            $this->error('APP_KEY is missing. Run key:generate on the new installation first.');
            return self::FAILURE;
        }
        if (!extension_loaded('intl')) {
            $this->error('The PHP intl extension is required to validate the installation country.');
            return self::FAILURE;
        }
        foreach (['cms_users', 'cms_user_types', 'cms_languages', 'cms_countries', 'cms_currencies', 'cms_site_settings', 'cms_site_setting_translations', 'cms_sensitive_mutation_events'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->error('Required table missing: '.$table.'. Run the selected migrations first.');
                return self::FAILURE;
            }
        }
        // Include soft-deleted identities: bootstrap is never account recovery or promotion.
        if (DB::table('cms_users')->exists()) {
            $this->error('Bootstrap refused: CMS identities already exist.');
            return self::FAILURE;
        }
        $name = $this->inputValue('name', 'Administrator name');
        $username = $this->inputValue('username', 'Administrator username');
        $email = $this->inputValue('email', 'Administrator email');
        $countryCode = strtoupper($this->inputValue('country-code', 'Installation country ISO2 code'));
        $countryName = $this->inputValue('country-name', 'Installation country name');
        $currencyCode = strtoupper($this->inputValue('currency-code', 'Installation fiat currency code (TRY, USD or EUR)'));
        [$firstName, $lastName] = array_pad(preg_split('/\s+/u', $name, 2) ?: [], 2, '');
        $regionBundle = \ResourceBundle::create('en', 'ICUDATA-region');
        $regionNames = $regionBundle instanceof \ResourceBundle ? $regionBundle->get('Countries') : null;
        $countryKnown = preg_match('/^[A-Z]{2}$/', $countryCode) && $regionNames instanceof \ResourceBundle && $regionNames->get($countryCode)
            && !in_array($countryCode, ['ZZ', 'EU', 'UN', 'QO', 'XA', 'XB'], true);
        $currencyMetadata = [
            'TRY' => ['name' => 'Turkish Lira', 'symbol' => '₺'],
            'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€'],
        ];
        if ($name === '' || mb_strlen($firstName) > 50 || mb_strlen($lastName) > 50
            || str_contains($username, '@') || RegistrationInputPolicy::identifierProblem($username) !== null
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100
            || !$countryKnown || $countryName === '' || mb_strlen($countryName) > 255
            || !isset($currencyMetadata[$currencyCode]) || !in_array($currencyCode, config('product_wallets.fiat_assets', []), true)) {
            $this->error('Provide valid CMS identity, explicit real country ISO2/name and a configured supported fiat code.');
            return self::FAILURE;
        }
        if ($this->option('password-stdin')) {
            $password = rtrim((string) fgets(STDIN), "\r\n");
        } elseif ($this->input->isInteractive()) {
            $password = (string) $this->secret('Password (minimum 12 characters)');
            $confirmation = (string) $this->secret('Confirm password');
            if (!hash_equals($password, $confirmation)) {
                $this->error('Passwords did not match.');
                return self::FAILURE;
            }
            unset($confirmation);
        } else {
            $this->error('Use an interactive prompt or --password-stdin.');
            return self::FAILURE;
        }
        if (mb_strlen($password) < 12 || RegistrationInputPolicy::passwordProblem($password) !== null) {
            $this->error('Password must contain at least 12 characters and meet the retained password policy.');
            return self::FAILURE;
        }
        $passwords = app(CmsPasswordIntegrityService::class);
        $hash = $passwords->hashAndValidatePlainPassword($password, 'app:admin-bootstrap');
        unset($password);
        try {
            $created = DB::transaction(function () use ($firstName, $lastName, $username, $email, $countryCode, $countryName, $currencyCode, $currencyMetadata, $hash, $passwords): bool {
                $settings = DB::table('cms_site_settings')->lockForUpdate()->get();
                if ($settings->count() > 1) throw new RuntimeException('Expected a single neutral base setting.');
                if (DB::table('cms_users')->exists()
                    || DB::table('cms_users')->where('username_normalized', strtolower($username))->orWhere('email_normalized', strtolower($email))->exists()) return false;
                $now = now();
                foreach (['en' => 'English', 'tr' => 'Türkçe', 'es' => 'Español'] as $code => $languageName) {
                    if (!DB::table('cms_languages')->where('code', $code)->exists()) {
                        DB::table('cms_languages')->insert(['code' => $code, 'name' => $languageName, 'status' => 1, 'is_default' => 0, 'created_at' => $now, 'updated_at' => $now]);
                    }
                }
                $languages = DB::table('cms_languages')->where('status', 1)->get();
                $defaults = $languages->where('is_default', 1)->pluck('code')->values()->all();
                if ($languages->pluck('code')->sort()->values()->all() !== ['en', 'es', 'tr']
                    || !in_array($defaults, [[], ['en'], ['tr']], true)) {
                    throw new RuntimeException('Existing language baseline does not match the neutral B1 contract.');
                }
                $setting = $settings->first();
                if (!$setting) {
                    $settingId = DB::table('cms_site_settings')->insertGetId([
                        'active_theme' => 'global', 'default_lang' => 'en', 'register_status' => 0,
                        'account_mail_status' => 'active', 'chat_status' => 'passive',
                        'balance_status' => 'passive', 'splash_status' => 'passive',
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                } else {
                    if ($setting->active_theme !== 'global' || (int) $setting->register_status !== 0 || !in_array($setting->default_lang, ['en', 'tr'], true)
                        || $setting->account_mail_status !== 'active') throw new RuntimeException('Existing base setting is not the neutral B1 baseline.');
                    $settingId = $setting->id;
                }
                foreach ($languages as $language) {
                    $translation = DB::table('cms_site_setting_translations')->where('cms_setting_id', $settingId)->where('cms_language_id', $language->id)->first();
                    if (!$translation) {
                        DB::table('cms_site_setting_translations')->insert([
                            'cms_setting_id' => $settingId, 'cms_language_id' => $language->id,
                            'site_title' => 'Rise Open Core', 'site_description' => '',
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                    } elseif (!in_array($translation->site_title, ['New CMS', 'Rise Open Core'], true)) {
                        throw new RuntimeException('Existing title is not the neutral B1 baseline.');
                    }
                }
                // The shipped migrations predate the English setup default. Promote English
                // only inside this empty-install transaction and preserve locale IDs.
                if ($defaults !== ['en']) {
                    DB::table('cms_languages')->where('is_default', 1)->update(['is_default' => 0]);
                    DB::table('cms_languages')->where('code', 'en')->update(['is_default' => 1]);
                }
                if ($setting && $setting->default_lang !== 'en') {
                    DB::table('cms_site_settings')->where('id', $settingId)->update(['default_lang' => 'en']);
                }
                $type = CmsUserType::ensureCoreCatalog();
                $country = DB::table('cms_countries')->where('iso2', $countryCode)->first();
                if ($country && $country->name !== $countryName) throw new RuntimeException('Existing country metadata differs from the explicit installation input.');
                $countryId = $country?->id ?? DB::table('cms_countries')->insertGetId(['iso2' => $countryCode, 'name' => $countryName, 'created_at' => $now, 'updated_at' => $now]);
                $currency = DB::table('cms_currencies')->where('code', $currencyCode)->first();
                if ($currency && ($currency->status !== 'active' || $currency->deleted_at !== null)) throw new RuntimeException('Selected fiat metadata is not active.');
                if (!$currency) {
                    DB::table('cms_currencies')->insert(array_merge($currencyMetadata[$currencyCode], [
                        'code' => $currencyCode, 'rate' => 1, 'is_default' => 1, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
                    ]));
                }
                $passwords->runWithPasswordMutationSource('app:admin-bootstrap', function () use ($firstName, $lastName, $username, $email, $countryId, $currencyCode, $type, $hash): void {
                    $user = new CmsUser([
                        'first_name' => $firstName, 'last_name' => $lastName, 'username' => $username, 'email' => $email,
                        'country_id' => $countryId, 'currency' => $currencyCode, 'language' => 'en', 'user_type_id' => $type->getKey(),
                        'role' => 'admin', 'status' => 'normal', 'registration_source' => 'app:admin-bootstrap',
                    ]);
                    // Password is deliberately absent from the model's mass-assignment surface.
                    $user->forceFill(['password' => $hash, 'password_raw' => null]);
                    $user->save();
                });
                return true;
            });
        } catch (Throwable $exception) {
            $this->error('Bootstrap failed and transaction rolled back ('.class_basename($exception).').');
            return self::FAILURE;
        }
        if (!$created) {
            $this->error('Bootstrap refused: a CMS identity already exists.');
            return self::FAILURE;
        }
        $this->info('First CMS administrator and neutral baseline created. Public maintenance remains unchanged.');
        return self::SUCCESS;
    }

    private function inputValue(string $option, string $prompt): string
    {
        return trim((string) ($this->option($option) ?: ($this->input->isInteractive() ? $this->ask($prompt) : '')));
    }
}
