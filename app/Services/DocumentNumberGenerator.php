<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\DocumentCounter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran dokumen berjalan per cabang per bulan.
 *
 * Tiap jenis dokumen punya serinya sendiri, sehingga nomor surat serah terima
 * dan nomor dokumen pengadaan tidak saling menggeser urutan.
 */
class DocumentNumberGenerator
{
    /**
     * Awalan dan seri untuk tiap jenis dokumen.
     *
     * @var array<string, string>
     */
    public const AWALAN = [
        'handover' => 'IDS-IT',
        'purchase_order' => 'PO',
        'goods_receipt' => 'GR',
        'payment' => 'PAY',
    ];

    /**
     * @param  string|Carbon  $date
     * @param  string  $series  Kunci pada self::AWALAN
     *
     * @throws \Exception
     */
    public function generate(Branch $branch, $date, string $series = 'handover'): string
    {
        $date = Carbon::parse($date);
        $year = $date->year;
        $month = $date->month;
        $awalan = self::AWALAN[$series] ?? strtoupper($series);

        return DB::transaction(function () use ($branch, $year, $month, $series, $awalan) {
            $counter = DocumentCounter::firstOrCreate(
                ['branch_id' => $branch->id, 'series' => $series, 'year' => $year, 'month' => $month],
                ['last_no' => 0]
            );

            // Dikunci agar dua permintaan bersamaan tidak mendapat nomor sama.
            $counter = DocumentCounter::where('id', $counter->id)->lockForUpdate()->first();

            $counter->last_no += 1;
            $counter->save();

            $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
            $seqStr = str_pad((string) $counter->last_no, 2, '0', STR_PAD_LEFT);

            // Format: {AWALAN}/{kode cabang}/{YYYY}/{MM}/{NN}
            return "{$awalan}/{$branch->code}/{$year}/{$monthStr}/{$seqStr}";
        });
    }
}
