<?php

namespace App\Support;

use Illuminate\Support\Facades\View;

final class CmsThemeResolver
{
    public const LEGACY_DEFAULT_THEME = 'global';
    public const NEUTRAL_DEFAULT_THEME = 'global';
    public const FALLBACK_THEME = 'global';

    private const SUPPORTED_THEMES = ['global' => [
        'key' => 'global', 'label' => 'Global', 'physical_namespace' => 'global',
        'status' => 'candidate', 'capability_profile' => 'global',
        'activation_ready' => false, 'preview_image' => null,
    ]];
    private const REQUIRED_LOGICAL_VIEWS = ['index', 'home', 'promotions', 'promotion-detail', 'sessions', 'information', 'auth.layout', 'auth.edit-password', 'auth.password-forgot', 'auth.password-reset', 'partials.layout.menu', 'partials.layout.footer', 'partials.layout.auth-sheet', 'partials.layout.account-sheet', 'partials.layout.utility-sheets', 'partials.layout.hero-slider', 'content.terms-conditions', 'content.privacy-policy', 'content.betting-rules', 'content.responsible-gaming', 'content.cookie', 'content.provably-fair'];
    private const THEME_ALIASES = [];

    public static function normalize(?string $theme): string
    {
        $theme = trim((string) $theme);

        return $theme !== '' ? $theme : self::NEUTRAL_DEFAULT_THEME;
    }

    public static function physicalNamespace(?string $theme): string
    {
        if (strtolower(self::normalize($theme)) !== 'global') {
            throw new \InvalidArgumentException('Unknown B1 theme.');
        }
        return 'global';
    }

    public static function supportedThemeKeys(): array
    {
        return array_keys(self::SUPPORTED_THEMES);
    }

    public static function supportedThemes(): array
    {
        return array_values(self::SUPPORTED_THEMES);
    }

    public static function themeDefinition(?string $theme): ?array
    {
        $normalizedTheme = strtolower(self::normalize($theme));

        if ($normalizedTheme === self::LEGACY_DEFAULT_THEME) {
            $normalizedTheme = self::NEUTRAL_DEFAULT_THEME;
        }

        return self::SUPPORTED_THEMES[$normalizedTheme] ?? null;
    }

    public static function capabilityProfile(?string $theme): ?string
    {
        $definition = self::themeDefinition($theme);

        return is_array($definition) && filled($definition['capability_profile'] ?? null)
            ? (string) $definition['capability_profile']
            : null;
    }

    public static function isActivationReady(?string $theme): bool
    {
        return (bool) (self::themeDefinition($theme)['activation_ready'] ?? false);
    }

    public static function assertActivationReady(?string $theme): void
    {
        $definition = self::themeDefinition($theme);

        if (! is_array($definition)) {
            throw new \InvalidArgumentException('Theme is not declared in the supported theme registry.');
        }

        if (! ($definition['activation_ready'] ?? false) && ! self::isLocalDevelopmentPreviewAllowed($theme)) {
            throw new \InvalidArgumentException('Theme has not passed the production launch-readiness gate.');
        }
    }

    /**
     * Permit the unfinished Global shell to be benchmarked only against a local DB.
     * This does not change its registry readiness or permit production activation.
     */
    public static function isLocalDevelopmentPreviewAllowed(?string $theme): bool
    {
        $connection = (string) config('database.default');
        $host = (string) config("database.connections.{$connection}.host");

        return self::supportedThemePublicKey($theme) === 'global'
            && app()->environment('local')
            && in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    public static function supportedThemePublicKey(?string $theme): string
    {
        return self::physicalNamespace($theme);
    }

    public static function resolveExistingPhysicalNamespace(?string $theme): string
    {
        $namespace = self::physicalNamespace($theme);
        if (!View::exists("cms.$namespace.index")) {
            throw new \RuntimeException('Required Global layout is missing.');
        }
        return $namespace;
    }

    /**
     * Fail closed when a persisted Global preview reaches a non-local runtime.
     * Keep default and WinX boot selection exactly as before. Never mutate settings.
     */
    public static function bootPhysicalNamespace(?string $theme): string
    {
        if (self::supportedThemePublicKey($theme) === 'global'
            && ! self::isActivationReady($theme)
            && ! self::isLocalDevelopmentPreviewAllowed($theme)) {
            return self::resolveExistingPhysicalNamespace(self::NEUTRAL_DEFAULT_THEME);
        }

        return self::resolveExistingPhysicalNamespace($theme);
    }

    public static function activePhysicalNamespace(): string
    {
        return defined('activeTheme') ? (string) activeTheme : self::LEGACY_DEFAULT_THEME;
    }

    public static function themeViewName(string $viewPath, ?string $theme = null): string
    {
        return 'cms.'.self::physicalNamespace($theme).".$viewPath";
    }

    public static function frontendComposerTargets(string $viewPath, ?string $theme = null): array
    {
        $physicalNamespace = $theme === null ? self::activePhysicalNamespace() : self::physicalNamespace($theme);

        return array_values(array_unique([
            self::themeViewName($viewPath, $physicalNamespace),
            self::themeViewName($viewPath, self::LEGACY_DEFAULT_THEME),
        ]));
    }

    public static function viewName(string $viewPath, ?string $theme = null): string
    {
        $name = self::themeViewName($viewPath, $theme);
        if (!View::exists($name)) {
            throw new \RuntimeException('Required Global view is missing: ' . $viewPath);
        }
        return $name;
    }

    public static function requiredLogicalViews(): array
    {
        return self::REQUIRED_LOGICAL_VIEWS;
    }

    public static function missingRequiredViews(?string $theme): array
    {
        $physicalNamespace = self::physicalNamespace($theme);

        return array_values(array_filter(
            self::REQUIRED_LOGICAL_VIEWS,
            fn (string $viewPath): bool => ! View::exists(self::themeViewName($viewPath, $physicalNamespace))
        ));
    }

    public static function hasRequiredViews(?string $theme): bool
    {
        return self::missingRequiredViews($theme) === [];
    }
}
