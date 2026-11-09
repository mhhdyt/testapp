<?php

declare(strict_types=1);

/**
 * Uji fungsional untuk setiap paket yang terpasang:
 * nginx + php8.3-fpm, php8.3-{mysql,curl,gd,mbstring,xml,zip,intl,bcmath}, php-imagick, MariaDB.
 */
final class SystemCheck
{
    public const OK   = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    /** Ekstensi PHP => paket apt yang menyediakannya. */
    public const EXTENSIONS = [
        'pdo_mysql'     => 'php8.3-mysql',
        'mysqli'        => 'php8.3-mysql',
        'mysqlnd'       => 'php8.3-mysql',
        'curl'          => 'php8.3-curl',
        'gd'            => 'php8.3-gd',
        'mbstring'      => 'php8.3-mbstring',
        'xml'           => 'php8.3-xml',
        'dom'           => 'php8.3-xml',
        'simplexml'     => 'php8.3-xml',
        'xmlwriter'     => 'php8.3-xml',
        'xmlreader'     => 'php8.3-xml',
        'zip'           => 'php8.3-zip',
        'intl'          => 'php8.3-intl',
        'bcmath'        => 'php8.3-bcmath',
        'imagick'       => 'php-imagick',
        'fileinfo'      => 'php8.3-common',
        'exif'          => 'php8.3-common',
        'Zend OPcache'  => 'php8.3-opcache',
    ];

    /** @var list<array{group: string, name: string, status: string, detail: string}> */
    private array $results = [];

    /** @return list<array{group: string, name: string, status: string, detail: string}> */
    public function run(): array
    {
        $this->results = [];

        $this->checkPhp();
        $this->checkExtensions();
        $this->attempt('Database', 'PDO MariaDB', fn () => $this->testPdo());
        $this->attempt('Database', 'mysqli', fn () => $this->testMysqli());
        $this->attempt('Database', 'Tulis + rollback', fn () => $this->testWrite());
        $this->attempt('Fungsi', 'cURL', fn () => $this->testCurl());
        $this->attempt('Fungsi', 'GD', fn () => $this->testGd());
        $this->attempt('Fungsi', 'Imagick', fn () => $this->testImagick());
        $this->attempt('Fungsi', 'mbstring', fn () => $this->testMbstring());
        $this->attempt('Fungsi', 'intl', fn () => $this->testIntl());
        $this->attempt('Fungsi', 'bcmath', fn () => $this->testBcmath());
        $this->attempt('Fungsi', 'XML', fn () => $this->testXml());
        $this->attempt('Fungsi', 'Zip', fn () => $this->testZip());
        $this->checkFilesystem();

        return $this->results;
    }

    /** @param list<array{status: string}> $results */
    public static function overall(array $results): string
    {
        $statuses = array_column($results, 'status');

        return match (true) {
            in_array(self::FAIL, $statuses, true) => self::FAIL,
            in_array(self::WARN, $statuses, true) => self::WARN,
            default                               => self::OK,
        };
    }

    // ------------------------------------------------------------------ PHP

    private function checkPhp(): void
    {
        $this->add('PHP', 'Versi PHP', version_compare(PHP_VERSION, '8.3.0', '>=') ? self::OK : self::WARN, PHP_VERSION);

        $sapi = PHP_SAPI;
        $this->add('PHP', 'SAPI', in_array($sapi, ['fpm-fcgi', 'cli'], true) ? self::OK : self::WARN,
            $sapi . ($sapi === 'fpm-fcgi' ? ' (php8.3-fpm)' : ''));

        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            $this->add('PHP', 'Web server', self::OK, (string) $_SERVER['SERVER_SOFTWARE']);
        }

        $this->add('PHP', 'php.ini', self::OK, (string) (php_ini_loaded_file() ?: '(tidak ada)'));

