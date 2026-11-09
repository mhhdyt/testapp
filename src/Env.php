<?php

declare(strict_types=1);

/**
 * Pembaca file .env sederhana (tanpa Composer).
 * Format: KEY=VALUE, nilai boleh dibungkus "..." atau '...'.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line); // juga membuang \r jika file diedit di Windows
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $len = strlen($value);
            if ($len >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[$len - 1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            self::$vars[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        // Variabel environment asli (mis. dari pool php-fpm `env[DB_PASS]`) diprioritaskan.
        $real = getenv($key);
        if ($real !== false && $real !== '') {
            return $real;
        }

        return self::$vars[$key] ?? $default;
    }
}
