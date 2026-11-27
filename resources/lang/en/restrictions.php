<?php

return [
    'vpn_policy' => [
        'title' => 'VPN / Proxy policy',
        'help' => 'Choose what happens only when a VPN or proxy is positively detected.',
        'label' => 'VPN / Proxy',
        'allow' => 'Allow VPN / Proxy',
        'block' => 'Block detected VPN / Proxy',
        'unknown_help' => 'An unknown or failed VPN check is not treated as detected; the normal country rule still applies.',
        'reason' => 'Change reason',
        'note' => 'Operator note (optional)',
        'last_changed' => 'Last changed :time by :user',
    ],
];