        $upload = (string) ini_get('upload_max_filesize');
        $post   = (string) ini_get('post_max_size');
        $this->add('PHP', 'Batas upload', self::bytes($post) >= self::bytes($upload) ? self::OK : self::WARN,
            "upload_max_filesize=$upload, post_max_size=$post, memory_limit=" . ini_get('memory_limit'));

        $tz = (string) ini_get('date.timezone');
        $this->add('PHP', 'Zona waktu', self::OK, date_default_timezone_get() . ($tz === '' ? ' (date.timezone di php.ini kosong)' : ''));

        $opcache = function_exists('opcache_get_status') && (bool) ini_get('opcache.enable' . (PHP_SAPI === 'cli' ? '_cli' : ''));
        $this->add('PHP', 'OPcache aktif', $opcache || PHP_SAPI === 'cli' ? self::OK : self::WARN, $opcache ? 'ya' : 'tidak');
    }

    private function checkExtensions(): void
    {
        foreach (self::EXTENSIONS as $ext => $package) {
            $loaded  = extension_loaded($ext);
            $version = $loaded ? (phpversion($ext) ?: '') : '';
            $status  = $loaded ? self::OK : ($ext === 'Zend OPcache' || $ext === 'exif' ? self::WARN : self::FAIL);
            $this->add('Ekstensi', $ext, $status, $loaded ? trim("aktif $version") . " — $package" : "TIDAK aktif — apt install $package");
        }
    }

    // ------------------------------------------------------------------ Database

    /** @return array{0: string, 1: string} */
    private function testPdo(): array
    {
        $t0  = hrtime(true);
        $pdo = Database::connection();
        $ms  = (hrtime(true) - $t0) / 1e6;

        $info = $pdo->query('SELECT VERSION() AS v, @@character_set_connection AS cs, @@collation_connection AS col, @@session.time_zone AS tz')->fetch();

        $tableExists = (bool) $pdo->query("SHOW TABLES LIKE 'products'")->fetchColumn();
        $count       = $tableExists ? (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() : 0;

        $detail = sprintf(
            '%s — MariaDB %s, koneksi %.1f ms, charset %s/%s, tz %s, tabel products: %s',
            Database::target(),
            $info['v'],
            $ms,
            $info['cs'],
            $info['col'],
            $info['tz'],
            $tableExists ? "$count baris" : 'BELUM ADA (jalankan database/schema.sql)'
        );

        return [$tableExists ? self::OK : self::WARN, $detail];
    }

    /** @return array{0: string, 1: string} */
    private function testMysqli(): array
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = mysqli_init();
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

        $socket = env('DB_SOCKET', '');
        $db->real_connect(
            $socket !== '' ? 'localhost' : env('DB_HOST', '127.0.0.1'),
            env('DB_USER', ''),
            env('DB_PASS', ''),
            env('DB_NAME', 'testapp'),
            (int) env('DB_PORT', '3306'),
            $socket !== '' ? $socket : null
        );
        $db->set_charset(env('DB_CHARSET', 'utf8mb4'));
        $detail = 'server ' . $db->server_info . ', client ' . mysqli_get_client_info() . ', ' . $db->host_info;
        $db->close();

        return [self::OK, $detail];
    }

    /** @return array{0: string, 1: string} */
    private function testWrite(): array
    {
        $pdo = Database::connection();
        if (!(bool) $pdo->query("SHOW TABLES LIKE 'products'")->fetchColumn()) {
            return [self::WARN, 'dilewati, tabel products belum ada'];
        }

        $pdo->beginTransaction();
        try {
            $sku  = 'TEST-' . strtoupper(bin2hex(random_bytes(4)));
            $stmt = $pdo->prepare('INSERT INTO products (sku, name, price, stock) VALUES (?, ?, ?, ?)');
            $stmt->execute([$sku, 'Uji tulis — ÄÖÜ ñ 日本語 😀', '12345.67', 1]);
            $id  = (int) $pdo->lastInsertId();
            $row = $pdo->query('SELECT name, price FROM products WHERE id = ' . $id)->fetch();
        } finally {
            $pdo->rollBack();
        }

        $ok = $row && $row['name'] === 'Uji tulis — ÄÖÜ ñ 日本語 😀' && $row['price'] === '12345.67';

        return [$ok ? self::OK : self::FAIL, $ok
            ? 'INSERT/SELECT/ROLLBACK berhasil, UTF-8 (emoji) & DECIMAL utuh'
            : 'Data yang dibaca tidak sama: ' . json_encode($row, JSON_UNESCAPED_UNICODE)];
    }

    // ------------------------------------------------------------------ Ekstensi

    /** @return array{0: string, 1: string} */
    private function testCurl(): array
    {
        $info = curl_version();
        $base = sprintf('libcurl %s, %s', $info['version'], $info['ssl_version']);
        $url  = env('CURL_TEST_URL', '');
        if ($url === '' || $url === null) {
            return [self::OK, $base . ' (uji koneksi keluar dilewati)'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_USERAGENT      => 'testapp-healthcheck/1.0',
        ]);
        curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $time  = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '' || $code === 0) {
            // Server tanpa akses internet bukan kegagalan aplikasi, jadi WARN
            return [self::WARN, "$base — $url gagal: $error"];
        }

        return [$code < 400 ? self::OK : self::WARN, sprintf('%s — %s HTTP %d (%.0f ms)', $base, $url, $code, $time * 1000)];
    }

    /** @return array{0: string, 1: string} */
    private function testGd(): array
    {
        $info = gd_info();
        $img  = imagecreatetruecolor(120, 40);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 37, 99, 235));
        imagestring($img, 4, 10, 12, 'GD OK', (int) imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();

        $formats = [];
        foreach (['JPEG Support' => 'JPEG', 'PNG Support' => 'PNG', 'WebP Support' => 'WebP', 'GIF Create Support' => 'GIF', 'FreeType Support' => 'FreeType'] as $key => $label) {
            if (!empty($info[$key])) {
                $formats[] = $label;
            }
        }

        $ok = str_starts_with($png, "\x89PNG");

        return [$ok ? self::OK : self::FAIL, sprintf('%s, dukungan: %s, PNG uji %d byte', $info['GD Version'], implode(', ', $formats), strlen($png))];
    }

    /** @return array{0: string, 1: string} */
    private function testImagick(): array
    {
        $version = Imagick::getVersion()['versionString'] ?? '?';
        $img     = new Imagick();
        $img->newImage(64, 64, new ImagickPixel('#16a34a'));
        $img->setImageFormat('jpeg');
        $blob = $img->getImageBlob();
        $img->clear();

        $formats = Imagick::queryFormats();
        $need    = array_filter(['JPEG', 'PNG', 'WEBP', 'GIF'], static fn (string $f) => in_array($f, $formats, true));

        return [strlen($blob) > 0 ? self::OK : self::FAIL, sprintf('%s — format: %s (total %d), JPEG uji %d byte', $version, implode(', ', $need), count($formats), strlen($blob))];
    }

    /** @return array{0: string, 1: string} */
    private function testMbstring(): array
    {
        $text = 'Kopi Lampung ☕ — 日本語';
        $ok   = mb_strlen($text) === 20 && mb_strtoupper('ärger ñandú') === 'ÄRGER ÑANDÚ';

        return [$ok ? self::OK : self::FAIL, sprintf('mb_strlen=%d vs strlen=%d, internal_encoding=%s', mb_strlen($text), strlen($text), mb_internal_encoding())];
    }

    /** @return array{0: string, 1: string} */
    private function testIntl(): array
    {
        $rp  = rupiah('1234567.89');
        $tgl = tanggal(new DateTimeImmutable());
        $ok  = str_contains($rp, 'Rp') && str_contains($rp, '1.234.568');

        return [$ok ? self::OK : self::WARN, sprintf('ICU %s — %s — %s — "%s"', INTL_ICU_VERSION, $rp, $tgl, ascii('Café Ñusantara Ångström'))];
    }

    /** @return array{0: string, 1: string} */
    private function testBcmath(): array
    {
        $float = 0.1 + 0.2;
        $bc    = bcadd('0.1', '0.2', 1);
        $big   = bcmul('123456789012345678901234567890', '987654321', 0);
        $ok    = $bc === '0.3' && $big === '121932631124828532112482853211126352690';

        return [$ok ? self::OK : self::FAIL, sprintf('float 0.1+0.2=%s, bcadd=%s, PPN 11%% dari 15.500 = %s', var_export($float, true), $bc, bc_round(bcmul('15500', PPN_RATE, 4), 2))];
    }

    /** @return array{0: string, 1: string} */
    private function testXml(): array
    {
        $xml = Exporter::xml([[
            'id' => 1, 'sku' => 'X-1', 'name' => 'Uji <XML> & "kutip"', 'category' => 'Tes',
            'price' => '1000.00', 'stock' => 2, 'description' => 'é', 'image' => null, 'updated_at' => date('Y-m-d H:i:s'),
        ]]);

        $sx  = new SimpleXMLElement($xml);
        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $ok = (string) $sx->produk_list->produk->nama === 'Uji <XML> & "kutip"'
            && $dom->getElementsByTagName('produk')->length === 1;

        return [$ok ? self::OK : self::FAIL, sprintf('XMLWriter → SimpleXML → DOM berhasil, libxml %s, %d byte', LIBXML_DOTTED_VERSION, strlen($xml))];
    }

    /** @return array{0: string, 1: string} */
    private function testZip(): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'zipchk_');
        try {
            $zip = new ZipArchive();
            $zip->open((string) $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString('halo.txt', str_repeat('Halo dunia. ', 500));
            $zip->close();

            $zip->open((string) $tmp);
            $content = $zip->getFromName('halo.txt');
            $count   = $zip->numFiles;
            $zip->close();
            $size = filesize((string) $tmp);
        } finally {
            @unlink((string) $tmp);
        }

        $ok = $count === 1 && $content === str_repeat('Halo dunia. ', 500);

        return [$ok ? self::OK : self::FAIL, sprintf('libzip %s, 6000 byte → %d byte terkompresi', ZipArchive::LIBZIP_VERSION, $size)];
    }

    // ------------------------------------------------------------------ Filesystem

    private function checkFilesystem(): void
    {
        $env = BASE_PATH . '/.env';
        $this->add('Filesystem', '.env', is_readable($env) ? self::OK : self::FAIL, is_readable($env) ? 'terbaca' : 'tidak ada / tidak terbaca: ' . $env);

        $writable = is_dir(UPLOAD_PATH) && is_writable(UPLOAD_PATH);
        $user     = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '?') : get_current_user();
        $this->add('Filesystem', 'Folder uploads', $writable ? self::OK : self::FAIL,
            ($writable ? 'bisa ditulis' : 'TIDAK bisa ditulis') . " oleh user '$user' — " . UPLOAD_PATH);

        $tmp = sys_get_temp_dir();
        $this->add('Filesystem', 'Folder temp', is_writable($tmp) ? self::OK : self::FAIL, $tmp);
    }

    // ------------------------------------------------------------------ util

    /** @param callable(): array{0: string, 1: string} $fn */
    private function attempt(string $group, string $name, callable $fn): void
    {
        try {
            [$status, $detail] = $fn();
            $this->add($group, $name, $status, $detail);
        } catch (Throwable $e) {
            $this->add($group, $name, self::FAIL, $e::class . ': ' . $e->getMessage());
        }
    }

    private function add(string $group, string $name, string $status, string $detail): void
    {
        $this->results[] = compact('group', 'name', 'status', 'detail');
    }

    private static function bytes(string $value): int
    {
        $value = trim($value);
        $num   = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g'     => $num * 1024 ** 3,
            'm'     => $num * 1024 ** 2,
            'k'     => $num * 1024,
            default => $num,
        };
    }
}
