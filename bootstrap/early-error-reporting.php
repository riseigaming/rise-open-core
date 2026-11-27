<?php

if (PHP_VERSION_ID < 80400) {
    return;
}

$earlyErrorReportingReadEnv = static function (string $key): ?string {
    foreach ([getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $value) {
        if ($value !== false && $value !== null) {
            return (string) $value;
        }
    }

    $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (!is_readable($envPath)) {
        return null;
    }

    $handle = fopen($envPath, 'rb');
    if (!$handle) {
        return null;
    }

    try {
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            if (trim($name) !== $key) {
                continue;
            }

            $value = trim($value);
            if ($value !== '' && $value[0] === '#') {
                return '';
            }

            if (
                strlen($value) >= 2
                && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            } else {
                $value = preg_replace('/\s+#.*$/', '', $value);
            }

            return trim((string) $value);
        }
    } finally {
        fclose($handle);
    }

    return null;
};

$earlyErrorReportingBool = static function (?string $value): bool {
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
};

$appEnvironment = $earlyErrorReportingReadEnv('APP_ENV');

if ($appEnvironment === null || in_array($appEnvironment, ['local', 'testing'], true)) {
    return;
}

if (
    $earlyErrorReportingBool($earlyErrorReportingReadEnv('APP_DEBUG'))
    || $earlyErrorReportingBool($earlyErrorReportingReadEnv('APP_REPORT_DEPRECATIONS'))
) {
    return;
}

error_reporting(error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED);
