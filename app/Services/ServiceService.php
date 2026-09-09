<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetService as AssetServiceModel;
use App\Models\AssetStatus;
use App\Models\ServiceKind;
use App\Models\ServiceResult;
use App\Models\Vendor;
use Exception;
use Illuminate\Support\Facades\DB;

class ServiceService
{
    protected TransactionService $transactionService;

    protected StockCalculator $stockCalculator;

    public function __construct(TransactionService $transactionService, StockCalculator $stockCalculator)
    {
        $this->transactionService = $transactionService;
        $this->stockCalculator = $stockCalculator;
    }

    /**
     * Buka catatan servis.
     */
    public function open(Asset $asset, array $data)
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = Asset::where('id', $asset->id)->lockForUpdate()->first();

            // Validate no open service
            $openService = AssetServiceModel::where('asset_id', $asset->id)
                ->where('status', 'open')
                ->first();

            if ($openService) {
                throw new Exception("Masih ada servis berjalan (#{$openService->id}); selesaikan dulu.");
            }

            if (in_array($asset->currentStatus?->code, ['in_transit', 'disposed'])) {
                throw new Exception('Barang tidak bisa diservis pada status ini.');
            }

            // Nota vendor selalu mencantumkan nomor seri. Bila barang ditinggal
            // tanpa serial, unit yang kembali tidak bisa dipastikan unit yang sama.
            if (($data['item_left'] ?? false) && $asset->isMissingRequiredSerial()) {
                throw new Exception("{$asset->product?->serialLabel()} aset {$asset->asset_code} belum diisi; lengkapi dulu sebelum barang ditinggal di tempat servis.");
            }

            $kind = ServiceKind::findOrFail($data['service_kind_id']);
            $vendorId = $data['vendor_id'] ?? null;
            $vendorName = $vendorId ? Vendor::find($vendorId)?->name : 'Internal IT';

            $rec = AssetServiceModel::create([
                'asset_id' => $asset->id,
                'service_kind_id' => $kind->id,
                'performed_by' => $data['performed_by'],
                'vendor_id' => $vendorId,
                'item_left' => $data['item_left'] ?? false,
                'started_at' => $data['started_at'] ?? now(),
                'expected_at' => $data['expected_at'] ?? null,
                'ticket_ref' => $data['ticket_ref'] ?? null,
                'complaint' => $data['complaint'],
                'spec_before' => $asset->effective_specifications,
                'spec_after' => $data['spec_after'] ?? null,
                'status' => 'open',
                'opened_by' => auth()->id(),
            ]);

            if ($rec->item_left) {
                $servisStatus = AssetStatus::where('code', 'service')->firstOrFail();
                $this->transactionService->changeStatus($asset, [
                    'status_id' => $servisStatus->id,
                    'notes' => "Servis #{$rec->id}: {$kind->name} · {$vendorName}",
                    'service_id' => $rec->id,
                    'transaction_date' => $rec->started_at,
                ]);
            }

            return $rec;
        });
    }

    /**
     * Selesaikan catatan servis.
     */
    public function close(AssetServiceModel $rec, array $data)
    {
        return DB::transaction(function () use ($rec, $data) {
            $rec = AssetServiceModel::where('id', $rec->id)->lockForUpdate()->first();
            $asset = Asset::where('id', $rec->asset_id)->lockForUpdate()->first();

            if ($rec->status !== 'open') {
                throw new Exception('Catatan servis sudah selesai/ditutup.');
            }

            $finishedAt = $data['finished_at'] ?? now();
            if ($finishedAt < $rec->started_at) {
                throw new Exception('Tanggal selesai lebih awal dari tanggal masuk.');
            }

            $result = ServiceResult::findOrFail($data['service_result_id']);
            $kind = $rec->serviceKind;

            $rec->finished_at = $finishedAt;
            $rec->service_result_id = $result->id;
            $rec->work_done = $data['work_done'] ?? null;
            $rec->cost_service = $data['cost_service'] ?? 0;
            $rec->cost_parts = $data['cost_parts'] ?? 0;
            $rec->service_warranty_until = $data['service_warranty_until'] ?? null;
            $rec->condition_after_id = $data['condition_after_id'] ?? null;

            if ($kind->changes_spec) {
                $rec->spec_after = $data['spec_after'] ?? $rec->spec_after;
            }

            $rec->status = 'closed';
            $rec->closed_by = auth()->id();
            $rec->save();

            // Apply spec if applies
            if ($kind->changes_spec && $result->applies_spec) {
                $asset->specifications = $rec->spec_after;
            }

            if ($rec->condition_after_id) {
                $asset->condition_id = $rec->condition_after_id;
            }

            $asset->save();

            // Handle asset status transition
            $statusSetelah = $data['status_setelah'] ?? 'spare'; // 'dipakai', 'spare', 'rusak'
            $servisTxData = [
                'transaction_date' => $finishedAt,
                'service_id' => $rec->id,
                'condition_after_id' => $rec->condition_after_id,
            ];

            if ($statusSetelah === 'dipakai') {
                // Return to user
                if (! $asset->current_holder_id) {
                    throw new Exception('Barang tidak dipegang siapapun; pilih Spare / Gudang.');
                }
                $inUseStatus = AssetStatus::where('code', 'in_use')->firstOrFail();
                $servisTxData['status_id'] = $inUseStatus->id;
                $servisTxData['notes'] = "Selesai servis #{$rec->id}, kembali Dipakai.";
                $this->transactionService->changeStatus($asset, $servisTxData);

            } elseif ($statusSetelah === 'spare') {
                // Return to stock
                if ($asset->current_holder_id) {
                    $this->transactionService->return($asset, array_merge($servisTxData, [
                        'from_employee_id' => $asset->current_holder_id,
                        'notes' => "Selesai servis #{$rec->id}, kembali ke Gudang.",
                    ]));
                } else {
                    $spareStatus = AssetStatus::where('code', 'spare')->firstOrFail();
                    $servisTxData['status_id'] = $spareStatus->id;
                    $servisTxData['notes'] = "Selesai servis #{$rec->id}, masuk Gudang.";
                    $this->transactionService->changeStatus($asset, $servisTxData);
                }

            } elseif ($statusSetelah === 'rusak') {
                $brokenStatus = AssetStatus::where('code', 'broken')->firstOrFail();
                $servisTxData['status_id'] = $brokenStatus->id;
                $servisTxData['notes'] = "Servis #{$rec->id}, hasil Rusak.";
                $this->transactionService->changeStatus($asset, $servisTxData);
            }

            // Note rule: "Bila barang tidak ditinggal dan status tidak berubah, tetap dibuat satu transaksi status_change"
            // Wait, changeStatus will do this automatically if we pass service_id! (TransactionService handles it).

            $this->stockCalculator->recompute($asset);

            return $rec;
        });
    }
}
