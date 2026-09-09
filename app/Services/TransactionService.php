<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetTransaction;
use App\Models\Condition;
use App\Models\DisposalReason;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Pergerakan satu unit aset. Setiap metode di sini menyangkut tepat satu unit,
 * jadi tidak ada lagi pemeriksaan jumlah terhadap sisa stok.
 */
class TransactionService
{
    protected StockCalculator $stockCalculator;

    public function __construct(StockCalculator $stockCalculator)
    {
        $this->stockCalculator = $stockCalculator;
    }

    /**
     * Tarik unit kembali dari pemegangnya ke gudang.
     */
    public function return(Asset $asset, array $data): AssetTransaction
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = Asset::withoutGlobalScopes()->where('id', $asset->id)->lockForUpdate()->first();

            if (! $asset->current_holder_id) {
                throw new Exception('Unit ini tidak sedang dipegang siapa pun.');
            }

            $fromEmployeeId = $data['from_employee_id'] ?? $asset->current_holder_id;

            if ((int) $fromEmployeeId !== (int) $asset->current_holder_id) {
                throw new Exception('Unit ini dipegang karyawan lain; periksa kembali pilihan Anda.');
            }

            $status = AssetStatus::where('code', 'spare')->firstOrFail();

            $transaction = AssetTransaction::create([
                'asset_id' => $asset->id,
                'type' => TransactionType::Return,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'from_employee_id' => $fromEmployeeId,
                'to_employee_id' => $data['to_employee_id'] ?? null,
                'status_id' => $status->id,
                'stock_direction' => 'in',
                'condition_after_id' => $data['condition_after_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->stockCalculator->recompute($asset);

            return $transaction;
        });
    }

    /**
     * Ubah status unit tanpa memindahkan kepemilikannya.
     */
    public function changeStatus(Asset $asset, array $data): AssetTransaction
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = Asset::withoutGlobalScopes()->where('id', $asset->id)->lockForUpdate()->first();

            $status = AssetStatus::findOrFail($data['status_id']);

            if ($asset->current_status_id == $status->id && empty($data['service_id'])) {
                throw new Exception('Status tidak berubah.');
            }

            if ($status->code === 'in_use' && empty($asset->current_holder_id)) {
                throw new Exception('Unit tidak sedang dipegang siapa pun; gunakan surat serah terima.');
            }

            $transaction = AssetTransaction::create([
                'asset_id' => $asset->id,
                'type' => TransactionType::StatusChange,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'status_id' => $status->id,
                'stock_direction' => 'neutral',
                'to_employee_id' => $asset->current_holder_id,
                'condition_after_id' => $data['condition_after_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->stockCalculator->recompute($asset);

            return $transaction;
        });
    }

    /**
     * Hapus buku satu unit: dijual, dibuang, dihibahkan, atau hilang.
     *
     * @return AssetTransaction|array<AssetTransaction>
     */
    public function dispose(Asset $asset, array $data): AssetTransaction|array
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = Asset::withoutGlobalScopes()->where('id', $asset->id)->lockForUpdate()->first();

            if ($asset->retired_at) {
                throw new Exception('Unit ini sudah dilepas sebelumnya.');
            }

            $reason = DisposalReason::findOrFail($data['disposal_reason_id']);
            $transactionDate = $data['transaction_date'] ?? now()->toDateString();
            $transactions = [];

            // Barang hilang saat masih dipegang: tarik dulu agar riwayat
            // pemegangnya tertutup rapi sebelum dihapusbukukan.
            if ($asset->current_holder_id) {
                if (! $reason->is_lost) {
                    throw new Exception('Unit masih dipegang '.$asset->currentHolder?->name.'; tarik kembali terlebih dahulu.');
                }

                $transactions[] = $this->return($asset, [
                    'from_employee_id' => $asset->current_holder_id,
                    'transaction_date' => $transactionDate,
                    'condition_after_id' => Condition::where('code', 'hilang')->value('id'),
                    'notes' => 'Hilang saat dipegang '.$asset->currentHolder?->name,
                ]);

                $asset->refresh();
            }

            $status = AssetStatus::where('code', 'disposed')->firstOrFail();

            $disposalTx = AssetTransaction::create([
                'asset_id' => $asset->id,
                'type' => TransactionType::Disposal,
                'transaction_date' => $transactionDate,
                'status_id' => $status->id,
                'stock_direction' => 'writeoff',
                'disposal_reason_id' => $reason->id,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $transactions[] = $disposalTx;

            $this->stockCalculator->recompute($asset);

            return count($transactions) > 1 ? $transactions : $disposalTx;
        });
    }
}
