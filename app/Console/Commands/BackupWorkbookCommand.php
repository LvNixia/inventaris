<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetTransaction;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class BackupWorkbookCommand extends Command
{
    protected $signature = 'backup:workbook';

    protected $description = 'Backup data into a workbook (Excel)';

    public function handle()
    {
        $this->info('Starting backup...');

        $date = Carbon::now()->format('Ymd_His');
        $filename = "workbook_{$date}.xlsx";

        // Disk local berakar di storage/app/private, jadi jalur disknya cukup
        // "backups". Sebelumnya pemeriksaan direktori dan jalur berkas menunjuk
        // lokasi berbeda, sehingga foldernya tidak pernah terbuat di tempat
        // berkas ditulis dan perintah ini selalu gagal.
        $dir = 'backups';

        if (! Storage::exists($dir)) {
            Storage::makeDirectory($dir);
        }

        $path = Storage::path("{$dir}/{$filename}");

        $writer = new Writer;
        $writer->openToFile($path);

        // Sheet 1: Master
        $sheetMaster = $writer->getCurrentSheet();
        $sheetMaster->setName('Master');

        $writer->addRow(Row::fromValues(['--- CABANG ---']));
        foreach (Branch::all() as $branch) {
            $writer->addRow(Row::fromValues([$branch->id, $branch->code, $branch->name]));
        }

        $writer->addRow(Row::fromValues(['--- KATEGORI ---']));
        foreach (Category::all() as $cat) {
            $writer->addRow(Row::fromValues([$cat->id, $cat->code, $cat->name]));
        }

        $writer->addRow(Row::fromValues(['--- KARYAWAN ---']));
        foreach (Employee::all() as $emp) {
            $writer->addRow(Row::fromValues([$emp->id, $emp->nik, $emp->name]));
        }

        // Sheet 2: Aset
        $sheetAsset = $writer->addNewSheetAndMakeItCurrent();
        $sheetAsset->setName('Aset');
        $writer->addRow(Row::fromValues([
            'ID', 'Kode', 'Kategori', 'Merk', 'Model', 'SN', 'Kondisi', 'Cabang', 'Status', 'Tgl Beli', 'Harga Satuan',
        ]));

        Asset::with(['product.category', 'product.brand', 'purchaseBatch', 'condition', 'branch', 'currentStatus'])->chunk(500, function ($assets) use ($writer) {
            foreach ($assets as $asset) {
                $writer->addRow(Row::fromValues([
                    $asset->id,
                    $asset->asset_code,
                    $asset->product?->category?->name,
                    $asset->product?->brand?->name,
                    $asset->product?->model,
                    $asset->serial_number,
                    $asset->condition?->name,
                    $asset->branch?->name,
                    $asset->currentStatus?->name,
                    $asset->purchaseBatch?->purchase_date?->format('Y-m-d'),
                    (float) $asset->purchaseBatch?->unit_price,
                ]));
            }
        });

        // Sheet 3: Riwayat
        $sheetHistory = $writer->addNewSheetAndMakeItCurrent();
        $sheetHistory->setName('Riwayat');
        $writer->addRow(Row::fromValues([
            'ID', 'Tanggal', 'Aset', 'Tipe', 'Arah', 'Surat',
        ]));

        AssetTransaction::with(['asset', 'handoverDocument'])->chunk(500, function ($txs) use ($writer) {
            foreach ($txs as $tx) {
                $writer->addRow(Row::fromValues([
                    $tx->id,
                    Carbon::parse($tx->transaction_date)->format('Y-m-d'),
                    $tx->asset?->asset_code,
                    $tx->type,
                    $tx->stock_direction,
                    $tx->handoverDocument?->document_number,
                ]));
            }
        });

        $writer->close();
        $this->info("Backup saved to {$path}");
    }
}
