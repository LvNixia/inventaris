<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetTransaction;
use App\Models\HandoverDocument;
use Exception;
use Illuminate\Support\Facades\DB;

class HandoverCancellation
{
    protected StockCalculator $stockCalculator;

    protected PdfRenderer $pdfRenderer;

    public function __construct(StockCalculator $stockCalculator, PdfRenderer $pdfRenderer)
    {
        $this->stockCalculator = $stockCalculator;
        $this->pdfRenderer = $pdfRenderer;
    }

    /**
     * Cancel an issued handover document.
     */
    public function cancel(HandoverDocument $doc, string $reason)
    {
        return DB::transaction(function () use ($doc, $reason) {
            $doc = HandoverDocument::where('id', $doc->id)->lockForUpdate()->first();

            if ($doc->status !== 'issued') {
                throw new Exception('Hanya surat terbit yang bisa dibatalkan');
            }
            if (empty(trim($reason))) {
                throw new Exception('Alasan wajib diisi');
            }

            // Lock all assets
            $assetIds = $doc->items()->pluck('asset_id')->toArray();
            sort($assetIds); // prevent deadlock

            $assets = Asset::whereIn('id', $assetIds)->lockForUpdate()->get()->keyBy('id');
            $spareStatus = AssetStatus::where('code', 'spare')->firstOrFail();

            $errors = [];

            foreach ($doc->items as $item) {
                $asset = $assets[$item->asset_id];

                if ($asset->quantity == 1) {
                    // It's a serialized asset. Ensure no further movement after this handover document.
                    // Meaning the last transaction for this asset MUST belong to this handover document.
                    $lastTx = AssetTransaction::where('asset_id', $asset->id)
                        ->orderByDesc('transaction_date')
                        ->orderByDesc('id')
                        ->first();

                    if ($lastTx && $lastTx->handover_document_id !== $doc->id) {
                        $errors[] = "Aset {$asset->asset_code} sudah bergerak setelah surat ini (transaksi #{$lastTx->id}, ".ucfirst($lastTx->type->value).'). Batalkan lewat koreksi.';
                    }
                } else {
                    // Mass asset: ensure the second party still has enough balance
                    $qtyOut = AssetTransaction::where('asset_id', $asset->id)
                        ->where('stock_direction', 'out')
                        ->where('to_employee_id', $doc->second_party_id)
                        ->sum('quantity');

                    $qtyIn = AssetTransaction::where('asset_id', $asset->id)
                        ->where('stock_direction', 'in')
                        ->where('from_employee_id', $doc->second_party_id)
                        ->sum('quantity');

                    $balance = $qtyOut - $qtyIn;

                    if ($balance < $item->quantity) {
                        $errors[] = "Aset {$asset->asset_code} (massal) sudah dikembalikan atau ditransfer sebagian oleh pemegang. Sisa: {$balance}, butuh dikembalikan: {$item->quantity}. Batalkan lewat koreksi.";
                    }
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
                    'transaction_date' => now(), // Cancellation date or original? Rule says: "transaksi arah in"
                    'quantity' => $item->quantity,
                    'from_employee_id' => $doc->second_party_id,
                    'to_employee_id' => $doc->first_party_id,
                    'status_id' => $spareStatus->id,
                    'stock_direction' => 'in',
                    'handover_document_id' => $doc->id,
                    'notes' => "Pembatalan surat {$doc->document_number}: {$reason}",
                    'created_by' => auth()->id(),
                ]);

                // Note: if there was a branch transfer implicitly created, rule: "pembatalan juga membuat branch_transfer balik ke cabang asal"
                // Check if the original handover did a branch transfer
                $branchTransfer = AssetTransaction::where('handover_document_id', $doc->id)
                    ->where('type', TransactionType::BranchTransfer)
                    ->where('asset_id', $asset->id)
                    ->first();

                if ($branchTransfer) {
                    AssetTransaction::create([
                        'asset_id' => $asset->id,
                        'type' => TransactionType::BranchTransfer,
                        'transaction_date' => now(),
                        'quantity' => $item->quantity,
                        'from_branch_id' => $branchTransfer->to_branch_id,
                        'to_branch_id' => $branchTransfer->from_branch_id,
                        'from_employee_id' => $doc->second_party_id,
                        'to_employee_id' => clone $doc->first_party_id,
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
            $doc->save(); // Save first so the PDF can read the cancelled status and reason

            // Render cancelled PDF
            $cancelledPdfPath = $this->pdfRenderer->handover($doc, false); // Not draft, but it's cancelled so it will have watermark
            $doc->cancelled_pdf_path = $cancelledPdfPath;
            $doc->save();

            return $doc;
        });
    }
}
