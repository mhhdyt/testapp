<?php

declare(strict_types=1);

/**
 * Proses gambar upload: validasi, resize, dan simpan ulang sebagai JPEG.
 * Memakai Imagick (php-imagick) jika ada, jika tidak memakai GD (php8.3-gd).
 * Re-encode juga membuang metadata/EXIF dan payload tersembunyi di file.
 */
final class ImageService
{
    public const MAX_DIM = 800;

    private const ALLOWED = [
        'image/jpeg' => true,
        'image/png'  => true,
        'image/webp' => true,
        'image/gif'  => true,
    ];

    public static function engine(): string
    {
        return match (true) {
            extension_loaded('imagick') => 'imagick',
            extension_loaded('gd')      => 'gd',
            default                     => 'none',
        };
    }

    /**
     * @param array{error?: int, size?: int, tmp_name?: string} $file elemen dari $_FILES
     * @return string nama file yang disimpan di folder uploads/
     */
    public static function storeUpload(array $file, string $targetDir): string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::uploadError($error));
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            throw new RuntimeException('File upload tidak valid.');
        }

        $maxBytes = (int) env('UPLOAD_MAX_MB', '5') * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException('Ukuran gambar melebihi ' . env('UPLOAD_MAX_MB', '5') . ' MB.');
        }

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Format gambar harus JPG, PNG, WEBP, atau GIF (terdeteksi: ' . $mime . ').');
        }

        if (!is_dir($targetDir) || !is_writable($targetDir)) {
            throw new RuntimeException('Folder upload tidak bisa ditulis: ' . $targetDir);
        }

        $name = bin2hex(random_bytes(16)) . '.jpg';
        $dest = rtrim($targetDir, '/') . '/' . $name;

        match (self::engine()) {
            'imagick' => self::resizeImagick($tmp, $dest),
            'gd'      => self::resizeGd($tmp, $dest, $mime),
            default   => throw new RuntimeException('Ekstensi imagick/gd tidak tersedia.'),
        };

        return $name;
    }

    public static function delete(?string $name, string $dir): void
    {
        if ($name === null || $name === '' || !preg_match('/^[a-f0-9]{32}\.jpg$/', $name)) {
            return;
        }
        $path = rtrim($dir, '/') . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function resizeImagick(string $src, string $dest): void
    {
        $img = new Imagick($src . '[0]'); // hanya frame pertama (GIF animasi)

        switch ($img->getImageOrientation()) {
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $img->rotateImage('#000', 180);
                break;
            case Imagick::ORIENTATION_RIGHTTOP:
                $img->rotateImage('#000', 90);
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $img->rotateImage('#000', -90);
                break;
        }
        $img->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);

        // Ratakan transparansi ke latar putih (JPEG tidak punya alpha)
        $img->setImageBackgroundColor('white');
        $flat = $img->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $img->clear();

        if ($flat->getImageWidth() > self::MAX_DIM || $flat->getImageHeight() > self::MAX_DIM) {
            $flat->thumbnailImage(self::MAX_DIM, self::MAX_DIM, true);
        }

        $flat->stripImage();
        $flat->setImageFormat('jpeg');
        $flat->setImageCompressionQuality(85);
        $flat->writeImage($dest);
        $flat->clear();
    }

    private static function resizeGd(string $src, string $dest, string $mime): void
    {
        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($src),
            'image/png'  => @imagecreatefrompng($src),
            'image/webp' => @imagecreatefromwebp($src),
            'image/gif'  => @imagecreatefromgif($src),
        };
        if ($source === false) {
            throw new RuntimeException('Gambar rusak atau tidak bisa dibaca GD.');
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif  = @exif_read_data($src);
            $angle = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };
            if ($angle !== 0) {
                $rotated = imagerotate($source, $angle, 0);
                if ($rotated !== false) {
                    $source = $rotated;
                }
            }
        }

        $w     = imagesx($source);
        $h     = imagesy($source);
        $ratio = min(1, self::MAX_DIM / max($w, $h));
        $nw    = max(1, (int) round($w * $ratio));
        $nh    = max(1, (int) round($h * $ratio));

        $canvas = imagecreatetruecolor($nw, $nh);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);

        if (!imagejpeg($canvas, $dest, 85)) {
            throw new RuntimeException('Gagal menyimpan gambar.');
        }
    }

    private static function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas upload_max_filesize/post_max_size.',
            UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian.',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang diupload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara PHP tidak ada.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
            default               => 'Upload gagal (kode ' . $code . ').',
        };
    }
}
