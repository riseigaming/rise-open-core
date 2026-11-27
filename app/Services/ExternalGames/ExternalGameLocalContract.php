<?php
namespace App\Services\ExternalGames;

/** Explicit opt-in for the owned loopback protocol fixtures. */
final class ExternalGameLocalContract
{
    public static function allows(string $url, string $alias = ExternalGameProviderConfig::PRIMARY): bool
    {
        $u=parse_url($url);
        return app()->environment(['local','testing'])
            && config('external_games.local_contract_enabled',false)
            && ($u['scheme']??'')==='http' && ($u['host']??'')==='127.0.0.1'
            && ($u['port']??0) === match ($alias) {
                ExternalGameProviderConfig::PRIMARY => 18971,
                'secondary-external-provider' => 18972,
                default => -1,
            } && !isset($u['user']) && !isset($u['pass']);
    }
    public static function frameAllowed(string $url, string $alias = ExternalGameProviderConfig::PRIMARY): bool
    {
        if (self::allows($url, $alias)) return true;
        $origin=SpinStructDemoClient::frameOrigin($url);
        $origins = $alias === ExternalGameProviderConfig::PRIMARY
            ? config('external_games.frame_origins', [])
            : config("external_games.providers.{$alias}.frame_origins", []);
        return $origin !== null && str_starts_with($origin, 'https://') && in_array($origin, (array) $origins, true);
    }
}
