<?php

namespace App\Services;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class AssetExportService
{
    /**
     * Export an Eloquent query of assets to XLSX.
     */
    public function export($query)
    {
        $writer = new Writer;
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
            'Status', 'Tgl Beli', 'No. Faktur', 'Harga Satuan',
        ]);
        $writer->addRow($headerRow);

        // Eager load for performance
        $query->with(['product.category', 'product.brand', 'purchaseBatch', 'condition', 'branch', 'currentHolder', 'currentStatus']);

        $query->chunk(500, function ($assets) use ($writer) {
            foreach ($assets as $asset) {
                $row = Row::fromValues([
                    $asset->id,
                    $asset->asset_code,
                    $asset->product?->category?->name,
                    $asset->product?->brand?->name,
                    $asset->product?->model,
                    $asset->serial_number,
                    $asset->condition?->name,
                    $asset->branch?->name,
                    $asset->currentHolder?->name ?? '-',
                    $asset->currentStatus?->name,
                    $asset->purchaseBatch?->purchase_date?->format('Y-m-d'),
                    $asset->purchaseBatch?->invoice_number,
                    (float) $asset->purchaseBatch?->unit_price,
                ]);
                $writer->addRow($row);
            }
        });

        $writer->close();
    }
}
