<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Condition;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseBatch;
use App\Models\PurchaseOrder;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Penerimaan barang: mengubah baris penerimaan menjadi unit aset.
 *
 * Seluruh baris diperiksa lebih dulu sebelum satu pun ditulis, mengikuti pola
 * HandoverService: pengguna melihat semua yang kurang dalam sekali coba, bukan
 * satu per satu.
 */
class GoodsReceiptService
{
    public function __construct(
        protected DocumentNumberGenerator $numberGenerator,
        protected AssetService $assetService,
    ) {}

    /**
     * Setujui penerimaan: terbitkan nomor, buat batch pembelian, lahirkan unit.
     *
     * @throws Exception
     */
    public function receive(GoodsReceipt $gr): GoodsReceipt
    {
        return DB::transaction(function () use ($gr) {
            $gr = GoodsReceipt::withoutGlobalScopes()
                ->with(['branch', 'items.product.category', 'items.purchaseOrderItem', 'purchaseOrder'])
                ->where('id', $gr->id)
                ->lockForUpdate()
                ->first();

            if ($gr->status !== 'draft') {
                throw new Exception('Hanya penerimaan berstatus draf yang bisa disetujui.');
            }

            if ($gr->items->isEmpty()) {
                throw new Exception('Penerimaan belum berisi unit apa pun.');
            }

            $this->pastikanPesananSiapDiterima($gr->purchaseOrder);

            $this->pastikanSah($gr);

            $gr->update([
                'gr_number' => $this->numberGenerator->generate($gr->branch, $gr->receipt_date, 'goods_receipt'),
                'status' => 'received',
                'received_by' => auth()->id(),
                'received_at' => now(),
            ]);

            $batches = $this->buatBatchPembelian($gr);

            foreach ($gr->items as $item) {
                $kunci = $this->kunciBatch($item);

                $asset = $this->assetService->create([
                    'product_id' => $item->product_id,
                    'purchase_batch_id' => $batches[$kunci]->id,
                    'branch_id' => $gr->branch_id,
                    'serial_number' => blank($item->serial_number) ? null : $item->serial_number,
                    'imei_1' => blank($item->imei_1) ? null : $item->imei_1,
                    'imei_2' => blank($item->imei_2) ? null : $item->imei_2,
                    'condition_id' => $item->condition_id ?? Condition::where('code', 'baik')->value('id'),
                    'notes' => $item->notes,
                ]);

                $item->update(['asset_id' => $asset->id]);
            }

            $this->perbaruiStatusPesanan($gr->purchaseOrder);

            return $gr->refresh();
        });
    }

