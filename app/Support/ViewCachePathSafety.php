<?php

namespace App\Support;

use RuntimeException;

final class ViewCachePathSafety
{
    public function assertSafe(string $command, string $environment, ?string $path, string $storageRoot): void
    {
        if (!in_array($environment, ['local', 'testing'], true)
            || !in_array($command, ['view:clear', 'view:cache', 'optimize:clear'], true)) {
            return;
        }
        $resolved = $path ? realpath($path) : false;
        $framework = realpath($storageRoot . '/framework');
        $standard = $framework ? $framework . '/views' : '';
        $testing = $framework ? $framework . '/testing/' : '';
        if (!$resolved || !$framework || !is_dir($resolved)
            || !($resolved === $standard
                || (str_starts_with($resolved, $testing) && basename($resolved) === 'views'))) {
            throw new RuntimeException('Unsafe compiled-view path: use an existing repo-owned framework/views or framework/testing/.../views directory.');
        }
    }
}
