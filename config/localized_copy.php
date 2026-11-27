<?php

return [
    // Copy remains in the existing Laravel/CMS translation files. This config
    // only maps stable product copy keys to those existing sources.
    'fallback_locale' => 'en',

    'aliases' => [
        'legal' => [
            'terms' => 'cms_public.legal_pages.terms_conditions',
            'privacy' => 'cms_public.legal_pages.privacy_policy',
            'betting_rules' => 'cms_public.legal_pages.betting_rules',
            'responsible_gaming' => 'cms_public.legal_pages.responsible_gaming',
            'cookie' => 'cms_public.legal_pages.cookie',
            'provably_fair' => 'cms_public.legal_pages.provably_fair',
        ],
    ],
];
