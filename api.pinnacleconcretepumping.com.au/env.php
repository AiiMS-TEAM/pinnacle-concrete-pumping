<?php
/**
 * .env loader for the cPanel-side API.
 *
 * Parses KEY=VALUE pairs from .env in the same directory and populates
 * getenv() / $_ENV. Skips blank lines and comments. Strips surrounding
 * single/double quotes. Does not overwrite values already set in the
 * environment.
 *
 * Usage:
 *   require_once __DIR__ . '/env.php';
 *   $secret = env_default('RECAPTCHA_SECRET');
 */

declare(strict_types=1);

(function (): void {
    $envPath = __DIR__ . '/.env';
    if (!is_file($envPath)) return;
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $eq = strpos($line, '=');
        if ($eq === false) continue;
        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));
        if (strlen($val) >= 2
            && ($val[0] === '"' || $val[0] === "'")
            && $val[strlen($val) - 1] === $val[0]) {
            $val = substr($val, 1, -1);
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $val);
            $_ENV[$key] = $val;
        }
    }
})();

if (!function_exists('env_default')) {
    function env_default(string $key, string $default = ''): string {
        $v = getenv($key);
        return ($v === false || $v === '') ? $default : $v;
    }
}
