<?php

namespace App\Services;

use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;
use Carbon\Carbon;

class AssetTransactionExportService
{
    /**
     * Export an Eloquent query of asset transactions to XLSX.
     */
    public function export($query)
    {
        $writer = new Writer();
        // Menulis ke php://output, bukan openToBrowser(): header unduhan sudah
        // dikirim oleh response()->streamDownload() di pemanggilnya. Bila writer
        // ikut mengirim header, PHP melempar "headers already sent".
        $writer->openToFile('php://output');

        $headerRow = Row::fromValues([
            'ID',
            'Tipe Transaksi',
            'Tanggal',
            'Kode Aset',
            'Jumlah',
            'Dari Karyawan',
            'Ke Karyawan',
            'Pemakai (User)',
            'Status',
            'Arah Stok',
            'No. Surat Serah Terima',
            'Keterangan',
        ]);
        $writer->addRow($headerRow);

        $query->with(['asset', 'fromEmployee', 'toEmployee', 'userEmployee', 'status', 'handoverDocument']);
        
        $query->chunk(500, function ($transactions) use ($writer) {
            foreach ($transactions as $transaction) {
                $row = Row::fromValues([
                    $transaction->id,
                    $transaction->type,
                    Carbon::parse($transaction->transaction_date)->format('Y-m-d'),
                    $transaction->asset?->asset_code,
                    $transaction->quantity,
                    $transaction->fromEmployee?->name ?? '-',
                    $transaction->toEmployee?->name ?? '-',
                    $transaction->userEmployee?->name ?? '-',
                    $transaction->status?->name,
                    $transaction->stock_direction,
                    $transaction->handoverDocument?->document_number ?? '-',
                    $transaction->notes,
                ]);
                $writer->addRow($row);
            }
        });

        $writer->close();
    }
}
