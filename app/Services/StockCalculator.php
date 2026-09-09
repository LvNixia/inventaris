<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Menyegarkan keadaan sebuah unit dari transaksi terakhirnya.
 *
 * Sejak satu baris aset berarti satu unit fisik, tidak ada lagi kolom jumlah
 * yang perlu dihitung ulang. Yang tersisa hanya menurunkan status, kondisi, dan
 * pemegang dari transaksi paling akhir — jauh lebih sedikit yang bisa meleset
 * dibanding menjumlahkan arah stok masuk, keluar, dan hapus buku.
 */
class StockCalculator
{
    public function recompute(Asset $asset): void
    {
        DB::transaction(function () use ($asset) {
            $asset->refresh();

            $lastTx = AssetTransaction::where('asset_id', $asset->id)
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->first();

            if (! $lastTx) {
                // Unit yang belum pernah bergerak: statusnya tetap seperti saat
                // didaftarkan, dan belum dipegang siapa pun.
                $asset->current_holder_id = null;
                $asset->current_user_id = null;
                $asset->save();

                return;
            }

            $asset->current_status_id = $lastTx->status_id;

            $status = $lastTx->status;

            if ($status && $status->clears_holder) {
                $asset->current_holder_id = null;
                $asset->current_user_id = null;
            } elseif ($lastTx->stock_direction === 'out') {
                $asset->current_holder_id = $lastTx->to_employee_id;
                $asset->current_user_id = $lastTx->user_employee_id;
            } elseif ($lastTx->to_employee_id) {
                // Servis, rusak, dan perubahan status lain tidak memindahkan
                // kepemilikan; pemegang sebelumnya dipertahankan.
                $asset->current_holder_id = $lastTx->to_employee_id;
                $asset->current_user_id = $lastTx->user_employee_id;
            }

            if ($lastTx->condition_after_id) {
                $asset->condition_id = $lastTx->condition_after_id;
            }

            $asset->retired_at = $lastTx->stock_direction === 'writeoff'
                ? $lastTx->transaction_date
                : null;

            $asset->save();
        });
    }

    /**
     * Unit ini masih ada di gudang dan siap diserahkan.
     */
    public function isAvailable(Asset $asset): bool
    {
        return $asset->current_holder_id === null
            && $asset->retired_at === null
            && (bool) $asset->currentStatus?->transferable;
    }
}
