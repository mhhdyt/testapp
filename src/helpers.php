<?php

declare(strict_types=1);

const PPN_RATE = '0.11'; // PPN 11%, dihitung dengan bcmath agar presisi

function env(string $key, ?string $default = null): ?string
{
    return Env::get($key, $default);
}

/** Escape output HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

// ---------------------------------------------------------------- CSRF & flash

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Token CSRF tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** @return list<array{type: string, message: string}> */
function flashes(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return $items;
}

// ---------------------------------------------------------------- intl

function rupiah(string|int|float $amount): string
{
    static $fmt = null;
    if ($fmt === null) {
        $fmt = new NumberFormatter(env('APP_LOCALE', 'id_ID'), NumberFormatter::CURRENCY);
        $fmt->setAttribute(NumberFormatter::FRACTION_DIGITS, 0);
    }

    return (string) $fmt->formatCurrency((float) $amount, 'IDR');
}

function angka(string|int|float $number): string
{
    static $fmt = null;
    $fmt ??= new NumberFormatter(env('APP_LOCALE', 'id_ID'), NumberFormatter::DECIMAL);

    return (string) $fmt->format((float) $number);
}

/** Format tanggal Indonesia, mis. "Senin, 9 November 2026 15:55". */
function tanggal(string|DateTimeInterface|null $value, string $pattern = 'EEEE, d MMMM yyyy HH:mm'): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    $dt  = $value instanceof DateTimeInterface ? $value : new DateTimeImmutable($value);
    $fmt = new IntlDateFormatter(
        env('APP_LOCALE', 'id_ID'),
        IntlDateFormatter::NONE,
        IntlDateFormatter::NONE,
        date_default_timezone_get(),
        IntlDateFormatter::GREGORIAN,
        $pattern
    );

    return (string) $fmt->format($dt);
}

/** Ubah teks Unicode menjadi ASCII (dipakai untuk label gambar GD). */
function ascii(string $text): string
{
    $out = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);

    return $out === false ? preg_replace('/[^\x20-\x7E]/', '?', $text) : $out;
}

// ---------------------------------------------------------------- bcmath

/** Pembulatan setengah ke atas untuk string bcmath (bcround baru ada di PHP 8.4). */
function bc_round(string $number, int $precision = 2): string
{
    $half = '0.' . str_repeat('0', $precision) . '5';

    return str_starts_with($number, '-')
        ? bcsub($number, $half, $precision)
        : bcadd($number, $half, $precision);
}

/**
 * Hitung ringkasan nilai persediaan dengan bcmath.
 *
 * @param list<array<string, mixed>> $products
 * @return array{items: int, stock: int, subtotal: string, ppn: string, total: string}
 */
function inventory_summary(array $products): array
{
    $subtotal = '0.00';
    $stock    = 0;
    foreach ($products as $p) {
        $subtotal = bcadd($subtotal, bcmul((string) $p['price'], (string) $p['stock'], 2), 2);
        $stock   += (int) $p['stock'];
    }
    $ppn = bc_round(bcmul($subtotal, PPN_RATE, 4), 2);

    return [
        'items'    => count($products),
        'stock'    => $stock,
        'subtotal' => $subtotal,
        'ppn'      => $ppn,
        'total'    => bcadd($subtotal, $ppn, 2),
    ];
}
