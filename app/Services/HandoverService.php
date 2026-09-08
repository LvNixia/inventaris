<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetTransaction;
use App\Models\HandoverDocument;
use Illuminate\Support\Facades\DB;
use App\Enums\TransactionType;
use Exception;

class HandoverService
{
    protected DocumentNumberGenerator $numberGenerator;
    protected StockCalculator $stockCalculator;

    public function __construct(DocumentNumberGenerator $numberGenerator, StockCalculator $stockCalculator)
    {
        $this->numberGenerator = $numberGenerator;
        $this->stockCalculator = $stockCalculator;
    }

    /**
     * Issue a handover document from draft to issued.
     * Generates document number, executes transactions, locks stock, and generates PDF.
     */
    public function issue(HandoverDocument $doc)
    {
        return DB::transaction(function () use ($doc) {
            $doc = HandoverDocument::where('id', $doc->id)->lockForUpdate()->first();

            if ($doc->status !== 'draft') {
                throw new Exception("Hanya dokumen draft yang bisa diterbitkan.");
            }

            // Lock all assets to prevent race conditions, ordered by id to prevent deadlock
            $assetIds = $doc->items()->pluck('asset_id')->toArray();
            sort($assetIds);
            
            $assets = Asset::whereIn('id', $assetIds)->lockForUpdate()->get()->keyBy('id');
            $inUseStatus = AssetStatus::where('code', 'in_use')->firstOrFail();

            $errors = [];
            
            foreach ($doc->items as $item) {
                $asset = $assets[$item->asset_id] ?? null;
                if (!$asset) {
                    $errors[] = "Aset #{$item->asset_id} tidak ditemukan.";
                    continue;
                }
                
                if ($asset->qty_available < $item->quantity) {
                    $errors[] = "Aset {$asset->asset_code} melebihi sisa tersedia (sisa: {$asset->qty_available}).";
                }
                
                if (!$asset->currentStatus || !$asset->currentStatus->transferable) {
                    $errors[] = "Status {$asset->currentStatus?->name} pada aset {$asset->asset_code} tidak bisa diserahkan.";
                }
            }

            if (!empty($errors)) {
                throw new Exception(implode("\n", $errors));
            }

            // Snapshot employee details
            $doc->first_party_name = $doc->firstParty->name;
            $doc->first_party_position = $doc->firstParty->position?->name;
            $doc->first_party_division = $doc->firstParty->division?->name;

            $doc->second_party_name = $doc->secondParty->name;
            $doc->second_party_position = $doc->secondParty->position?->name;
            $doc->second_party_division = $doc->secondParty->division?->name;

            if ($doc->witness) {
                $doc->witness_name = $doc->witness->name;
                $doc->witness_position = $doc->witness->position?->name;
                $doc->witness_division = $doc->witness->division?->name;
            }

            $doc->document_number = $this->numberGenerator->generate($doc->branch, $doc->document_date);
            $doc->status = 'issued';
            $doc->issued_at = now();
            $doc->save();

            // Process transactions
            foreach ($doc->items as $item) {
                $asset = $assets[$item->asset_id];

                // Snapshot item details
                $item->item_name = $asset->brand?->name . ' ' . $asset->model;
                $item->serial_number = $asset->serial_number ?? $asset->imei_1;
                $item->specifications = $asset->specifications;
                $item->condition = $asset->condition?->name;
                $item->save();

                // Branch transfer logic if admin changes branch
                if ($doc->secondParty->branch_id !== $asset->branch_id) {
                    AssetTransaction::create([
                        'asset_id' => $asset->id,
                        'type' => TransactionType::BranchTransfer,
                        'transaction_date' => $doc->document_date,
                        'quantity' => $item->quantity,
                        'from_branch_id' => $asset->branch_id,
                        'to_branch_id' => $doc->secondParty->branch_id,
                        'to_employee_id' => $doc->second_party_id,
                        'status_id' => $inUseStatus->id,
                        'stock_direction' => 'neutral',
                        'handover_document_id' => $doc->id,
                        'created_by' => auth()->id(),
                    ]);
                    $asset->branch_id = $doc->secondParty->branch_id;
                    $asset->save();
                }

                AssetTransaction::create([
                    'asset_id' => $asset->id,
                    'type' => TransactionType::Handover,
                    'transaction_date' => $doc->document_date,
                    'quantity' => $item->quantity,
                    'from_employee_id' => $doc->first_party_id,
                    'to_employee_id' => $doc->second_party_id,
                    'user_employee_id' => $item->user_employee_id,
                    'status_id' => $inUseStatus->id,
                    'stock_direction' => 'out',
                    'handover_document_id' => $doc->id,
                    'created_by' => auth()->id(),
                ]);

                $this->stockCalculator->recompute($asset);
            }

            // PDF generation will be handled separately (sync or queued)
            return $doc;
        });
    }
}
