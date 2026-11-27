<?php

// Executable positive registry compiled from B1-INSTALL-CONTRACT-001.
// This is a specification, never an acceptance result.
$contract = [
    'id' => 'B1-INSTALL-CONTRACT-001',
    'php_minimum' => '8.4',
    'required_extensions' => ['dom', 'exif', 'fileinfo', 'gd', 'iconv', 'intl', 'json', 'libxml', 'mbstring', 'openssl', 'pcre', 'pdo_mysql'],
    'required_tables' => ['cms_cities', 'cms_countries', 'cms_currencies', 'cms_languages', 'cms_menus', 'cms_promotion_translations', 'cms_promotions', 'cms_sensitive_mutation_events', 'cms_session_history', 'cms_sessions', 'cms_site_setting_translations', 'cms_site_settings', 'cms_slider_translations', 'cms_sliders', 'cms_staff_permissions', 'cms_staff_profiles', 'cms_states', 'cms_theme_settings', 'cms_user_logs', 'cms_user_types', 'cms_users', 'failed_jobs', 'jobs_table', 'password_resets', 'site_settings', 'migrations'],
    'required_columns' => [
        'cms_cities' => ['country_id', 'created_at', 'id', 'name', 'state_id', 'updated_at'],
        'cms_countries' => ['created_at', 'id', 'iso2', 'name', 'updated_at'],
        'cms_currencies' => ['code', 'created_at', 'deleted_at', 'id', 'is_default', 'name', 'rate', 'status', 'symbol', 'updated_at'],
        'cms_languages' => ['active_default_guard', 'code', 'created_at', 'id', 'is_default', 'name', 'status', 'updated_at'],
        'cms_menus' => ['created_at', 'deleted_at', 'id', 'json', 'language_id', 'name', 'updated_at'],
        'cms_promotion_translations' => ['cms_language_id', 'cms_promotion_id', 'created_at', 'deleted_at', 'description', 'detail_title', 'id', 'listing_title', 'redirect_button_text', 'short_description', 'updated_at'],
        'cms_promotions' => ['created_at', 'deleted_at', 'detail_image', 'end_date', 'id', 'is_permanent', 'rank', 'redirect_url', 'show_in_top_line', 'show_on_homepage_top', 'status', 'thumbnail', 'updated_at'],
        'cms_sensitive_mutation_events' => ['actor_id', 'actor_type', 'command_name', 'created_at', 'event_type', 'id', 'ip_address', 'metadata', 'request_path', 'route_name', 'source', 'subject_id', 'subject_type', 'user_agent'],
        'cms_session_history' => ['browser_name', 'cms_user_id', 'ended_at', 'ended_by', 'id', 'ip_address', 'last_activity_at', 'os_name', 'reason', 'session_record_id', 'started_at', 'user_agent'],
        'cms_sessions' => ['browser_name', 'cms_user_id', 'created_at', 'expires_at', 'id', 'ip_address', 'is_persistent', 'os_name', 'remember_token', 'updated_at', 'user_agent'],
        'cms_site_setting_translations' => ['cms_language_id', 'cms_setting_id', 'created_at', 'first_modal_image_lg', 'first_modal_image_sm', 'first_modal_text', 'id', 'site_description', 'site_title', 'updated_at'],
        'cms_site_settings' => ['account_mail_status', 'active_theme', 'balance_status', 'chat_status', 'created_at', 'default_lang', 'homepage_hero_auth_mode', 'id', 'logo', 'menu_version', 'mobile_nav_type', 'register_status', 'socket_port', 'socket_url', 'splash_status', 'topbar_fullscreen_status', 'topbar_logout_status', 'updated_at'],
        'cms_slider_translations' => ['cms_language_id', 'cms_slider_id', 'created_at', 'deleted_at', 'description', 'id', 'image_name', 'image_path', 'title', 'updated_at', 'url'],
        'cms_sliders' => ['created_at', 'deleted_at', 'id', 'image_name', 'image_path', 'placement', 'rank', 'status', 'theme_key', 'updated_at', 'use_same_image'],
        'cms_staff_permissions' => ['cms_user_id', 'created_at', 'enabled', 'id', 'permission_key', 'updated_at'],
        'cms_staff_profiles' => ['active', 'cms_user_id', 'created_at', 'department', 'id', 'title', 'updated_at'],
        'cms_states' => ['country_id', 'created_at', 'id', 'name', 'updated_at'],
        'cms_theme_settings' => ['created_at', 'id', 'logo', 'theme_key', 'translations', 'updated_at'],
        'cms_user_logs' => ['cms_operator_id', 'cms_user_id', 'created_at', 'description', 'id', 'log_type', 'title', 'updated_at'],
        'cms_user_types' => ['created_at', 'deleted_at', 'description', 'id', 'name', 'updated_at'],
        'cms_users' => ['activity_point', 'balance', 'birth_city', 'birth_date', 'city_id', 'country_id', 'created_at', 'created_by_admin', 'currency', 'deleted_at', 'email', 'email_normalized', 'email_verified_at', 'email_verify_hash', 'external_id', 'first_name', 'gender', 'id', 'is_chat_blocked', 'is_private', 'is_smoke_account', 'language', 'last_name', 'last_seen', 'live_bet_limit', 'marketing_email_opt_in', 'marketing_email_opted_out_at', 'parent_id', 'partner_id', 'password', 'password_raw', 'personal_id', 'phone', 'phone_normalized', 'player_id', 'promotion_code_id', 'promotion_link_id', 'promotion_source_id', 'registration_source', 'remember_token', 'role', 'slot_bet_limit', 'state_id', 'status', 'transactional_email_opt_in', 'transactional_email_opted_out_at', 'updated_at', 'user_color', 'user_type_id', 'username', 'username_normalized'],
        'failed_jobs' => ['connection', 'exception', 'failed_at', 'id', 'payload', 'queue'],
        'jobs_table' => ['attempts', 'available_at', 'created_at', 'id', 'payload', 'queue', 'reserved_at'],
        'password_resets' => ['created_at', 'email', 'token'],
        'site_settings' => ['created_at', 'id', 'key', 'updated_at', 'value'],
    ],
    'migration_names' => ['2014_10_12_100000_create_password_resets_table', '2019_08_19_000000_create_failed_jobs_table', '2022_02_13_055717_site_settings_migration', '2022_08_23_013402_create_jobs_table', '2023_06_02_171346_create_cms_user_types_table', '2023_06_03_155852_bootstrap_cms_location_lookup_tables', '2023_06_03_155853_create_cms_users_table', '2023_06_04_201856_create_cms_languages_table', '2023_06_04_210633_create_cms_sliders_table', '2023_06_04_211338_create_cms_slider_translations_table', '2023_06_04_231119_create_cms_menus_table', '2023_06_05_214958_create_cms_user_logs_table', '2023_07_29_132255_create_cms_site_settings_table', '2023_07_29_132541_create_cms_site_setting_translations_table', '2023_09_28_010150_create_cms_currencies_table', '2023_09_28_032625_create_cms_promotions_table', '2023_09_28_032643_create_cms_promotion_translations_table', '2023_10_01_054831_create_cms_sessions_table', '2026_03_21_000351_align_cms_locations_fresh_install_columns', '2026_03_21_000353_bootstrap_generic_cms_site_setting', '2026_03_30_120100_align_cms_site_settings_for_local_surface', '2026_03_30_120300_align_cms_users_front_surface_fields', '2026_04_02_170500_expand_site_settings_value_for_betting_sync_controls', '2026_04_07_140000_add_show_in_top_line_to_cms_promotions_table', '2026_04_18_000000_add_show_on_homepage_top_to_cms_promotions_table', '2026_05_14_000002_add_placement_to_cms_sliders_table', '2026_05_14_000003_create_cms_sensitive_mutation_events_table', '2026_05_16_000001_add_topbar_action_visibility_to_cms_site_settings_table', '2026_06_01_000100_add_normalized_identity_unique_indexes_to_cms_users', '2026_07_22_000100_add_homepage_hero_auth_mode_to_cms_site_settings_table', '2026_07_29_000100_add_email_preferences_to_cms_users', '2026_07_29_000400_add_mobile_nav_type_to_cms_site_settings_table', '2026_07_30_000500_create_cms_staff_access_tables', '2026_08_25_000100_create_cms_theme_settings_table', '2026_08_28_000100_add_theme_key_to_cms_sliders_table', '2026_09_12_234000_add_account_mail_status_to_cms_site_settings_table', '2026_09_21_000200_add_phone_normalized_identity_to_cms_users', '2026_09_21_000300_repair_cms_language_default_invariant', '2026_09_21_000400_null_legacy_cms_user_password_raw', '2026_09_23_010000_add_cms_session_management', '2026_10_01_180000_add_is_smoke_account_to_cms_users'],
    'routes' => [
        [
            'id' => 'B1-HTTP-001',
            'method' => 'GET',
            'uri' => '/',
            'name' => 'cms.front.home',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'cmsLanguage', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\HomeController@index',
        ],
        [
            'id' => 'B1-HTTP-002',
            'method' => 'GET',
            'uri' => '/login-front',
            'name' => 'cms.front.login',
            'middleware' => ['web', 'EnforceSiteCountryRestriction', 'cmsSessionCheck', 'cmsLanguage', 'PublicFrontendMaintenance'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\AuthController@loginFront',
        ],
        [
            'id' => 'B1-HTTP-003',
            'method' => 'GET',
            'uri' => '/register-front',
            'name' => 'cms.front.register',
            'middleware' => ['web', 'EnforceSiteCountryRestriction', 'cmsSessionCheck', 'cmsLanguage', 'PublicFrontendMaintenance'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\AuthController@registerFront',
        ],
        [
            'id' => 'B1-HTTP-004',
            'method' => 'GET',
            'uri' => '/logout-front',
            'name' => 'cms.front.logout',
            'middleware' => ['web', 'EnforceSiteCountryRestriction', 'cmsSessionCheck', 'cmsLanguage', 'PublicFrontendMaintenance'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\AuthController@logoutFront',
        ],
        [
            'id' => 'B1-HTTP-005',
            'method' => 'GET',
            'uri' => '/verify-email',
            'name' => 'cms.front.verifyEmail',
            'middleware' => ['web', 'EnforceSiteCountryRestriction', 'cmsSessionCheck', 'cmsLanguage', 'PublicFrontendMaintenance'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\AuthController@verifyEmail',
        ],
        [
            'id' => 'B1-HTTP-006',
            'method' => 'REDIRECT',
            'uri' => '/cms',
            'name' => null,
            'middleware' => ['web', 'EnforceSiteCountryRestriction'],
            'controller' => null,
        ],
        [
            'id' => 'B1-HTTP-007',
            'method' => 'GET',
            'uri' => '/cms/login',
            'name' => 'cms.login',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\AuthController@login',
        ],
        [
            'id' => 'B1-HTTP-008',
            'method' => 'GET',
            'uri' => '/cms/information',
            'name' => 'cms.information',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsLanguage'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\StaticPageController@information',
        ],
        [
            'id' => 'B1-HTTP-009',
            'method' => 'GET',
            'uri' => '/cms/page/{pageName}',
            'name' => 'cms.staticPage',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsLanguage'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\StaticPageController@getPage',
        ],
        [
            'id' => 'B1-HTTP-010',
            'method' => 'GET',
            'uri' => '/cms/logout',
            'name' => 'cms.logout',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\AuthController@logout',
        ],
        [
            'id' => 'B1-HTTP-011',
            'method' => 'POST',
            'uri' => '/cms/login',
            'name' => 'cms.loginPost',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsLanguage', 'throttle:10,1,cms-login:'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\AuthController@loginPost',
        ],
        [
            'id' => 'B1-HTTP-012',
            'method' => 'GET',
            'uri' => '/cms/register',
            'name' => 'cms.register',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\AuthController@register',
        ],
        [
            'id' => 'B1-HTTP-013',
            'method' => 'GET',
            'uri' => '/cms/register/availability',
            'name' => 'cms.registerAvailability',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsLanguage', 'throttle:registration-availability'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\AuthController@registerAvailability',
        ],
        [
            'id' => 'B1-HTTP-014',
            'method' => 'POST',
            'uri' => '/cms/register',
            'name' => 'cms.registerPost',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsLanguage', 'throttle:registration-submit'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\AuthController@registerPost',
        ],
        [
            'id' => 'B1-HTTP-015',
            'method' => 'GET',
            'uri' => '/cms/password/forgot',
            'name' => 'cms.password.request',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsLanguage'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\CmsPasswordResetController@requestForm',
        ],
        [
            'id' => 'B1-HTTP-016',
            'method' => 'POST',
            'uri' => '/cms/password/forgot',
            'name' => 'cms.password.email',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsLanguage', 'throttle:5,1'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\CmsPasswordResetController@sendLink',
        ],
        [
            'id' => 'B1-HTTP-017',
            'method' => 'GET',
            'uri' => '/cms/password/reset/{token}',
            'name' => 'cms.password.reset.form',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsLanguage'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\CmsPasswordResetController@resetForm',
        ],
        [
            'id' => 'B1-HTTP-018',
            'method' => 'POST',
            'uri' => '/cms/password/reset',
            'name' => 'cms.password.update',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsLanguage', 'throttle:10,1'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\CmsPasswordResetController@update',
        ],
        [
            'id' => 'B1-HTTP-019',
            'method' => 'GET',
            'uri' => '/cms/promotions',
            'name' => 'cms.promotions',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsLanguage'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\PromotionController@index',
        ],
        [
            'id' => 'B1-HTTP-020',
            'method' => 'GET',
            'uri' => '/cms/promotions/detail/{id}',
            'name' => 'cms.promotions.detail',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsLanguage'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\PromotionController@detail',
        ],
        [
            'id' => 'B1-HTTP-021',
            'method' => 'POST',
            'uri' => '/cms/promotion-detail',
            'name' => 'cms.promotions.getDetail',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsLanguage'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\PromotionController@getDetail',
        ],
        [
            'id' => 'B1-HTTP-024',
            'method' => 'GET',
            'uri' => '/cms/edit-password',
            'name' => 'cms.front.editPassword',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsLanguage'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\ProfileController@editPassword',
        ],
        [
            'id' => 'B1-HTTP-025',
            'method' => 'POST',
            'uri' => '/cms/resendVerifyEmail',
            'name' => 'cms.front.resendVerifyEmail',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsLanguage'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\ProfileController@resendVerifyEmail',
        ],
        [
            'id' => 'B1-HTTP-026',
            'method' => 'POST',
            'uri' => '/cms/update-profile',
            'name' => 'cms.front.updateProfile',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsLanguage'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\ProfileController@update',
        ],
        [
            'id' => 'B1-HTTP-027',
            'method' => 'GET',
            'uri' => '/cms/sessions',
            'name' => 'cms.front.sessions',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsLanguage'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\ProfileController@sessions',
        ],
        [
            'id' => 'B1-HTTP-028',
            'method' => 'POST',
            'uri' => '/cms/make-logout-session',
            'name' => 'cms.front.makeLogoutSession',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsLanguage'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\ProfileController@makeLogoutSession',
        ],
        [
            'id' => 'B1-HTTP-029',
            'method' => 'POST',
            'uri' => '/cms/logout-all-sessions',
            'name' => 'cms.front.logoutAllSessions',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsLanguage'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\ProfileController@logoutAllSessions',
        ],
        [
            'id' => 'B1-HTTP-030',
            'method' => 'POST',
            'uri' => '/cms/logout-other-sessions',
            'name' => 'cms.front.logoutOtherSessions',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsLanguage', 'throttle:10,1'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\ProfileController@logoutOtherSessions',
        ],
        [
            'id' => 'B1-HTTP-031',
            'method' => 'GET',
            'uri' => '/cms/admin',
            'name' => 'cms.admin.home',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\HomeController@index',
        ],
        [
            'id' => 'B1-HTTP-032',
            'method' => 'GET',
            'uri' => '/cms/admin/languages',
            'name' => 'cms.admin.languages.index',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\LanguageController@index',
        ],
        [
            'id' => 'B1-HTTP-033',
            'method' => 'GET',
            'uri' => '/cms/admin/languages/get-ajax-list',
            'name' => 'cms.admin.languages.getAjaxList',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\LanguageController@getAjaxList',
        ],
        [
            'id' => 'B1-HTTP-034',
            'method' => 'GET',
            'uri' => '/cms/admin/languages/create',
            'name' => 'cms.admin.languages.create',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\LanguageController@create',
        ],
        [
            'id' => 'B1-HTTP-035',
            'method' => 'POST',
            'uri' => '/cms/admin/languages/store',
            'name' => 'cms.admin.languages.store',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\LanguageController@store',
        ],
        [
            'id' => 'B1-HTTP-036',
            'method' => 'GET',
            'uri' => '/cms/admin/languages/edit/{id}',
            'name' => 'cms.admin.languages.edit',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\LanguageController@edit',
        ],
        [
            'id' => 'B1-HTTP-037',
            'method' => 'PUT',
            'uri' => '/cms/admin/languages/update/{id}',
            'name' => 'cms.admin.languages.update',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\LanguageController@update',
        ],
        [
            'id' => 'B1-HTTP-038',
            'method' => 'DELETE',
            'uri' => '/cms/admin/languages/delete/{id}',
            'name' => 'cms.admin.languages.delete',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\LanguageController@delete',
        ],
        [
            'id' => 'B1-HTTP-039',
            'method' => 'POST',
            'uri' => '/cms/admin/languages/bulkAction',
            'name' => 'cms.admin.languages.bulkAction',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\LanguageController@bulkAction',
        ],
        [
            'id' => 'B1-HTTP-040',
            'method' => 'POST',
            'uri' => '/cms/admin/languages/country-defaults',
            'name' => 'cms.admin.languages.countryDefaults.set',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\LanguageController@setCountryDefault',
        ],
        [
            'id' => 'B1-HTTP-041',
            'method' => 'DELETE',
            'uri' => '/cms/admin/languages/country-defaults/{countryId}',
            'name' => 'cms.admin.languages.countryDefaults.remove',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\LanguageController@removeCountryDefault',
        ],
        [
            'id' => 'B1-HTTP-042',
            'method' => 'GET',
            'uri' => '/cms/admin/sliders',
            'name' => 'cms.admin.sliders.index',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\SliderController@index',
        ],
        [
            'id' => 'B1-HTTP-043',
            'method' => 'GET',
            'uri' => '/cms/admin/sliders/get-ajax-list',
            'name' => 'cms.admin.sliders.getAjaxList',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\SliderController@getAjaxList',
        ],
        [
            'id' => 'B1-HTTP-044',
            'method' => 'GET',
            'uri' => '/cms/admin/sliders/create',
            'name' => 'cms.admin.sliders.create',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\SliderController@create',
        ],
        [
            'id' => 'B1-HTTP-045',
            'method' => 'POST',
            'uri' => '/cms/admin/sliders/store',
            'name' => 'cms.admin.sliders.store',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\SliderController@store',
        ],
        [
            'id' => 'B1-HTTP-046',
            'method' => 'GET',
            'uri' => '/cms/admin/sliders/archive',
            'name' => 'cms.admin.sliders.archive',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\SliderController@archive',
        ],
        [
            'id' => 'B1-HTTP-047',
            'method' => 'POST',
            'uri' => '/cms/admin/sliders/restore/{id}',
            'name' => 'cms.admin.sliders.restore',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\SliderController@restore',
        ],
        [
            'id' => 'B1-HTTP-048',
            'method' => 'GET',
            'uri' => '/cms/admin/sliders/edit/{id}',
            'name' => 'cms.admin.sliders.edit',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\SliderController@edit',
        ],
        [
            'id' => 'B1-HTTP-049',
            'method' => 'POST',
            'uri' => '/cms/admin/sliders/update',
            'name' => 'cms.admin.sliders.update',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\SliderController@update',
        ],
        [
            'id' => 'B1-HTTP-050',
            'method' => 'DELETE',
            'uri' => '/cms/admin/sliders/delete/{id}',
            'name' => 'cms.admin.sliders.delete',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\SliderController@delete',
        ],
        [
            'id' => 'B1-HTTP-051',
            'method' => 'POST',
            'uri' => '/cms/admin/sliders/bulkAction',
            'name' => 'cms.admin.sliders.bulkAction',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\SliderController@bulkAction',
        ],
        [
            'id' => 'B1-HTTP-052',
            'method' => 'GET',
            'uri' => '/cms/admin/promotions',
            'name' => 'cms.admin.promotions.index',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\PromotionController@index',
        ],
        [
            'id' => 'B1-HTTP-053',
            'method' => 'GET',
            'uri' => '/cms/admin/promotions/get-ajax-list',
            'name' => 'cms.admin.promotions.getAjaxList',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\PromotionController@getAjaxList',
        ],
        [
            'id' => 'B1-HTTP-054',
            'method' => 'GET',
            'uri' => '/cms/admin/promotions/order',
            'name' => 'cms.admin.promotions.order',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\PromotionController@order',
        ],
        [
            'id' => 'B1-HTTP-055',
            'method' => 'POST',
            'uri' => '/cms/admin/promotions/order',
            'name' => 'cms.admin.promotions.updateOrder',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\PromotionController@updateOrder',
        ],
        [
            'id' => 'B1-HTTP-056',
            'method' => 'GET',
            'uri' => '/cms/admin/promotions/create',
            'name' => 'cms.admin.promotions.create',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\PromotionController@create',
        ],
        [
            'id' => 'B1-HTTP-057',
            'method' => 'POST',
            'uri' => '/cms/admin/promotions/store',
            'name' => 'cms.admin.promotions.store',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\PromotionController@store',
        ],
        [
            'id' => 'B1-HTTP-058',
            'method' => 'GET',
            'uri' => '/cms/admin/promotions/edit/{id}',
            'name' => 'cms.admin.promotions.edit',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\PromotionController@edit',
        ],
        [
            'id' => 'B1-HTTP-059',
            'method' => 'POST',
            'uri' => '/cms/admin/promotions/update',
            'name' => 'cms.admin.promotions.update',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\PromotionController@update',
        ],
        [
            'id' => 'B1-HTTP-060',
            'method' => 'DELETE',
            'uri' => '/cms/admin/promotions/delete/{id}',
            'name' => 'cms.admin.promotions.delete',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\PromotionController@delete',
        ],
        [
            'id' => 'B1-HTTP-061',
            'method' => 'POST',
            'uri' => '/cms/admin/promotions/bulkAction',
            'name' => 'cms.admin.promotions.bulkAction',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\PromotionController@bulkAction',
        ],
        [
            'id' => 'B1-HTTP-062',
            'method' => 'GET',
            'uri' => '/cms/admin/settings/site-settings',
            'name' => 'cms.admin.settings.getSiteSettings',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\SettingController@siteSettings',
        ],
        [
            'id' => 'B1-HTTP-063',
            'method' => 'POST',
            'uri' => '/cms/admin/settings/updateSiteSettings',
            'name' => 'cms.admin.settings.updateSiteSettings',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin', 'cmsRecentAuth'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\SettingController@updateSiteSettings',
        ],
        [
            'id' => 'B1-HTTP-064',
            'method' => 'GET',
            'uri' => '/cms/admin/menus/edit',
            'name' => 'cms.admin.menus.edit',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\MenuController@edit',
        ],
        [
            'id' => 'B1-HTTP-065',
            'method' => 'POST',
            'uri' => '/cms/admin/menus/update',
            'name' => 'cms.admin.menus.update',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\MenuController@update',
        ],
        [
            'id' => 'B1-HTTP-066',
            'method' => 'GET',
            'uri' => '/cms/setLanguage/{languageCode}',
            'name' => 'cms.setLanguage',
            'middleware' => ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance'],
            'controller' => 'App\\Http\\Controllers\\Cms\\Front\\ProfileController@setLanguage',
        ],
    ],
    'views' => [
        [
            'path' => 'resources/views/cms/global/index.blade.php',
            'view_name' => 'cms.global.index',
        ],
        [
            'path' => 'resources/views/cms/global/home.blade.php',
            'view_name' => 'cms.global.home',
        ],
        [
            'path' => 'resources/views/cms/global/partials/layout/menu.blade.php',
            'view_name' => 'cms.global.partials.layout.menu',
        ],
        [
            'path' => 'resources/views/cms/global/partials/layout/utility-sheets.blade.php',
            'view_name' => 'cms.global.partials.layout.utility-sheets',
        ],
        [
            'path' => 'resources/views/cms/global/partials/layout/account-sheet.blade.php',
            'view_name' => 'cms.global.partials.layout.account-sheet',
        ],
        [
            'path' => 'resources/views/cms/global/partials/layout/footer.blade.php',
            'view_name' => 'cms.global.partials.layout.footer',
        ],
        [
            'path' => 'resources/views/cms/global/partials/layout/auth-sheet.blade.php',
            'view_name' => 'cms.global.partials.layout.auth-sheet',
        ],
        [
            'path' => 'resources/views/cms/global/partials/layout/hero-slider.blade.php',
            'view_name' => 'cms.global.partials.layout.hero-slider',
        ],
        [
            'path' => 'resources/views/cms/global/partials/vendor-runtime.blade.php',
            'view_name' => 'cms.global.partials.vendor-runtime',
        ],
        [
            'path' => 'resources/views/cms/global/partials/frontend-notices.blade.php',
            'view_name' => 'cms.global.partials.frontend-notices',
        ],
        [
            'path' => 'resources/views/cms/global/information.blade.php',
            'view_name' => 'cms.global.information',
        ],
        [
            'path' => 'resources/views/cms/global/content/legal-page.blade.php',
            'view_name' => 'cms.global.content.legal-page',
        ],
        [
            'path' => 'resources/views/cms/global/content/partials/legal-page-content.blade.php',
            'view_name' => 'cms.global.content.partials.legal-page-content',
        ],
        [
            'path' => 'resources/views/cms/global/content/terms-conditions.blade.php',
            'view_name' => 'cms.global.content.terms-conditions',
        ],
        [
            'path' => 'resources/views/cms/global/content/privacy-policy.blade.php',
            'view_name' => 'cms.global.content.privacy-policy',
        ],
        [
            'path' => 'resources/views/cms/global/content/cookie.blade.php',
            'view_name' => 'cms.global.content.cookie',
        ],
        [
            'path' => 'resources/views/cms/global/content/responsible-gaming.blade.php',
            'view_name' => 'cms.global.content.responsible-gaming',
        ],
        [
            'path' => 'resources/views/cms/global/content/betting-rules.blade.php',
            'view_name' => 'cms.global.content.betting-rules',
        ],
        [
            'path' => 'resources/views/cms/global/content/provably-fair.blade.php',
            'view_name' => 'cms.global.content.provably-fair',
        ],
        [
            'path' => 'resources/views/cms/global/promotions.blade.php',
            'view_name' => 'cms.global.promotions',
        ],
        [
            'path' => 'resources/views/cms/global/promotion-detail.blade.php',
            'view_name' => 'cms.global.promotion-detail',
        ],
        [
            'path' => 'resources/views/cms/global/partials/navbar-timed-promotion-modal.blade.php',
            'view_name' => 'cms.global.partials.navbar-timed-promotion-modal',
        ],
        [
            'path' => 'resources/views/cms/global/partials/promotion-modal-inner.blade.php',
            'view_name' => 'cms.global.partials.promotion-modal-inner',
        ],
        [
            'path' => 'resources/views/cms/global/partials/home/parity-vitrines.blade.php',
            'view_name' => 'cms.global.partials.home.parity-vitrines',
        ],
        [
            'path' => 'resources/views/cms/global/sessions.blade.php',
            'view_name' => 'cms.global.sessions',
        ],
        [
            'path' => 'resources/views/cms/global/auth/password-forgot.blade.php',
            'view_name' => 'cms.global.auth.password-forgot',
        ],
        [
            'path' => 'resources/views/cms/global/auth/password-reset.blade.php',
            'view_name' => 'cms.global.auth.password-reset',
        ],
        [
            'path' => 'resources/views/cms/global/auth/edit-password.blade.php',
            'view_name' => 'cms.global.auth.edit-password',
        ],
        [
            'path' => 'resources/views/cms/global/auth/form-behavior.blade.php',
            'view_name' => 'cms.global.auth.form-behavior',
        ],
        [
            'path' => 'resources/views/cms/global/auth/layout.blade.php',
            'view_name' => 'cms.global.auth.layout',
        ],
        [
            'path' => 'resources/views/cms/partials/registration-turnstile.blade.php',
            'view_name' => 'cms.partials.registration-turnstile',
        ],
        [
            'path' => 'resources/views/cms/partials/customer-placeholder-style.blade.php',
            'view_name' => 'cms.partials.customer-placeholder-style',
        ],
        [
            'path' => 'resources/views/cms/partials/desktop-form-autofocus.blade.php',
            'view_name' => 'cms.partials.desktop-form-autofocus',
        ],
        [
            'path' => 'resources/views/cms/partials/security/session-list.blade.php',
            'view_name' => 'cms.partials.security.session-list',
        ],
        [
            'path' => 'resources/views/cms/emails/email-validation.blade.php',
            'view_name' => 'cms.emails.email-validation',
        ],
        [
            'path' => 'resources/views/cms/admin/index.blade.php',
            'view_name' => 'cms.admin.index',
        ],
        [
            'path' => 'resources/views/cms/admin/languages/create.blade.php',
            'view_name' => 'cms.admin.languages.create',
        ],
        [
            'path' => 'resources/views/cms/admin/languages/edit.blade.php',
            'view_name' => 'cms.admin.languages.edit',
        ],
        [
            'path' => 'resources/views/cms/admin/promotions/index.blade.php',
            'view_name' => 'cms.admin.promotions.index',
        ],
        [
            'path' => 'resources/views/cms/admin/promotions/order.blade.php',
            'view_name' => 'cms.admin.promotions.order',
        ],
        [
            'path' => 'resources/views/cms/admin/sliders/archive.blade.php',
            'view_name' => 'cms.admin.sliders.archive',
        ],
        [
            'path' => 'resources/views/cms/admin/sliders/create.blade.php',
            'view_name' => 'cms.admin.sliders.create',
        ],
        [
            'path' => 'resources/views/cms/admin/sliders/edit.blade.php',
            'view_name' => 'cms.admin.sliders.edit',
        ],
        [
            'path' => 'resources/views/cms/admin/sliders/index.blade.php',
            'view_name' => 'cms.admin.sliders.index',
        ],
        [
            'path' => 'resources/views/cms/admin/sliders/partials/placement-tabs.blade.php',
            'view_name' => 'cms.admin.sliders.partials.placement-tabs',
        ],
        [
            'path' => 'resources/views/cms/admin/sliders/partials/responsive-image-set.blade.php',
            'view_name' => 'cms.admin.sliders.partials.responsive-image-set',
        ],
        [
            'path' => 'resources/views/cms/admin/languages/index.blade.php',
            'view_name' => 'cms.admin.languages.index',
        ],
        [
            'path' => 'resources/views/cms/admin/home.blade.php',
            'view_name' => 'cms.admin.home',
        ],
        [
            'path' => 'resources/views/cms/admin/promotions/create.blade.php',
            'view_name' => 'cms.admin.promotions.create',
        ],
        [
            'path' => 'resources/views/cms/admin/promotions/edit.blade.php',
            'view_name' => 'cms.admin.promotions.edit',
        ],
        [
            'path' => 'resources/views/cms/admin/menus/edit.blade.php',
            'view_name' => 'cms.admin.menus.edit',
        ],
        [
            'path' => 'resources/views/cms/admin/settings/site-settings.blade.php',
            'view_name' => 'cms.admin.settings.site-settings',
        ],
        [
            'path' => 'resources/views/cms/admin/partials/dashboard-icon.blade.php',
            'view_name' => 'cms.admin.partials.dashboard-icon',
        ],
        [
            'path' => 'resources/views/cms/admin/partials/dashboard-metric-detail.blade.php',
            'view_name' => 'cms.admin.partials.dashboard-metric-detail',
        ],
        [
            'path' => 'resources/views/geo-restricted.blade.php',
            'view_name' => 'geo-restricted',
        ],
        [
            'path' => 'resources/views/public-maintenance.blade.php',
            'view_name' => 'public-maintenance',
        ],
    ],
    'column_types' => [
        'password_resets' => [
            'email' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'token' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'created_at' => [
                'type' => 'timestamp',
                'nullable' => true,
            ],
        ],
        'failed_jobs' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'connection' => [
                'type' => 'text',
                'nullable' => false,
            ],
            'queue' => [
                'type' => 'text',
                'nullable' => false,
            ],
            'payload' => [
                'type' => 'longtext',
                'nullable' => false,
            ],
            'exception' => [
                'type' => 'longtext',
                'nullable' => false,
            ],
            'failed_at' => [
                'type' => 'timestamp',
                'nullable' => false,
            ],
        ],
        'site_settings' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'key' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'value' => [
                'type' => 'text',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
        ],
        'jobs_table' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'queue' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'payload' => [
                'type' => 'longtext',
                'nullable' => false,
            ],
            'attempts' => [
                'type' => 'tinyint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'reserved_at' => [
                'type' => 'int',
                'nullable' => true,
                'unsigned' => true,
            ],
            'available_at' => [
                'type' => 'int',
                'nullable' => false,
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'int',
                'nullable' => false,
                'unsigned' => true,
            ],
        ],
        'cms_user_types' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'name' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 50,
            ],
            'description' => [
                'type' => 'text',
                'nullable' => true,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'deleted_at' => [
                'type' => 'timestamp',
            ],
        ],
        'cms_countries' => [
            'id' => [
                'type' => 'bigint',
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'name' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'iso2' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 2,
            ],
        ],
        'cms_cities' => [
            'id' => [
                'type' => 'bigint',
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'name' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'country_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'state_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
        ],
        'cms_states' => [
            'id' => [
                'type' => 'bigint',
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'name' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'country_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
        ],
        'cms_users' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'first_name' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 50,
            ],
            'last_name' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 50,
            ],
            'username' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 50,
            ],
            'email' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 100,
            ],
            'phone' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 100,
            ],
            'email_verify_hash' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 150,
            ],
            'email_verified_at' => [
                'type' => 'datetime',
                'nullable' => true,
            ],
            'birth_date' => [
                'type' => 'date',
                'nullable' => true,
            ],
            'birth_city' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 30,
            ],
            'country_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'city_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'state_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'password' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 150,
            ],
            'password_raw' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 150,
            ],
            'language' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 15,
            ],
            'currency' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 15,
            ],
            'registration_source' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 300,
            ],
            'personal_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'player_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'external_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'partner_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'promotion_code_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'promotion_link_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'promotion_source_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'user_type_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'balance' => [
                'type' => 'decimal',
                'nullable' => false,
            ],
            'parent_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'gender' => [
                'type' => 'enum',
                'nullable' => true,
            ],
            'status' => [
                'type' => 'enum',
                'nullable' => false,
            ],
            'role' => [
                'type' => 'enum',
                'nullable' => false,
            ],
            'last_seen' => [
                'type' => 'timestamp',
                'nullable' => true,
            ],
            'activity_point' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'is_chat_blocked' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'is_private' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'user_color' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'remember_token' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'slot_bet_limit' => [
                'type' => 'decimal',
                'nullable' => false,
            ],
            'live_bet_limit' => [
                'type' => 'decimal',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'deleted_at' => [
                'type' => 'timestamp',
            ],
            'created_by_admin' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'username_normalized' => [
                'type' => 'varchar',
                'length' => 50,
                'generated' => true,
            ],
            'email_normalized' => [
                'type' => 'varchar',
                'length' => 100,
                'generated' => true,
            ],
            'marketing_email_opt_in' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'marketing_email_opted_out_at' => [
                'type' => 'timestamp',
                'nullable' => true,
            ],
            'transactional_email_opt_in' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'transactional_email_opted_out_at' => [
                'type' => 'timestamp',
                'nullable' => true,
            ],
            'phone_normalized' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 32,
            ],
            'is_smoke_account' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
        ],
        'cms_languages' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'name' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 30,
            ],
            'code' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 5,
            ],
            'status' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'is_default' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'active_default_guard' => [
                'type' => 'tinyint',
                'nullable' => true,
                'unsigned' => true,
            ],
        ],
        'cms_sliders' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'rank' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'status' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'image_path' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'image_name' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'use_same_image' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'deleted_at' => [
                'type' => 'timestamp',
            ],
            'placement' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 20,
            ],
            'theme_key' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 64,
            ],
        ],
        'cms_slider_translations' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'title' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'description' => [
                'type' => 'text',
                'nullable' => true,
            ],
            'image_path' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'image_name' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'url' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'cms_language_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'cms_slider_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'deleted_at' => [
                'type' => 'timestamp',
            ],
        ],
        'cms_menus' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'language_id' => [
                'type' => 'int',
                'nullable' => true,
            ],
            'name' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 20,
            ],
            'json' => [
                'type' => 'mediumtext',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'deleted_at' => [
                'type' => 'timestamp',
            ],
        ],
        'cms_user_logs' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'log_type' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'title' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'description' => [
                'type' => 'text',
                'nullable' => true,
            ],
            'cms_user_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'cms_operator_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
        ],
        'cms_site_settings' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'chat_status' => [
                'type' => 'enum',
                'nullable' => false,
            ],
            'splash_status' => [
                'type' => 'enum',
                'nullable' => false,
            ],
            'balance_status' => [
                'type' => 'enum',
                'nullable' => false,
            ],
            'socket_url' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'logo' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'socket_port' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 10,
            ],
            'active_theme' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'default_lang' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'register_status' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'menu_version' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 10,
            ],
            'topbar_fullscreen_status' => [
                'type' => 'varchar',
                'length' => 10,
            ],
            'topbar_logout_status' => [
                'type' => 'varchar',
                'length' => 10,
            ],
            'homepage_hero_auth_mode' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 16,
            ],
            'mobile_nav_type' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 20,
            ],
            'account_mail_status' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 10,
            ],
        ],
        'cms_site_setting_translations' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'site_title' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'site_description' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'first_modal_image_lg' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'first_modal_image_sm' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'first_modal_text' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'cms_language_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'cms_setting_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
        ],
        'cms_currencies' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'name' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'code' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'symbol' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'rate' => [
                'type' => 'double',
                'nullable' => false,
            ],
            'is_default' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'status' => [
                'type' => 'enum',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'deleted_at' => [
                'type' => 'timestamp',
            ],
        ],
        'cms_promotions' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'thumbnail' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'detail_image' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'redirect_url' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'status' => [
                'type' => 'enum',
                'nullable' => false,
            ],
            'is_permanent' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'rank' => [
                'type' => 'int',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'deleted_at' => [
                'type' => 'timestamp',
            ],
            'end_date' => [
                'type' => 'datetime',
                'nullable' => true,
            ],
            'show_in_top_line' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'show_on_homepage_top' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
        ],
        'cms_promotion_translations' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'listing_title' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'detail_title' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'short_description' => [
                'type' => 'text',
                'nullable' => false,
            ],
            'description' => [
                'type' => 'text',
                'nullable' => false,
            ],
            'redirect_button_text' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'cms_promotion_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'cms_language_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'deleted_at' => [
                'type' => 'timestamp',
            ],
        ],
        'cms_sessions' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'os_name' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'browser_name' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'user_agent' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'ip_address' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'remember_token' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'cms_user_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
            'expires_at' => [
                'type' => 'timestamp',
                'nullable' => true,
            ],
            'is_persistent' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
        ],
        'cms_sensitive_mutation_events' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'event_type' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'subject_type' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'subject_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'actor_type' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'actor_id' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'source' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'route_name' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'request_path' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'command_name' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'ip_address' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'user_agent' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 500,
            ],
            'metadata' => [
                'type' => 'json',
                'nullable' => true,
            ],
            'created_at' => [
                'type' => 'timestamp',
                'nullable' => true,
            ],
        ],
        'cms_staff_profiles' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'cms_user_id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'active' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'title' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'department' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
        ],
        'cms_staff_permissions' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'cms_user_id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'permission_key' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 120,
            ],
            'enabled' => [
                'type' => 'tinyint',
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
        ],
        'cms_theme_settings' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'theme_key' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 64,
            ],
            'logo' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'translations' => [
                'type' => 'json',
                'nullable' => true,
            ],
            'created_at' => [
                'type' => 'timestamp',
            ],
            'updated_at' => [
                'type' => 'timestamp',
            ],
        ],
        'cms_session_history' => [
            'id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'session_record_id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'cms_user_id' => [
                'type' => 'bigint',
                'nullable' => false,
                'unsigned' => true,
            ],
            'ended_by' => [
                'type' => 'bigint',
                'nullable' => true,
                'unsigned' => true,
            ],
            'reason' => [
                'type' => 'varchar',
                'nullable' => false,
                'length' => 255,
            ],
            'browser_name' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'os_name' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'ip_address' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'user_agent' => [
                'type' => 'varchar',
                'nullable' => true,
                'length' => 255,
            ],
            'started_at' => [
                'type' => 'timestamp',
                'nullable' => true,
            ],
            'last_activity_at' => [
                'type' => 'timestamp',
                'nullable' => true,
            ],
            'ended_at' => [
                'type' => 'timestamp',
                'nullable' => false,
            ],
        ],
    ],
    'foreign_keys' => [
        [
            'table' => 'cms_users',
            'columns' => ['user_type_id'],
            'references' => [
                'table' => 'cms_user_types',
                'columns' => ['id'],
            ],
            'on_delete' => 'source default (no explicit action)',
        ],
        [
            'table' => 'cms_users',
            'columns' => ['country_id'],
            'references' => [
                'table' => 'cms_countries',
                'columns' => ['id'],
            ],
            'on_delete' => 'source default (no explicit action)',
        ],
        [
            'table' => 'cms_users',
            'columns' => ['city_id'],
            'references' => [
                'table' => 'cms_cities',
                'columns' => ['id'],
            ],
            'on_delete' => 'source default (no explicit action)',
        ],
        [
            'table' => 'cms_users',
            'columns' => ['state_id'],
            'references' => [
                'table' => 'cms_states',
                'columns' => ['id'],
            ],
            'on_delete' => 'source default (no explicit action)',
        ],
        [
            'table' => 'cms_users',
            'columns' => ['parent_id'],
            'references' => [
                'table' => 'cms_users',
                'columns' => ['id'],
            ],
            'on_delete' => 'source default (no explicit action)',
        ],
        [
            'table' => 'cms_slider_translations',
            'columns' => ['cms_language_id'],
            'references' => [
                'table' => 'cms_languages',
                'columns' => ['id'],
            ],
            'on_delete' => 'cascade',
        ],
        [
            'table' => 'cms_slider_translations',
            'columns' => ['cms_slider_id'],
            'references' => [
                'table' => 'cms_sliders',
                'columns' => ['id'],
            ],
            'on_delete' => 'cascade',
        ],
        [
            'table' => 'cms_user_logs',
            'columns' => ['cms_user_id'],
            'references' => [
                'table' => 'cms_users',
                'columns' => ['id'],
            ],
            'on_delete' => 'cascade',
        ],
        [
            'table' => 'cms_user_logs',
            'columns' => ['cms_operator_id'],
            'references' => [
                'table' => 'cms_users',
                'columns' => ['id'],
            ],
            'on_delete' => 'cascade',
        ],
        [
            'table' => 'cms_site_setting_translations',
            'columns' => ['cms_language_id'],
            'references' => [
                'table' => 'cms_languages',
                'columns' => ['id'],
            ],
            'on_delete' => 'source default (no explicit action)',
        ],
        [
            'table' => 'cms_site_setting_translations',
            'columns' => ['cms_setting_id'],
            'references' => [
                'table' => 'cms_site_settings',
                'columns' => ['id'],
            ],
            'on_delete' => 'source default (no explicit action)',
        ],
        [
            'table' => 'cms_promotion_translations',
            'columns' => ['cms_language_id'],
            'references' => [
                'table' => 'cms_languages',
                'columns' => ['id'],
            ],
            'on_delete' => 'source default (no explicit action)',
        ],
        [
            'table' => 'cms_promotion_translations',
            'columns' => ['cms_promotion_id'],
            'references' => [
                'table' => 'cms_promotions',
                'columns' => ['id'],
            ],
            'on_delete' => 'source default (no explicit action)',
        ],
        [
            'table' => 'cms_sessions',
            'columns' => ['cms_user_id'],
            'references' => [
                'table' => 'cms_users',
                'columns' => ['id'],
            ],
            'on_delete' => 'source default (no explicit action)',
        ],
        [
            'table' => 'cms_staff_profiles',
            'columns' => ['cms_user_id'],
            'references' => [
                'table' => 'cms_users',
                'columns' => ['id'],
            ],
            'on_delete' => 'cascade',
        ],
        [
            'table' => 'cms_staff_permissions',
            'columns' => ['cms_user_id'],
            'references' => [
                'table' => 'cms_users',
                'columns' => ['id'],
            ],
            'on_delete' => 'cascade',
        ],
    ],
    'indexes' => [
        [
            'table' => 'password_resets',
            'columns' => ['email'],
            'kind' => 'index',
        ],
        [
            'table' => 'failed_jobs',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'site_settings',
            'columns' => ['id', 'key'],
            'kind' => 'index',
        ],
        [
            'table' => 'site_settings',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'jobs_table',
            'columns' => ['queue'],
            'kind' => 'index',
        ],
        [
            'table' => 'jobs_table',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_user_types',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_countries',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_cities',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_states',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_users',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_users',
            'columns' => ['user_type_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_users',
            'columns' => ['country_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_users',
            'columns' => ['city_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_users',
            'columns' => ['state_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_users',
            'columns' => ['parent_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_languages',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_sliders',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_slider_translations',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_slider_translations',
            'columns' => ['cms_language_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_slider_translations',
            'columns' => ['cms_slider_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_menus',
            'columns' => ['language_id', 'name'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_menus',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_user_logs',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_user_logs',
            'columns' => ['cms_user_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_user_logs',
            'columns' => ['cms_operator_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_site_settings',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_site_setting_translations',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_site_setting_translations',
            'columns' => ['cms_language_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_site_setting_translations',
            'columns' => ['cms_setting_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_currencies',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_promotions',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_promotion_translations',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_promotion_translations',
            'columns' => ['cms_language_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_promotion_translations',
            'columns' => ['cms_promotion_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_sessions',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_sessions',
            'columns' => ['cms_user_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_states',
            'columns' => ['country_id'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_cities',
            'columns' => ['country_id'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_cities',
            'columns' => ['state_id'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_countries',
            'columns' => ['iso2'],
            'kind' => 'unique',
        ],
        [
            'table' => 'cms_sliders',
            'columns' => ['placement', 'status', 'rank'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_sensitive_mutation_events',
            'columns' => ['event_type', 'created_at'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_sensitive_mutation_events',
            'columns' => ['subject_type', 'subject_id'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_sensitive_mutation_events',
            'columns' => ['actor_type', 'actor_id'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_sensitive_mutation_events',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_users',
            'columns' => ['username_normalized'],
            'kind' => 'unique',
        ],
        [
            'table' => 'cms_users',
            'columns' => ['email_normalized'],
            'kind' => 'unique',
        ],
        [
            'table' => 'cms_staff_profiles',
            'columns' => ['active'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_staff_profiles',
            'columns' => ['cms_user_id'],
            'kind' => 'unique',
        ],
        [
            'table' => 'cms_staff_permissions',
            'columns' => ['enabled'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_staff_permissions',
            'columns' => ['cms_user_id', 'permission_key'],
            'kind' => 'unique',
        ],
        [
            'table' => 'cms_staff_permissions',
            'columns' => ['permission_key'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_staff_profiles',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_staff_permissions',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_staff_permissions',
            'columns' => ['cms_user_id'],
            'kind' => 'foreign_key_required_index',
        ],
        [
            'table' => 'cms_theme_settings',
            'columns' => ['theme_key'],
            'kind' => 'unique',
        ],
        [
            'table' => 'cms_theme_settings',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
        [
            'table' => 'cms_sliders',
            'columns' => ['theme_key'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_sliders',
            'columns' => ['theme_key', 'placement', 'status', 'rank'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_users',
            'columns' => ['phone_normalized'],
            'kind' => 'unique',
        ],
        [
            'table' => 'cms_languages',
            'columns' => ['active_default_guard'],
            'kind' => 'unique',
        ],
        [
            'table' => 'cms_sessions',
            'columns' => ['expires_at'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_session_history',
            'columns' => ['session_record_id'],
            'kind' => 'unique',
        ],
        [
            'table' => 'cms_session_history',
            'columns' => ['cms_user_id'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_session_history',
            'columns' => ['ended_at'],
            'kind' => 'index',
        ],
        [
            'table' => 'cms_session_history',
            'columns' => ['id'],
            'kind' => 'primary',
        ],
    ],
    'writable_directories' => ['storage/app', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'],
];

// Explicit first Base extension; the original 41-migration B1 specification remains intact above.
$catalog = require __DIR__.'/base-catalog-contract.php';
$contract['migration_names'] = array_merge($contract['migration_names'], $catalog['migration_names']);
$contract['required_tables'] = array_merge($contract['required_tables'], array_keys($catalog['required_columns']));
$contract['required_columns'] = array_merge($contract['required_columns'], $catalog['required_columns']);
$contract['routes'] = array_merge($contract['routes'], $catalog['routes']);
$contract['views'] = array_merge($contract['views'], $catalog['views']);
foreach (['cms.front.learnCenter'=>'/cms/learn','cms.front.learnGuide'=>'/cms/learn/{slug}'] as $name=>$uri) {
    $contract['routes'][] = ['method'=>'GET','uri'=>$uri,'name'=>$name,
        'controller'=>'App\\Http\\Controllers\\Cms\\Front\\StaticPageController@learn',
        'middleware'=>['web','cmsUserActivity','cmsSessionCheck','EnforceSiteCountryRestriction','PublicFrontendMaintenance','cmsLanguage'],
        'id'=>'GLOBAL-JOURNEY-'.$name];
}
foreach (['learn','partials.journey.breadcrumb','partials.journey.nav-links','partials.journey.information-links'] as $view) {
    $contract['views'][] = ['view_name'=>'cms.global.'.$view,
        'path'=>'resources/views/cms/global/'.str_replace('.', '/', $view).'.blade.php'];
}
// Only the three explicitly reviewed demo endpoints and demo persistence extension.
$demo = require __DIR__.'/internal-demo-contract.php';
$contract['migration_names'] = array_merge($contract['migration_names'], $demo['migration_names']);
$contract['required_tables'] = array_merge($contract['required_tables'], array_keys($demo['required_columns']));
$contract['required_columns'] = array_merge($contract['required_columns'], $demo['required_columns']);
$contract['routes'] = array_merge($contract['routes'], $demo['routes']);
$contract['views'] = array_merge($contract['views'], $demo['views']);
$finance=require __DIR__.'/canonical-finance-contract.php';
$contract['migration_names']=array_merge($contract['migration_names'],$finance['migration_names']);
$contract['required_tables']=array_values(array_unique(array_merge($contract['required_tables'],array_keys($finance['required_columns']))));
foreach($finance['required_columns'] as $table=>$columns){$contract['required_columns'][$table]=array_values(array_unique(array_merge($contract['required_columns'][$table]??[],$columns)));}
$contract['routes']=array_merge($contract['routes'],$finance['routes']);
$contract['column_types']['cms_users']['balance']['type']='decimal';
foreach(['internal-blackjack','internal-blackjack-salon','internal-blackjack-shell'] as $view){$contract['views'][]=['view_name'=>'game-provider-front.'.$view,'path'=>'resources/views/game-provider-front/'.$view.'.blade.php'];}
$contract['migration_names'][]='2026_10_28_160100_allow_open_internal_game_rounds';

$contract['migration_names'][]='2026_10_28_180100_install_internal_crash_family';
$contract['required_tables'][]='internal_game_crash_rounds';
$contract['required_columns']['internal_game_crash_rounds']=['id','cms_game_id','game_code','currency','is_demo','status','round_public_id','server_seed','server_seed_hash','fair_hash','starts_at','crashed_at'];
$contract['required_columns']['internal_game_rounds']=array_merge($contract['required_columns']['internal_game_rounds'],['internal_game_crash_round_id','bet_lane','cashout_request_ref','cashout_multiplier','cashout_amount','cashed_out_at']);
foreach(['internal-skyrush','internal-orbit-x']as $v)$contract['views'][]=['view_name'=>'game-provider-front.'.$v,'path'=>'resources/views/game-provider-front/'.$v.'.blade.php'];
foreach(['internal-mini-slot','internal-big-win']as $v)$contract['views'][]=['view_name'=>'game-provider-front.'.$v,'path'=>'resources/views/game-provider-front/'.$v.'.blade.php'];
$contract['views'][]=['view_name'=>'game-provider-front.partials.slot-session','path'=>'resources/views/game-provider-front/partials/slot-session.blade.php'];
$contract['migration_names'][]='2026_10_28_210200_align_external_protocol_identity_lengths';
$external=require __DIR__.'/external-games-contract.php';
$contract['migration_names']=array_merge($contract['migration_names'],$external['migration_names']);
$contract['required_tables']=array_values(array_unique(array_merge($contract['required_tables'],array_keys($external['required_columns']))));
foreach($external['required_columns']as $t=>$c)$contract['required_columns'][$t]=array_values(array_unique(array_merge($contract['required_columns'][$t]??[],$c)));
$contract['routes']=array_merge($contract['routes'],$external['routes']);
$contract['views']=array_merge($contract['views'],$external['views']);
$sports=require __DIR__.'/sportsbook-contract.php';
$contract['migration_names']=array_merge($contract['migration_names'],$sports['migration_names']);
$contract['required_tables']=array_values(array_unique(array_merge($contract['required_tables'],array_keys($sports['required_columns']))));
foreach($sports['required_columns']as $t=>$c)$contract['required_columns'][$t]=array_values(array_unique(array_merge($contract['required_columns'][$t]??[],$c)));
$contract['routes']=array_merge($contract['routes'],$sports['routes']);
$contract['views']=array_merge($contract['views'],$sports['views']);
$chess=require __DIR__.'/chess-contract.php';
$contract['migration_names']=array_merge($contract['migration_names'],$chess['migration_names']);
$contract['required_tables']=array_values(array_unique(array_merge($contract['required_tables'],array_keys($chess['required_columns']))));
foreach($chess['required_columns']as $t=>$c)$contract['required_columns'][$t]=array_values(array_unique(array_merge($contract['required_columns'][$t]??[],$c)));
$contract['routes']=array_merge($contract['routes'],$chess['routes']);
$contract['views']=array_merge($contract['views'],$chess['views']);

$contract['migration_names'][] = '2026_11_04_000100_add_global_content_pages_and_accent_preset';
$contract['migration_names'][] = '2026_10_17_190000_add_external_games_rollout_key_lookup_index';
$contract['required_tables'][] = 'cms_content_pages';
$contract['required_columns']['cms_content_pages'] = ['id', 'slug', 'published', 'translations', 'created_at', 'updated_at'];
$contract['required_columns']['cms_theme_settings'][] = 'accent_preset';
foreach ([
    ['GET', '/cms/admin/content-pages', 'index'],
    ['GET', '/cms/admin/content-pages/{page}/edit', 'edit'],
    ['POST', '/cms/admin/content-pages', 'store'],
    ['PUT', '/cms/admin/content-pages/{page}', 'update'],
] as [$method, $uri, $action]) {
    $middleware = ['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction', 'PublicFrontendMaintenance', 'cmsUser', 'cmsAdmin'];
    if (in_array($method, ['POST', 'PUT'], true)) {
        $middleware[] = 'cmsRecentAuth';
    }
    $contract['routes'][] = [
        'id' => 'RUN29-CONTENT-PAGE-' . strtoupper($action), 'method' => $method, 'uri' => $uri,
        'name' => 'cms.admin.content-pages.' . $action,
        'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\ContentPageController@' . $action,
        'middleware' => $middleware,
    ];
}
foreach (['cms.admin.content-pages.index', 'cms.admin.content-pages.edit', 'cms.admin.content-pages.form', 'cms.global.content.custom-page'] as $view) {
    $contract['views'][] = ['view_name' => $view, 'path' => 'resources/views/' . str_replace('.', '/', $view) . '.blade.php'];
}


$contract['migration_names'][] = '2026_09_15_000100_create_crypto_settlement_claims_table';
$contract['migration_names'][] = '2026_09_15_000200_create_crypto_settlement_control_tables';
$contract['required_tables'] = array_merge($contract['required_tables'],
    ['crypto_settlement_claims', 'crypto_settlement_control', 'crypto_settlement_leases']);
$contract['required_columns']['crypto_settlement_claims'] = ['output_fingerprint', 'cms_transaction_id', 'exact_amount'];
foreach (['cms.global.nova-deposits', 'cms.admin.transactions.nova-deposits'] as $view) {
    $contract['views'][] = ['view_name' => $view, 'path' => 'resources/views/' . str_replace('.', '/', $view) . '.blade.php'];
}


foreach ([
    ['GET', '/cms/wallet/nova', 'cms.wallet.nova.index', 'Front\\NovaDepositController@index', []],
    ['POST', '/cms/wallet/nova', 'cms.wallet.nova.store', 'Front\\NovaDepositController@store', ['throttle:10,1']],
    ['POST', '/cms/wallet/nova/{reference}/recheck', 'cms.wallet.nova.recheck', 'Front\\NovaDepositController@recheck', ['throttle:30,1']],
    ['GET', '/cms/admin/finance/nova', 'cms.admin.finance.nova', 'Admin\\NovaDepositReadController', ['cmsStaffPermission:finance.manual.override']],
] as [$method, $uri, $name, $action, $extraMiddleware]) {
    $contract['routes'][] = [
        'id' => $name, 'method' => $method, 'uri' => $uri, 'name' => $name,
        'controller' => 'App\\Http\\Controllers\\Cms\\' . $action,
        'middleware' => array_merge(['web', 'cmsUserActivity', 'cmsSessionCheck', 'EnforceSiteCountryRestriction',
            'PublicFrontendMaintenance', 'cmsLanguage', 'cmsUser'], $extraMiddleware),
    ];
}


$contract['routes'][] = [
    'id' => 'RUN33-OPS-RADAR', 'method' => 'GET', 'uri' => '/cms/admin/ops-radar',
    'name' => 'cms.admin.opsRadar.index',
    'controller' => 'App\\Http\\Controllers\\Cms\\Admin\\OpsRadarController@index',
    'middleware' => ['web', 'cmsUser', 'cmsLanguage'],
];
$contract['views'][] = ['view_name' => 'cms.admin.ops-radar.index', 'path' => 'resources/views/cms/admin/ops-radar/index.blade.php'];

return $contract;
