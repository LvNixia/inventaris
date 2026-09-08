<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetTransaction;
use App\Models\Condition;
use App\Models\DisposalReason;
use Illuminate\Support\Facades\DB;
use App\Enums\TransactionType;

class TransactionService
{
    protected StockCalculator $stockCalculator;

    public function __construct(StockCalculator $stockCalculator)
    {
        $this->stockCalculator = $stockCalculator;
    }

    /**
     * Tarik kembali (Pengembalian ke gudang)
     */
    public function return(Asset $asset, array $data): AssetTransaction
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = Asset::where('id', $asset->id)->lockForUpdate()->first();
            
            $fromEmployeeId = $data['from_employee_id'];
            $toEmployeeId = $data['to_employee_id'] ?? auth()->user()->employee_id;
            $quantity = $data['quantity'] ?? 1;
            
            // Calculate holder balance
            $qtyOutToHolder = AssetTransaction::where('asset_id', $asset->id)
                ->where('stock_direction', 'out')
                ->where('to_employee_id', $fromEmployeeId)
                ->sum('quantity');
                
            $qtyInFromHolder = AssetTransaction::where('asset_id', $asset->id)
                ->where('stock_direction', 'in')
                ->where('from_employee_id', $fromEmployeeId)
                ->sum('quantity');
                
            $balance = $qtyOutToHolder - $qtyInFromHolder;
            
            if ($quantity <= 0) {
                throw new \Exception("Jumlah harus lebih dari 0");
            }
            if ($quantity > $balance) {
                throw new \Exception("Kembali melebihi yang dipegang (dipegang: $balance)");
            }
            if ($balance <= 0 && $qtyOutToHolder == 0) {
                throw new \Exception("Barang ini belum pernah diserahkan");
            }

            $status = AssetStatus::where('code', 'spare')->firstOrFail();

            $transaction = AssetTransaction::create([
                'asset_id' => $asset->id,
                'type' => TransactionType::Return,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'quantity' => $quantity,
                'from_employee_id' => $fromEmployeeId,
                'to_employee_id' => $toEmployeeId,
                'status_id' => $status->id,
                'stock_direction' => 'in',
                'condition_after_id' => $data['condition_after_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if (!empty($data['condition_after_id'])) {
                $asset->condition_id = $data['condition_after_id'];
                $asset->save();
            }

            $this->stockCalculator->recompute($asset);

            return $transaction;
        });
    }

    /**
     * Ubah status tanpa mengubah unit.
     */
    public function changeStatus(Asset $asset, array $data): AssetTransaction
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = Asset::where('id', $asset->id)->lockForUpdate()->first();
            
            $status = AssetStatus::findOrFail($data['status_id']);
            
            if ($asset->current_status_id == $status->id && empty($data['service_id'])) {
                throw new \Exception("Status tidak berubah");
            }

            if ($status->code === 'in_use' && empty($asset->current_holder_id)) {
                throw new \Exception("Barang tidak sedang dipegang siapa pun; gunakan surat serah terima");
            }

            $transaction = AssetTransaction::create([
                'asset_id' => $asset->id,
                'type' => TransactionType::StatusChange,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'status_id' => $status->id,
                'stock_direction' => 'neutral',
                'to_employee_id' => $asset->current_holder_id, // keep holder
                'condition_after_id' => $data['condition_after_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            if (!empty($data['condition_after_id'])) {
                $asset->condition_id = $data['condition_after_id'];
                $asset->save();
            }

            $this->stockCalculator->recompute($asset);

            return $transaction;
        });
    }

    /**
     * Dilepas / Dijual (Write-off)
     */
    public function dispose(Asset $asset, array $data): AssetTransaction|array
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = Asset::where('id', $asset->id)->lockForUpdate()->first();
            
            $quantity = $data['quantity'] ?? 1;
            if ($quantity <= 0) {
                throw new \Exception("Jumlah harus lebih dari 0");
            }

            $reason = DisposalReason::findOrFail($data['disposal_reason_id']);
            
            $transactions = [];

            // If lost and there is a holder, return it first automatically
            if ($reason->is_lost && $asset->current_holder_id && $quantity > $asset->qty_available) {
                // Return from holder
                $lostCondition = Condition::where('code', 'hilang')->first();
                $transactions[] = $this->return($asset, [
                    'from_employee_id' => $asset->current_holder_id,
                    'quantity' => $quantity,
                    'condition_after_id' => $lostCondition?->id,
                    'notes' => "Hilang saat dipegang " . $asset->currentHolder?->name,
                    'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                ]);
                $asset->refresh();
            }

            if ($quantity > $asset->qty_available) {
                throw new \Exception("Unit masih dipegang, tarik dulu (di gudang: {$asset->qty_available})");
            }

            $status = AssetStatus::where('code', 'disposed')->firstOrFail();

            $disposalTx = AssetTransaction::create([
                'asset_id' => $asset->id,
                'type' => TransactionType::Disposal,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'quantity' => $quantity,
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
