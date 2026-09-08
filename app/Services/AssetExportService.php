<?php

namespace App\Services;

use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;

class AssetExportService
{
    /**
     * Export an Eloquent query of assets to XLSX.
     */
    public function export($query)
    {
        $writer = new Writer();
        // Menulis ke php://output, bukan openToBrowser(): header unduhan sudah
        // dikirim oleh response()->streamDownload() di pemanggilnya. Bila writer
        // ikut mengirim header, PHP melempar "headers already sent".
        $writer->openToFile('php://output');

        // Header
        $headerRow = Row::fromValues([
            'ID',
            'Kode Aset',
            'Kategori',
            'Merk',
            'Model',
            'S/N',
            'Kondisi',
            'Cabang',
            'Pemegang (Current Holder)',
            'Total Qty',
            'Qty Tersedia',
            'Qty Dipinjam/Dipakai',
            'Qty Dilepas'
        ]);
        $writer->addRow($headerRow);

        // Eager load for performance
        $query->with(['category', 'brand', 'condition', 'branch', 'currentHolder']);
        
        $query->chunk(500, function ($assets) use ($writer) {
            foreach ($assets as $asset) {
                $row = Row::fromValues([
                    $asset->id,
                    $asset->asset_code,
                    $asset->category?->name,
                    $asset->brand?->name,
                    $asset->model,
                    $asset->serial_number,
                    $asset->condition?->name,
                    $asset->branch?->name,
                    $asset->currentHolder?->name ?? '-',
                    $asset->quantity,
                    $asset->qty_available,
                    ($asset->qty_out - $asset->qty_in),
                    $asset->qty_writeoff
                ]);
                $writer->addRow($row);
            }
        });

        $writer->close();
    }
}
