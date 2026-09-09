<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\PaymentTerm;
use App\Models\PurchaseBatch;
use App\Models\PurchaseInvoice;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tagihan vendor.
 *
 * Jatuh tempo dihitung dari syarat pembayaran yang disalin ke faktur, bukan
 * dibaca ulang dari vendor saat tampil: mengubah syarat vendor tidak boleh
 * menggeser jatuh tempo faktur yang sudah terbit.
 */
class PurchaseInvoiceService
{
    /**
     * Catat faktur beserta penerimaan yang ditagihnya.
     *
     * @param  array<int, int>  $goodsReceiptIds
     *
     * @throws Exception
     */
    public function create(array $data, array $goodsReceiptIds = []): PurchaseInvoice
    {
        return DB::transaction(function () use ($data, $goodsReceiptIds) {
            $term = PaymentTerm::find($data['payment_term_id'] ?? null);
            $tanggal = Carbon::parse($data['invoice_date']);

            $data['due_date'] = $term
                ? $term->dueDateFrom($tanggal)->toDateString()
                : $tanggal->toDateString();
            $data['paid_amount'] = 0;
            $data['status'] = 'unpaid';
            $data['created_by'] = auth()->id();

            $invoice = PurchaseInvoice::create($data);

            $this->tautkanPenerimaan($invoice, $goodsReceiptIds);

            return $invoice->refresh();
        });
    }

    /**
     * Tautkan penerimaan ke faktur, lalu isikan nomor fakturnya ke batch
     * pembelian yang lahir dari penerimaan tersebut.
     *
     * Dengan begitu `Asset::invoice_number` yang sudah dipakai laporan dan
     * ekspor ikut terisi tanpa perubahan kode apa pun.
     *
     * @param  array<int, int>  $goodsReceiptIds
     *
     * @throws Exception
     */
    public function tautkanPenerimaan(PurchaseInvoice $invoice, array $goodsReceiptIds): void
    {
        foreach (array_filter($goodsReceiptIds) as $grId) {
            $gr = GoodsReceipt::withoutGlobalScopes()->find($grId);

            if (! $gr) {
                continue;
            }

            if ($gr->status !== 'received') {
                throw new Exception("Penerimaan {$gr->gr_number} belum disetujui, jadi belum bisa ditagihkan.");
            }

            if ($gr->vendor_id && (int) $gr->vendor_id !== (int) $invoice->vendor_id) {
                throw new Exception("Penerimaan {$gr->gr_number} berasal dari vendor lain.");
            }

            $invoice->receipts()->firstOrCreate(
                ['goods_receipt_id' => $gr->id],
                ['amount' => $this->nilaiPenerimaan($gr)],
            );

            PurchaseBatch::where('goods_receipt_id', $gr->id)
                ->whereNull('invoice_number')
                ->update(['invoice_number' => $invoice->invoice_number]);
        }
    }

    /**
     * Hitung ulang status faktur dari pembayaran yang sudah masuk.
     */
    public function recalculate(PurchaseInvoice $invoice): PurchaseInvoice
    {
        $terbayar = (float) $invoice->allocations()
            ->whereHas('vendorPayment', fn ($q) => $q->withoutGlobalScopes()->whereNull('cancelled_at'))
            ->sum('amount');

        $status = match (true) {
            $invoice->status === 'cancelled' => 'cancelled',
            $terbayar <= 0 => 'unpaid',
            $terbayar < (float) $invoice->total_amount => 'partial',
            default => 'paid',
        };

        $invoice->update([
            'paid_amount' => $terbayar,
            'status' => $status,
        ]);

        return $invoice->refresh();
    }

    /**
     * Batalkan faktur. Hanya boleh selama belum ada pembayaran masuk.
     *
     * @throws Exception
     */
    public function cancel(PurchaseInvoice $invoice, string $alasan): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $alasan) {
            $invoice = PurchaseInvoice::withoutGlobalScopes()
                ->where('id', $invoice->id)
                ->lockForUpdate()
                ->first();

            if (trim($alasan) === '') {
                throw new Exception('Alasan wajib diisi.');
            }

            if ($invoice->status === 'cancelled') {
                throw new Exception('Faktur ini sudah dibatalkan.');
            }

            if ((float) $invoice->paid_amount > 0) {
                throw new Exception('Faktur sudah menerima pembayaran; batalkan pembayarannya terlebih dahulu.');
            }

            $invoice->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $alasan,
            ]);

            return $invoice->refresh();
        });
    }

    /**
     * Nilai sebuah penerimaan, dijumlahkan dari harga tiap unitnya.
     */
    protected function nilaiPenerimaan(GoodsReceipt $gr): float
    {
        $gr->loadMissing('items.purchaseOrderItem');

        return $gr->items->sum(
            fn ($item): float => (float) ($item->unit_price ?? $item->purchaseOrderItem?->unit_price ?? 0)
        );
    }
}
