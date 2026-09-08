<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetTransaction;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Employee;
use Illuminate\Console\Command;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupWorkbookCommand extends Command
{
    protected $signature = 'backup:workbook';
    protected $description = 'Backup data into a workbook (Excel)';

    public function handle()
    {
        $this->info('Starting backup...');

        $date = Carbon::now()->format('Ymd_His');
        $filename = "workbook_{$date}.xlsx";
        $dir = 'private/backups';

        if (!Storage::exists($dir)) {
            Storage::makeDirectory($dir);
        }

        $path = storage_path("app/{$dir}/{$filename}");

        $writer = new Writer();
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
            'ID', 'Kode', 'Kategori', 'Merk', 'Model', 'SN', 'Kondisi', 'Cabang', 'Qty', 'Tersedia', 'Dipegang', 'Writeoff'
        ]));

        Asset::with(['category', 'brand', 'condition', 'branch'])->chunk(500, function ($assets) use ($writer) {
            foreach ($assets as $asset) {
                $writer->addRow(Row::fromValues([
                    $asset->id,
                    $asset->asset_code,
                    $asset->category?->name,
                    $asset->brand?->name,
                    $asset->model,
                    $asset->serial_number,
                    $asset->condition?->name,
                    $asset->branch?->name,
                    $asset->quantity,
                    $asset->qty_available,
                    ($asset->qty_out - $asset->qty_in),
                    $asset->qty_writeoff
                ]));
            }
        });

        // Sheet 3: Riwayat
        $sheetHistory = $writer->addNewSheetAndMakeItCurrent();
        $sheetHistory->setName('Riwayat');
        $writer->addRow(Row::fromValues([
            'ID', 'Tanggal', 'Aset', 'Tipe', 'Qty', 'Arah', 'Surat'
        ]));

        AssetTransaction::with(['asset', 'handoverDocument'])->chunk(500, function ($txs) use ($writer) {
            foreach ($txs as $tx) {
                $writer->addRow(Row::fromValues([
                    $tx->id,
                    Carbon::parse($tx->transaction_date)->format('Y-m-d'),
                    $tx->asset?->asset_code,
                    $tx->type,
                    $tx->quantity,
                    $tx->stock_direction,
                    $tx->handoverDocument?->document_number
                ]));
            }
        });

        $writer->close();
        $this->info("Backup saved to {$path}");
    }
}
