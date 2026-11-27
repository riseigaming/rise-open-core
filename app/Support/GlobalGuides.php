<?php
namespace App\Support;

/** Usage guides for implemented consumers only. No operator or financial claims. */
final class GlobalGuides
{
    public const SLUGS = ['catalog-discovery', 'account-security', 'promotions-and-information'];

    public static function all(): array
    {
        return array_intersect_key((array) trans('journey.guides'), array_flip(self::SLUGS));
    }

    public static function links(string $slug): array
    {
        return match ($slug) {
            'catalog-discovery' => [__('journey.catalog') => route('cms.games.index'), __('journey.providers') => route('cms.games.index').'#catalog-providers'],
            'account-security' => auth('cms')->check()
                ? [__('global.sessions') => route('cms.front.sessions'), __('global.b1.change_password') => route('cms.front.editPassword')]
                : [__('global.log_in') => route('cms.login'), __('journey.password_reset') => route('cms.password.request')],
            'promotions-and-information' => [__('cms_public.promotions.title') => route('cms.promotions'), __('journey.information') => route('cms.information')],
            default => [],
        };
    }
}
