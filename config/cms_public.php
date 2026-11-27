<?php

return [
    // External editorial links require an explicit host allowlist.
    'content_link_hosts' => [],
    'maintenance' => [
        'enabled' => env('CMS_PUBLIC_MAINTENANCE_ENABLED', true),
        'message' => env('CMS_PUBLIC_MAINTENANCE_MESSAGE', 'Under Maintenance'),
    ],
    'readiness' => [
        'required_tables' => [
            'cms_cities',
            'cms_countries',
            'cms_currencies',
            'cms_languages',
            'cms_menus',
            'cms_promotion_translations',
            'cms_promotions',
            'cms_sensitive_mutation_events',
            'cms_session_history',
            'cms_sessions',
            'cms_site_setting_translations',
            'cms_site_settings',
            'cms_slider_translations',
            'cms_sliders',
            'cms_staff_permissions',
            'cms_staff_profiles',
            'cms_states',
            'cms_theme_settings',
            'cms_user_logs',
            'cms_user_types',
            'cms_users',
            'failed_jobs',
            'jobs_table',
            'password_resets',
            'site_settings',
        ],
        'required_records' => [
            'cms_languages',
            'cms_site_settings',
            'cms_site_setting_translations',
            'cms_user_types',
            'cms_countries',
            'cms_currencies',
            'cms_users',
        ],
    ],
    'browser_fallback_locales' => ['tr', 'en'],
];
