<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\AssetTransaction;
use App\Models\HandoverDocument;
use Exception;
use Illuminate\Support\Facades\DB;

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
     * Terbitkan surat serah terima: nomor dibuat, unit berpindah ke pemegang,
     * dan rincian barang dibekukan pada barisnya.
     */
    public function issue(HandoverDocument $doc)
    {
        return DB::transaction(function () use ($doc) {
            $doc = HandoverDocument::where('id', $doc->id)->lockForUpdate()->first();

            if ($doc->status !== 'draft') {
                throw new Exception('Hanya dokumen draft yang bisa diterbitkan.');
            }

            // Dikunci berurutan menurut id untuk menghindari deadlock.
            $assetIds = $doc->items()->pluck('asset_id')->sort()->values()->all();

            $assets = Asset::withoutGlobalScopes()
                ->with(['product.category', 'product.brand', 'currentStatus', 'currentHolder', 'condition'])
                ->whereIn('id', $assetIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $inUseStatus = AssetStatus::where('code', 'in_use')->firstOrFail();

            $errors = [];

            foreach ($doc->items as $item) {
                $asset = $assets[$item->asset_id] ?? null;

                if (! $asset) {
                    $errors[] = "Aset #{$item->asset_id} tidak ditemukan.";

                    continue;
                }

                if ($asset->retired_at) {
                    $errors[] = "Aset {$asset->asset_code} sudah dilepas dan tidak bisa diserahkan.";
                }

                if ($asset->current_holder_id) {
                    $errors[] = "Aset {$asset->asset_code} masih dipegang {$asset->currentHolder?->name}; tarik kembali terlebih dahulu.";
                }

                if (! $asset->currentStatus || ! $asset->currentStatus->transferable) {
                    $errors[] = "Status {$asset->currentStatus?->name} pada aset {$asset->asset_code} tidak bisa diserahkan.";
                }

                // Serial boleh kosong saat barang diterima, tetapi harus sudah
                // terisi sebelum unitnya diserahkan: nomor itu yang tercetak di
                // surat dan menjadi bukti unit mana yang berpindah tangan.
                if ($asset->isMissingRequiredSerial()) {
                    $errors[] = "Nomor seri aset {$asset->asset_code} ({$asset->product?->name}) belum diisi; lengkapi dulu sebelum diserahkan.";
                }
            }

            if (! empty($errors)) {
                throw new Exception(implode("\n", $errors));
            }

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

            foreach ($doc->items as $item) {
                $asset = $assets[$item->asset_id];

                $item->item_name = $asset->product?->name;
                $item->serial_number = $asset->serial_number ?? $asset->imei_1;
                $item->specifications = $asset->effective_specifications;
                $item->condition = $asset->condition?->name;
                $item->save();

                // Penerima berada di cabang lain: unitnya ikut berpindah cabang.
                if ($doc->secondParty->branch_id !== $asset->branch_id) {
                    AssetTransaction::create([
                        'asset_id' => $asset->id,
                        'type' => TransactionType::BranchTransfer,
                        'transaction_date' => $doc->document_date,
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

            return $doc;
        });
    }
}
