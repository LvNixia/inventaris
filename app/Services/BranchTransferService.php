<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetTransaction;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use App\Enums\TransactionType;
use Exception;

class BranchTransferService
{
    protected StockCalculator $stockCalculator;
    protected AssetCodeGenerator $assetCodeGenerator;

    public function __construct(StockCalculator $stockCalculator, AssetCodeGenerator $assetCodeGenerator)
    {
        $this->stockCalculator = $stockCalculator;
        $this->assetCodeGenerator = $assetCodeGenerator;
    }

    /**
     * Send asset to another branch.
     */
    public function send(Asset $asset, array $data)
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = Asset::where('id', $asset->id)->lockForUpdate()->first();

            $targetBranchId = $data['to_branch_id'];
            $quantity = $data['quantity'] ?? 1;

            if ($targetBranchId == $asset->branch_id) {
                throw new Exception("Cabang tujuan tidak boleh sama dengan cabang asal");
            }

            // Validasi saldo pemegang = 0 (karena ini barang gudang yang dikirim)
            $heldQuantity = $asset->qty_out - $asset->qty_in;
            if ($heldQuantity > 0) {
                throw new Exception("Barang masih dipegang seseorang. Tarik kembali terlebih dahulu.");
            }

            if ($quantity > $asset->qty_available) {
                throw new Exception("Jumlah melebihi stok di gudang ({$asset->qty_available})");
            }

            $inTransitStatus = AssetStatus::where('code', 'in_transit')->firstOrFail();

            if ($quantity < $asset->quantity) {
                // Split asset for mass asset
                $asset->quantity -= $quantity;
                $asset->save();

                $targetBranch = Branch::findOrFail($targetBranchId);
                $newCode = $this->assetCodeGenerator->generate($asset->category, $targetBranch);

                $target = $asset->replicate();
                $target->asset_code = $newCode;
                $target->branch_id = $targetBranchId;
                $target->quantity = $quantity;
                $target->qty_out = 0;
                $target->qty_in = 0;
                $target->qty_available = 0; // will be recomputed
                $target->split_from_asset_id = $asset->id;
                $target->current_status_id = $inTransitStatus->id;
                $target->save();

            } else {
                $target = $asset;
                $target->branch_id = $targetBranchId;
                $target->current_status_id = $inTransitStatus->id;
                $target->save();
            }

            AssetTransaction::create([
                'asset_id' => $target->id,
                'type' => TransactionType::BranchTransfer,
                'transaction_date' => $data['transaction_date'] ?? now(),
                'quantity' => $quantity,
                'from_branch_id' => $asset->branch_id,
                'to_branch_id' => $targetBranchId,
                'status_id' => $inTransitStatus->id,
                'stock_direction' => 'neutral',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->stockCalculator->recompute($asset);
            if ($target->id !== $asset->id) {
                $this->stockCalculator->recompute($target);
            }

            return $target;
        });
    }

    /**
     * Confirm receipt at destination branch.
     */
    public function receive(Asset $asset, array $data)
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = Asset::where('id', $asset->id)->lockForUpdate()->first();

            $inTransitStatus = AssetStatus::where('code', 'in_transit')->firstOrFail();
            $spareStatus = AssetStatus::where('code', 'spare')->firstOrFail();

            if ($asset->current_status_id !== $inTransitStatus->id) {
                throw new Exception("Aset ini tidak sedang dalam perjalanan");
            }

            if (auth()->user()->role === \App\Enums\Role::AdminCabang && auth()->user()->branch_id !== $asset->branch_id) {
                throw new Exception("Hanya cabang tujuan yang bisa mengonfirmasi penerimaan");
            }

            AssetTransaction::create([
                'asset_id' => $asset->id,
                'type' => TransactionType::StatusChange,
                'transaction_date' => $data['transaction_date'] ?? now(),
                'quantity' => $asset->quantity,
                'status_id' => $spareStatus->id,
                'condition_after_id' => $data['condition_id'] ?? $asset->condition_id,
                'stock_direction' => 'neutral',
                'notes' => 'Tiba di cabang tujuan: ' . ($data['notes'] ?? ''),
                'created_by' => auth()->id(),
            ]);

            if (isset($data['condition_id'])) {
                $asset->condition_id = $data['condition_id'];
                $asset->save();
            }

            $this->stockCalculator->recompute($asset);

            return $asset;
        });
    }
}
