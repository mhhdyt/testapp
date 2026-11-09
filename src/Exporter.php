<?php

declare(strict_types=1);

/**
 * Ekspor data produk ke CSV, XML (php8.3-xml), dan ZIP (php8.3-zip).
 */
final class Exporter
{
    private const COLUMNS = ['id', 'sku', 'name', 'category', 'price', 'stock', 'description', 'image', 'created_at', 'updated_at'];

    /** @param list<array<string, mixed>> $products */
    public static function csv(array $products): string
    {
        $out = fopen('php://temp', 'w+b');
        fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
        // Pemisah ';' sesuai pengaturan regional Indonesia di Excel
        fputcsv($out, self::COLUMNS, ';', '"', '');
        foreach ($products as $p) {
            $row = [];
            foreach (self::COLUMNS as $col) {
                $row[] = $p[$col] ?? '';
            }
            fputcsv($out, $row, ';', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** @param list<array<string, mixed>> $products */
    public static function xml(array $products): string
    {
        $summary = inventory_summary($products);

        $xw = new XMLWriter();
        $xw->openMemory();
        $xw->setIndent(true);
        $xw->setIndentString('  ');
        $xw->startDocument('1.0', 'UTF-8');

        $xw->startElement('inventaris');
        $xw->writeAttribute('dibuat', date(DATE_ATOM));
        $xw->writeAttribute('jumlah', (string) count($products));

        $xw->startElement('ringkasan');
        $xw->writeElement('total_stok', (string) $summary['stock']);
        $xw->startElement('nilai');
        $xw->writeAttribute('mata_uang', 'IDR');
        $xw->text($summary['subtotal']);
        $xw->endElement();
        $xw->writeElement('ppn', $summary['ppn']);
        $xw->writeElement('total', $summary['total']);
        $xw->endElement();

        $xw->startElement('produk_list');
        foreach ($products as $p) {
            $xw->startElement('produk');
            $xw->writeAttribute('id', (string) $p['id']);
            $xw->writeElement('sku', (string) $p['sku']);
            $xw->writeElement('nama', (string) $p['name']);
            $xw->writeElement('kategori', (string) ($p['category'] ?? ''));
            $xw->startElement('harga');
            $xw->writeAttribute('mata_uang', 'IDR');
            $xw->text((string) $p['price']);
            $xw->endElement();
            $xw->writeElement('stok', (string) $p['stock']);
            $xw->writeElement('deskripsi', (string) ($p['description'] ?? ''));
            $xw->writeElement('gambar', (string) ($p['image'] ?? ''));
            $xw->writeElement('diperbarui', (string) $p['updated_at']);
            $xw->endElement();
        }
        $xw->endElement(); // produk_list
        $xw->endElement(); // inventaris
        $xw->endDocument();

        return $xw->outputMemory();
    }

    /**
     * Buat file ZIP sementara berisi CSV, XML, dan gambar produk.
     *
     * @param list<array<string, mixed>> $products
     * @return string path file zip sementara (hapus setelah dikirim)
     */
    public static function zip(array $products, string $uploadDir): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'testapp_');
        if ($tmp === false) {
            throw new RuntimeException('Tidak bisa membuat file sementara.');
        }

        $zip = new ZipArchive();
        $res = $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($res !== true) {
            throw new RuntimeException('ZipArchive gagal dibuka (kode ' . $res . ').');
        }

        $zip->addFromString('produk.csv', self::csv($products));
        $zip->addFromString('produk.xml', self::xml($products));
        $zip->addFromString('BACA.txt', "Ekspor dari " . env('APP_NAME', 'Test App') . "\nDibuat: " . tanggal(new DateTimeImmutable()) . "\n");

        foreach ($products as $p) {
            $img = (string) ($p['image'] ?? '');
            if ($img !== '' && is_file($uploadDir . '/' . $img)) {
                $zip->addFile($uploadDir . '/' . $img, 'gambar/' . $p['sku'] . '.jpg');
            }
        }

        if (!$zip->close()) {
            throw new RuntimeException('Gagal menulis file ZIP.');
        }

        return $tmp;
    }
}
