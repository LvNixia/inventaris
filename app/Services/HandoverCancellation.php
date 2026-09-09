<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetTransaction;
use App\Models\HandoverDocument;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Pembatalan surat serah terima yang sudah terbit.
 *
 * Pembatalan hanya boleh selama unitnya belum bergerak lagi setelah surat ini.
 * Bila sudah ditarik, diservis, dipindah, atau dilepas, riwayatnya tidak bisa
 * dibalik begitu saja dan harus diperbaiki lewat koreksi.
 */
class HandoverCancellation
{
    protected StockCalculator $stockCalculator;

    protected PdfRenderer $pdfRenderer;

    public function __construct(StockCalculator $stockCalculator, PdfRenderer $pdfRenderer)
    {
        $this->stockCalculator = $stockCalculator;
        $this->pdfRenderer = $pdfRenderer;
    }

    public function cancel(HandoverDocument $doc, string $reason): HandoverDocument
    {
        return DB::transaction(function () use ($doc, $reason) {
            $doc = HandoverDocument::where('id', $doc->id)->lockForUpdate()->first();

            if ($doc->status !== 'issued') {
                throw new Exception('Hanya surat terbit yang bisa dibatalkan.');
            }

            if (trim($reason) === '') {
                throw new Exception('Alasan wajib diisi.');
            }

            // Dikunci berurutan menurut id untuk menghindari deadlock.
            $assetIds = $doc->items()->pluck('asset_id')->sort()->values()->all();

            $assets = Asset::withoutGlobalScopes()
                ->with(['currentHolder', 'product'])
                ->whereIn('id', $assetIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $spareStatus = AssetStatus::where('code', 'spare')->firstOrFail();

            $errors = [];

            foreach ($doc->items as $item) {
                $asset = $assets[$item->asset_id] ?? null;

                if (! $asset) {
                    $errors[] = "Aset #{$item->asset_id} tidak ditemukan.";

                    continue;
                }

                // Satu baris aset berarti satu unit, jadi pemeriksaannya cukup
                // satu: transaksi terakhir unit ini harus masih milik surat ini.
                $lastTx = AssetTransaction::where('asset_id', $asset->id)
                    ->orderByDesc('transaction_date')
                    ->orderByDesc('id')
                    ->first();

                if ($lastTx && $lastTx->handover_document_id !== $doc->id) {
                    $errors[] = "Aset {$asset->asset_code} sudah bergerak setelah surat ini (transaksi #{$lastTx->id}, {$lastTx->type}). Perbaiki lewat koreksi.";

                    continue;
                }

                if ($asset->current_holder_id !== $doc->second_party_id) {
                    $errors[] = "Aset {$asset->asset_code} tidak lagi dipegang {$doc->second_party_name}. Perbaiki lewat koreksi.";
                }
            }

            if (! empty($errors)) {
                throw new Exception(implode("\n", $errors));
            }

            foreach ($doc->items as $item) {
                $asset = $assets[$item->asset_id];

                AssetTransaction::create([
                    'asset_id' => $asset->id,
                    'type' => TransactionType::Cancellation,
                    'transaction_date' => now()->toDateString(),
                    'from_employee_id' => $doc->second_party_id,
                    'to_employee_id' => $doc->first_party_id,
                    'status_id' => $spareStatus->id,
                    'stock_direction' => 'in',
                    'handover_document_id' => $doc->id,
                    'notes' => "Pembatalan surat {$doc->document_number}: {$reason}",
                    'created_by' => auth()->id(),
                ]);

                // Bila penerbitan surat sempat memindahkan unit ke cabang lain,
                // pembatalannya ikut mengembalikan unit ke cabang asal.
                $branchTransfer = AssetTransaction::where('handover_document_id', $doc->id)
                    ->where('asset_id', $asset->id)
                    ->where('type', TransactionType::BranchTransfer)
                    ->first();

                if ($branchTransfer) {
                    AssetTransaction::create([
                        'asset_id' => $asset->id,
                        'type' => TransactionType::BranchTransfer,
                        'transaction_date' => now()->toDateString(),
                        'from_branch_id' => $branchTransfer->to_branch_id,
                        'to_branch_id' => $branchTransfer->from_branch_id,
                        'status_id' => $spareStatus->id,
                        'stock_direction' => 'neutral',
                        'handover_document_id' => $doc->id,
                        'notes' => "Pengembalian cabang dari pembatalan surat {$doc->document_number}",
                        'created_by' => auth()->id(),
                    ]);

                    $asset->branch_id = $branchTransfer->from_branch_id;
                    $asset->save();
                }

                $this->stockCalculator->recompute($asset);
            }

            $doc->status = 'cancelled';
            $doc->cancelled_at = now();
            $doc->cancel_reason = $reason;
            // Disimpan lebih dulu supaya PDF membaca status dan alasan terbaru.
            $doc->save();

            $doc->cancelled_pdf_path = $this->pdfRenderer->handover($doc, false);
            $doc->save();

            return $doc;
        });
    }
}