    /**
     * Batalkan penerimaan. Hanya boleh selama unit yang lahir darinya belum
     * bergerak sama sekali, sama seperti aturan pembatalan surat serah terima.
     *
     * @throws Exception
     */
    public function cancel(GoodsReceipt $gr, string $alasan): GoodsReceipt
    {
        return DB::transaction(function () use ($gr, $alasan) {
            $gr = GoodsReceipt::withoutGlobalScopes()
                ->with('items', 'purchaseOrder')
                ->where('id', $gr->id)
                ->lockForUpdate()
                ->first();

            if ($gr->status !== 'received') {
                throw new Exception('Hanya penerimaan yang sudah disetujui yang bisa dibatalkan.');
            }

            if (trim($alasan) === '') {
                throw new Exception('Alasan wajib diisi.');
            }

            $assetIds = $gr->items->pluck('asset_id')->filter()->all();

            $units = Asset::withoutGlobalScopes()->whereIn('id', $assetIds)->get();

            // Lampiran ikut diperiksa: berkasnya menunjuk ke aset lewat foreign
            // key, jadi tanpa pemeriksaan ini penghapusan gagal di tengah jalan
            // dengan pesan basis data yang tidak bisa dipahami pengguna.
            $terpakai = $units->filter(fn (Asset $unit): bool => $unit->assetTransactions()->exists()
                || $unit->handoverItems()->exists()
                || $unit->assetServices()->exists()
                || $unit->attachments()->exists());

            if ($terpakai->isNotEmpty()) {
                throw new Exception(
                    'Penerimaan tidak bisa dibatalkan karena '.$terpakai->count().' unit sudah dipakai: '
                    .$terpakai->take(5)->pluck('asset_code')->join(', ')
                    .($terpakai->count() > 5 ? ', dan lainnya' : '').'.'
                );
            }

            // Tautan dilepas dulu agar penghapusan aset tidak tertahan foreign key.
            GoodsReceiptItem::where('goods_receipt_id', $gr->id)->update(['asset_id' => null]);

            Asset::withoutGlobalScopes()->whereIn('id', $assetIds)->delete();

            PurchaseBatch::where('goods_receipt_id', $gr->id)
                ->whereDoesntHave('assets')
                ->delete();

            $gr->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $alasan,
            ]);

            $this->perbaruiStatusPesanan($gr->purchaseOrder);

            return $gr->refresh();
        });
    }

    /**
     * Penerimaan hanya boleh menumpang pesanan yang memang sedang berjalan.
     *
     * Tanpa penjagaan ini, penerimaan berstatus draf yang dibuat sebelum
     * pesanannya dibatalkan tetap bisa disetujui: unit asetnya lahir, sementara
     * pesanannya tetap tercatat batal sehingga penerimaan itu tidak terlihat
     * di mana pun.
     *
     * @throws Exception
     */
    protected function pastikanPesananSiapDiterima(?PurchaseOrder $po): void
    {
        if (! $po) {
            return; // Penerimaan tanpa pesanan memang diizinkan.
        }

        if ($po->status === 'cancelled') {
            throw new Exception("Pesanan {$po->po_number} sudah dibatalkan, jadi barangnya tidak bisa diterima.");
        }

        if (! $po->isApproved()) {
            throw new Exception("Pesanan {$po->po_number} belum disetujui; setujui dulu sebelum menerima barangnya.");
        }
    }

    /**
     * Periksa seluruh baris; kumpulkan semua kesalahan lalu laporkan sekaligus.
     *
     * @throws Exception
     */
    protected function pastikanSah(GoodsReceipt $gr): void
    {
        $errors = [];
        $serialTerpakai = [];

        foreach ($gr->items as $urutan => $item) {
            $nomor = $urutan + 1;
            $produk = $item->product;
            $sebutan = $produk?->serialLabel() ?? 'Nomor Seri';

            if ($produk?->requiresSerialNumber() && blank($item->serial_number)) {
                $errors[] = "Unit {$nomor} ({$produk?->name}): {$sebutan} belum diisi.";

                continue;
            }

            if (blank($item->serial_number)) {
                continue;
            }

            $serial = mb_strtolower($item->serial_number);

            if (in_array($serial, $serialTerpakai, true)) {
                $errors[] = "Unit {$nomor}: {$sebutan} \"{$item->serial_number}\" muncul dua kali pada penerimaan ini.";

                continue;
            }

            $serialTerpakai[] = $serial;

            if (Asset::withoutGlobalScopes()->where('serial_number', $item->serial_number)->exists()) {
                $errors[] = "Unit {$nomor}: {$sebutan} \"{$item->serial_number}\" sudah terdaftar pada aset lain.";
            }
        }

        $errors = array_merge($errors, $this->periksaSisaPesanan($gr));

        if ($errors !== []) {
            throw new Exception(implode("\n", $errors));
        }
    }

    /**
     * Jumlah diterima tidak boleh melebihi sisa yang dipesan.
     *
     * @return array<int, string>
     */
    protected function periksaSisaPesanan(GoodsReceipt $gr): array
    {
        $errors = [];

        $perBarisPo = $gr->items
            ->filter(fn (GoodsReceiptItem $item): bool => $item->purchase_order_item_id !== null)
            ->groupBy('purchase_order_item_id');

        foreach ($perBarisPo as $poItemId => $items) {
            $poItem = $items->first()->purchaseOrderItem;

            if (! $poItem) {
                continue;
            }

            // Penerimaan lain yang sudah disetujui untuk baris pesanan yang sama.
            $sudahDiterima = GoodsReceiptItem::where('purchase_order_item_id', $poItemId)
                ->where('goods_receipt_id', '!=', $gr->id)
                ->whereHas('goodsReceipt', fn ($q) => $q->withoutGlobalScopes()->where('status', 'received'))
                ->count();

            $sisa = (int) $poItem->quantity - $sudahDiterima;

            if ($items->count() > $sisa) {
                $errors[] = "Barang {$poItem->product?->name}: diterima {$items->count()} unit, "
                    ."sisa pesanan hanya {$sisa} dari {$poItem->quantity} unit.";
            }
        }

        return $errors;
    }

    /**
     * Satu batch pembelian per kombinasi barang, harga, dan garansi.
     *
     * Sepuluh unit dari satu baris pesanan menghasilkan satu batch, bukan
     * sepuluh, sehingga nilai aset tetap terbaca sebagai satu pembelian.
     *
     * @return array<string, PurchaseBatch>
     */
    protected function buatBatchPembelian(GoodsReceipt $gr): array
    {
        $batches = [];

        foreach ($gr->items as $item) {
            $kunci = $this->kunciBatch($item);

            if (isset($batches[$kunci])) {
                continue;
            }

            $harga = $item->unit_price ?? $item->purchaseOrderItem?->unit_price;
            $garansi = $item->warranty_months ?? $item->purchaseOrderItem?->warranty_months;

            $batches[$kunci] = PurchaseBatch::create([
                'product_id' => $item->product_id,
                'goods_receipt_id' => $gr->id,
                'branch_id' => $gr->branch_id,
                'vendor_id' => $gr->vendor_id,
                // Nomor faktur menyusul saat tagihan vendor dicatat pada Tahap C.
                'invoice_number' => null,
                'purchase_date' => $gr->receipt_date,
                'unit_price' => $harga,
                'warranty_months' => $garansi,
                'warranty_until' => $garansi
                    ? $gr->receipt_date->copy()->addMonths((int) $garansi)->toDateString()
                    : null,
                'created_by' => auth()->id(),
            ]);
        }

        return $batches;
    }

    /**
     * Unit dengan barang, harga, dan garansi sama berbagi satu batch.
     */
    protected function kunciBatch(GoodsReceiptItem $item): string
    {
        $harga = $item->unit_price ?? $item->purchaseOrderItem?->unit_price ?? '0';
        $garansi = $item->warranty_months ?? $item->purchaseOrderItem?->warranty_months ?? '0';

        return $item->product_id.'|'.$harga.'|'.$garansi;
    }

    /**
     * Naikkan status pesanan mengikuti banyaknya unit yang sudah diterima.
     */
    protected function perbaruiStatusPesanan(?PurchaseOrder $po): void
    {
        if (! $po || in_array($po->status, ['cancelled'], true)) {
            return;
        }

        $po->loadMissing('items');

        $dipesan = 0;
        $diterima = 0;

        foreach ($po->items as $poItem) {
            $dipesan += (int) $poItem->quantity;

            $diterima += GoodsReceiptItem::where('purchase_order_item_id', $poItem->id)
                ->whereHas('goodsReceipt', fn ($q) => $q->withoutGlobalScopes()->where('status', 'received'))
                ->count();
        }

        $status = match (true) {
            $diterima <= 0 => 'approved',
            $diterima >= $dipesan => 'completed',
            default => 'partial_receipt',
        };

        if ($po->status !== $status) {
            $po->update(['status' => $status]);
        }
    }
}
