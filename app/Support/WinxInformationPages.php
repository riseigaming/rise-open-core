<?php

namespace App\Support;

/** One allowlist for information content, navigation and legacy URL targets. */
final class WinxInformationPages
{
    public const PAGES = [
        'provably-fair' => ['copy' => 'provably_fair', 'icon' => 'fas fa-check-circle'],
        'responsible-gaming' => ['copy' => 'responsible_gaming', 'icon' => 'fas fa-heart'],
        'terms-conditions' => ['copy' => 'terms', 'icon' => 'fas fa-balance-scale'],
        'betting-rules' => ['copy' => 'betting_rules', 'icon' => 'fas fa-list'],
        'privacy-policy' => ['copy' => 'privacy', 'icon' => 'fas fa-user-shield'],
        'cookie' => ['copy' => 'cookie', 'icon' => 'fas fa-cookie-bite'],
    ];

    public static function url(string $page, array $query = []): string
    {
        if (!isset(self::PAGES[$page])) {
            throw new \InvalidArgumentException('Unknown information section.');
        }

        return route('cms.information', $query).'#'.$page;
    }
}
