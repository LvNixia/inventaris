<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetTransaction;
use Illuminate\Support\Facades\DB;

class StockCalculator
{
    /**
     * Recompute all stock cached columns and current holder/status for an asset.
     *
     * @param Asset $asset
     * @return void
     */
    public function recompute(Asset $asset): void
    {
        DB::transaction(function () use ($asset) {
            // Re-fetch to ensure we have the latest
            $asset->refresh();

            $qtyOut = AssetTransaction::where('asset_id', $asset->id)
                ->where('stock_direction', 'out')
                ->sum('quantity');

            $qtyIn = AssetTransaction::where('asset_id', $asset->id)
                ->where('stock_direction', 'in')
                ->sum('quantity');

            $qtyWriteoff = AssetTransaction::where('asset_id', $asset->id)
                ->where('stock_direction', 'writeoff')
                ->sum('quantity');

            $asset->qty_out = $qtyOut;
            $asset->qty_in = $qtyIn;
            $asset->qty_writeoff = $qtyWriteoff;
            $asset->qty_available = $asset->quantity - $qtyOut + min($qtyIn, $qtyOut) - $qtyWriteoff;

            // Current holder & status logic
            $lastTx = AssetTransaction::where('asset_id', $asset->id)
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->first();

            if (!$lastTx) {
                $asset->current_status_id = null;
                // Holder defaults to what was manually set on create if any, but usually null
            } else {
                $asset->current_status_id = $lastTx->status_id;
                
                $status = $lastTx->status;
                if ($status && $status->clears_holder) {
                    $asset->current_holder_id = null;
                    $asset->current_user_id = null;
                } elseif ($lastTx->stock_direction === 'out') {
                    $asset->current_holder_id = $lastTx->to_employee_id;
                    $asset->current_user_id = $lastTx->user_employee_id;
                } else {
                    // For service, broken, registered, etc. Keep previous holder unless explicitly set
                    if ($lastTx->to_employee_id) {
                        $asset->current_holder_id = $lastTx->to_employee_id;
                        $asset->current_user_id = $lastTx->user_employee_id;
                    }
                }
            }

            $asset->save();
        });
    }
}
