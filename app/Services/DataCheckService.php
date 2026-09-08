<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetTransaction;
use Illuminate\Support\Facades\DB;

class DataCheckService
{
    /**
     * Run all data integrity checks.
     * Returns an array of issues.
     */
    public function runChecks(): array
    {
        $issues = [];

        // 1. Negative available quantity
        $negativeAvailable = Asset::where('qty_available', '<', 0)->get();
        foreach ($negativeAvailable as $asset) {
            $issues[] = [
                'type' => 'Aset',
                'id' => $asset->id,
                'reference' => $asset->asset_code,
                'issue' => "Kuantitas tersedia negatif ({$asset->qty_available}).",
            ];
        }

        // 2. Qty in > Qty out
        $invalidInOut = Asset::where(DB::raw('qty_in'), '>', DB::raw('qty_out'))->get();
        foreach ($invalidInOut as $asset) {
            $issues[] = [
                'type' => 'Aset',
                'id' => $asset->id,
                'reference' => $asset->asset_code,
                'issue' => "Kuantitas masuk ({$asset->qty_in}) lebih besar dari kuantitas keluar ({$asset->qty_out}).",
            ];
        }

        // 3. Qty available + qty held + qty writeoff != quantity
        $mismatchedTotals = Asset::where(DB::raw('qty_available + (qty_out - qty_in) + qty_writeoff'), '!=', DB::raw('quantity'))->get();
        foreach ($mismatchedTotals as $asset) {
            $issues[] = [
                'type' => 'Aset',
                'id' => $asset->id,
                'reference' => $asset->asset_code,
                'issue' => "Total kuantitas tidak seimbang. Rumus: Tersedia + Dipegang + Dilepas != Total Awal.",
            ];
        }

        return $issues;
    }
}
