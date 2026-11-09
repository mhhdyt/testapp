<?php

declare(strict_types=1);

/**
 * Koneksi PDO ke MariaDB (driver pdo_mysql dari paket php8.3-mysql).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $charset = env('DB_CHARSET', 'utf8mb4');
        $name    = env('DB_NAME', 'testapp');
        $socket  = env('DB_SOCKET', '');

        $dsn = $socket !== ''
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $name, $charset)
            : sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                env('DB_HOST', '127.0.0.1'),
                (int) env('DB_PORT', '3306'),
                $name,
                $charset
            );

        $pdo = new PDO($dsn, env('DB_USER', ''), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]);

        // Samakan zona waktu sesi MariaDB dengan PHP (mis. +07:00 untuk Asia/Jakarta)
        $pdo->exec("SET time_zone = '" . (new DateTimeImmutable())->format('P') . "'");

        return self::$pdo = $pdo;
    }

    /** Info ringkas untuk ditampilkan (tanpa password). */
    public static function target(): string
    {
        $socket = env('DB_SOCKET', '');
        $where  = $socket !== '' ? 'socket ' . $socket : env('DB_HOST', '127.0.0.1') . ':' . env('DB_PORT', '3306');

        return sprintf('%s@%s / %s', env('DB_USER', ''), $where, env('DB_NAME', 'testapp'));
    }
}
