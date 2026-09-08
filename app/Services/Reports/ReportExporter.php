<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mesin keluaran laporan.
 *
 * Judul, kolom, dan baris disiapkan oleh masing-masing halaman laporan,
 * sehingga versi layar, XLSX, dan PDF selalu berasal dari sumber yang sama.
 */
class ReportExporter
{
    /**
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>  $rows
     * @param  array<string, string>  $meta  Ringkasan filter yang sedang aktif.
     */
    public function xlsx(string $title, array $headings, iterable $rows, array $meta = []): StreamedResponse
    {
        // Berkas XLSX adalah wadah ZIP. Diperiksa di sini, sebelum respons
        // mulai dikirim, supaya kegagalan tampil sebagai pesan yang jelas
        // dan bukan berkas rusak yang terlanjur terunduh separuh.
        static::ensureXlsxIsSupported();

        return response()->streamDownload(function () use ($title, $headings, $rows, $meta) {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $bold = (new Style)->setFontBold();

            $writer->addRow(Row::fromValues([$title], $bold));
            $writer->addRow(Row::fromValues(['Dibuat', now()->translatedFormat('d F Y H:i')]));

            foreach ($meta as $label => $value) {
                $writer->addRow(Row::fromValues([$label, $value]));
            }

            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues($headings, $bold));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(
                    // OpenSpout menolak nilai objek; semua diseragamkan jadi teks/angka.
                    fn ($value) => is_scalar($value) || $value === null ? $value : (string) $value,
                    $row,
                )));
            }

            $writer->close();
        }, $this->filename($title, 'xlsx'));
    }

    /**
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>  $rows
     * @param  array<string, string>  $meta
     */
    public function pdf(string $title, array $headings, iterable $rows, array $meta = [], string $orientation = 'landscape'): StreamedResponse
    {
        $pdf = Pdf::loadView('pdf.report', [
            'title' => $title,
            'headings' => $headings,
            'rows' => is_array($rows) ? $rows : iterator_to_array($rows),
            'meta' => $meta,
            'printedAt' => now()->translatedFormat('d F Y H:i'),
            'printedBy' => auth()->user()?->name,
        ])->setPaper('a4', $orientation);

        $output = $pdf->output();

        // Harus StreamedResponse: aksi Livewire menjadikan nilai balik biasa
        // sebagai JSON, dan isi PDF yang biner bukan UTF-8 sehingga memicu
        // "Malformed UTF-8 characters". Hanya StreamedResponse dan
        // BinaryFileResponse yang dikenali Livewire sebagai unduhan berkas.
        return response()->streamDownload(
            fn () => print($output),
            $this->filename($title, 'pdf'),
            ['Content-Type' => 'application/pdf'],
        );
    }

    public static function supportsXlsx(): bool
    {
        return extension_loaded('zip');
    }

    /**
     * @throws \RuntimeException bila ekstensi zip PHP belum aktif.
     */
    public static function ensureXlsxIsSupported(): void
    {
        if (static::supportsXlsx()) {
            return;
        }

        throw new \RuntimeException(
            'Ekspor XLSX butuh ekstensi PHP "zip" yang saat ini belum aktif. '
            . 'Aktifkan lewat Laragon (menu PHP > Extensions > zip) atau hapus tanda titik koma '
            . 'pada baris ";extension=zip" di php.ini, lalu jalankan ulang layanan web. '
            . 'Sementara itu, laporan tetap bisa diunduh sebagai PDF.'
        );
    }

    protected function filename(string $title, string $extension): string
    {
        return str($title)->slug('_')->value() . '_' . now()->format('Ymd_His') . '.' . $extension;
    }
}
