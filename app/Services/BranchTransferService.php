<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\TransactionType;
use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetTransaction;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Perpindahan unit antar cabang.
 *
 * Sejak satu baris aset berarti satu unit, pengiriman tidak perlu lagi memecah
 * baris: unit yang sama berpindah cabang dan kode asetnya tetap, sehingga
 * riwayat servis, lampiran, dan serah terimanya ikut terbawa.
 */
class BranchTransferService
{
    protected StockCalculator $stockCalculator;

    public function __construct(StockCalculator $stockCalculator)
    {
        $this->stockCalculator = $stockCalculator;
    }

    /**
     * Kirim unit ke cabang lain.
     */
    public function send(Asset $asset, array $data): Asset
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = Asset::withoutGlobalScopes()
                ->with(['product.category', 'currentHolder'])
                ->where('id', $asset->id)
                ->lockForUpdate()
                ->first();

            $targetBranchId = $data['to_branch_id'];

            if ($targetBranchId == $asset->branch_id) {
                throw new Exception('Cabang tujuan tidak boleh sama dengan cabang asal.');
            }

            if ($asset->retired_at) {
                throw new Exception('Unit ini sudah dilepas dan tidak bisa dikirim.');
            }

            if ($asset->current_holder_id) {
                throw new Exception('Barang masih dipegang '.$asset->currentHolder?->name.'. Tarik kembali terlebih dahulu.');
            }

            if (! $asset->currentStatus || ! $asset->currentStatus->transferable) {
                throw new Exception('Status '.$asset->currentStatus?->name.' tidak bisa dikirim antar cabang.');
            }

            // Cabang tujuan mengonfirmasi penerimaan unit tertentu, jadi nomor
            // serinya harus sudah ada sebelum barang berangkat.
            if ($asset->isMissingRequiredSerial()) {
                throw new Exception("Nomor seri aset {$asset->asset_code} belum diisi; lengkapi dulu sebelum dikirim.");
            }

            $inTransitStatus = AssetStatus::where('code', 'in_transit')->firstOrFail();
            $branchAsal = $asset->branch_id;

            $asset->branch_id = $targetBranchId;
            $asset->save();

            AssetTransaction::create([
                'asset_id' => $asset->id,
                'type' => TransactionType::BranchTransfer,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'from_branch_id' => $branchAsal,
                'to_branch_id' => $targetBranchId,
                'status_id' => $inTransitStatus->id,
                'stock_direction' => 'neutral',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->stockCalculator->recompute($asset);

            return $asset->refresh();
        });
    }

    /**
     * Konfirmasi penerimaan di cabang tujuan.
     */
    public function receive(Asset $asset, array $data): Asset
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = Asset::withoutGlobalScopes()->where('id', $asset->id)->lockForUpdate()->first();

            $inTransitStatus = AssetStatus::where('code', 'in_transit')->firstOrFail();
            $spareStatus = AssetStatus::where('code', 'spare')->firstOrFail();

            if ($asset->current_status_id !== $inTransitStatus->id) {
                throw new Exception('Aset ini tidak sedang dalam perjalanan.');
            }

            if (auth()->user()->role === Role::AdminCabang && auth()->user()->branch_id !== $asset->branch_id) {
                throw new Exception('Hanya cabang tujuan yang bisa mengonfirmasi penerimaan.');
            }

            AssetTransaction::create([
                'asset_id' => $asset->id,
                'type' => TransactionType::StatusChange,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'status_id' => $spareStatus->id,
                'condition_after_id' => $data['condition_id'] ?? $asset->condition_id,
                'stock_direction' => 'neutral',
                'notes' => trim('Tiba di cabang tujuan. '.($data['notes'] ?? '')),
                'created_by' => auth()->id(),
            ]);

            $this->stockCalculator->recompute($asset);

            return $asset->refresh();
        });
    }
}
