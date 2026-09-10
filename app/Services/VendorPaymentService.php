<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\PurchaseInvoice;
use App\Models\VendorPayment;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pembayaran ke vendor.
 *
 * Satu pembayaran dialokasikan ke satu atau beberapa faktur. Jumlah alokasi
 * harus sama persis dengan nilai pembayaran, dan tiap alokasi tidak boleh
 * melebihi sisa fakturnya — aturan itu yang mencegah kelebihan bayar.
 */
class VendorPaymentService
{
    public function __construct(
        protected DocumentNumberGenerator $numberGenerator,
        protected PurchaseInvoiceService $invoiceService,
    ) {}

    /**
     * Catat pembayaran beserta alokasinya.
     *
     * @param  array<int, array{purchase_invoice_id: int, amount: float|string}>  $alokasi
     *
     * @throws Exception
     */
    public function pay(array $data, array $alokasi): VendorPayment
    {
        return DB::transaction(function () use ($data, $alokasi) {
            $alokasi = array_values(array_filter(
                $alokasi,
                fn (array $a): bool => filled($a['purchase_invoice_id'] ?? null) && (float) ($a['amount'] ?? 0) > 0,
            ));

            if ($alokasi === []) {
                throw new Exception('Pembayaran belum dialokasikan ke faktur mana pun.');
            }

            $nilai = (float) $data['amount'];
            $totalAlokasi = array_sum(array_map(fn (array $a): float => (float) $a['amount'], $alokasi));

            // Dibandingkan dengan toleransi satu rupiah agar pembulatan desimal
            // tidak menolak pembayaran yang sebenarnya pas.
            if (abs($totalAlokasi - $nilai) > 0.5) {
                throw new Exception(sprintf(
                    'Jumlah alokasi (Rp %s) tidak sama dengan nilai pembayaran (Rp %s).',
                    number_format($totalAlokasi, 0, ',', '.'),
                    number_format($nilai, 0, ',', '.'),
                ));
            }

            // Faktur dikunci sebelum sisanya diperiksa. Tanpa ini dua pembayaran
            // bersamaan atas faktur yang sama bisa lolos pemeriksaan berdua,
            // lalu keduanya tersimpan — kelebihan bayar yang justru dicegah
            // aturan di bawah.
            $faktur = PurchaseInvoice::withoutGlobalScopes()
                ->whereIn('id', array_column($alokasi, 'purchase_invoice_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $this->pastikanAlokasiSah($alokasi, (int) $data['vendor_id'], $faktur);

            $branch = Branch::findOrFail($data['branch_id']);

            $payment = VendorPayment::create(array_merge($data, [
                'payment_number' => $this->numberGenerator->generate($branch, $data['payment_date'], 'payment'),
                'created_by' => auth()->id(),
            ]));

            foreach ($alokasi as $a) {
                $payment->allocations()->create([
                    'purchase_invoice_id' => $a['purchase_invoice_id'],
                    'amount' => $a['amount'],
                ]);

                $this->invoiceService->recalculate(
                    PurchaseInvoice::withoutGlobalScopes()->find($a['purchase_invoice_id'])
                );
            }

            return $payment->refresh();
        });
    }

    /**
     * Batalkan pembayaran: alokasinya ditarik, status faktur dihitung ulang.
     *
     * Barisnya ditandai batal, bukan dihapus, supaya nomor pembayaran tidak
     * pernah dipakai ulang.
     *
     * @throws Exception
     */
    public function cancel(VendorPayment $payment, string $alasan): VendorPayment
    {
        return DB::transaction(function () use ($payment, $alasan) {
            $payment = VendorPayment::withoutGlobalScopes()
                ->with('allocations')
                ->where('id', $payment->id)
                ->lockForUpdate()
                ->first();

            if (trim($alasan) === '') {
                throw new Exception('Alasan wajib diisi.');
            }

            if ($payment->isCancelled()) {
                throw new Exception('Pembayaran ini sudah dibatalkan.');
            }

            $invoiceIds = $payment->allocations->pluck('purchase_invoice_id')->all();

            $payment->update([
                'cancelled_at' => now(),
                'cancel_reason' => $alasan,
            ]);

            foreach ($invoiceIds as $invoiceId) {
                $this->invoiceService->recalculate(
                    PurchaseInvoice::withoutGlobalScopes()->find($invoiceId)
                );
            }

            return $payment->refresh();
        });
    }

    /**
     * @param  array<int, array{purchase_invoice_id: int, amount: float|string}>  $alokasi
     * @param  Collection<int, PurchaseInvoice>  $faktur  Faktur yang sudah dikunci pemanggil.
     *
     * @throws Exception
     */
    protected function pastikanAlokasiSah(array $alokasi, int $vendorId, $faktur): void
    {
        $errors = [];
        $sudahDipakai = [];

        foreach ($alokasi as $a) {
            $invoiceId = (int) $a['purchase_invoice_id'];

            if (in_array($invoiceId, $sudahDipakai, true)) {
                $errors[] = 'Satu faktur dialokasikan dua kali pada pembayaran yang sama.';

                continue;
            }

            $sudahDipakai[] = $invoiceId;

            $invoice = $faktur->get($invoiceId);

            if (! $invoice) {
                $errors[] = "Faktur #{$invoiceId} tidak ditemukan.";

                continue;
            }

            if ((int) $invoice->vendor_id !== $vendorId) {
                $errors[] = "Faktur {$invoice->invoice_number} berasal dari vendor lain.";

                continue;
            }

            if ($invoice->status === 'cancelled') {
                $errors[] = "Faktur {$invoice->invoice_number} sudah dibatalkan dan tidak bisa dibayar.";

                continue;
            }

            $sisa = $invoice->outstanding;

            if ((float) $a['amount'] - $sisa > 0.5) {
                $errors[] = sprintf(
                    'Alokasi untuk faktur %s (Rp %s) melebihi sisa tagihannya (Rp %s).',
                    $invoice->invoice_number,
                    number_format((float) $a['amount'], 0, ',', '.'),
                    number_format($sisa, 0, ',', '.'),
                );
            }
        }

        if ($errors !== []) {
            throw new Exception(implode("\n", $errors));
        }
    }
}
