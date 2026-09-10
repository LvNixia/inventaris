<?php

namespace App\Filament\Resources\VendorPayments\Pages;

use App\Filament\Resources\VendorPayments\VendorPaymentResource;
use App\Models\PurchaseInvoice;
use App\Services\VendorPaymentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateVendorPayment extends CreateRecord
{
    protected static string $resource = VendorPaymentResource::class;

    /**
     * Pembayaran bisa dibuka langsung dari daftar faktur.
     *
     * Vendor, cabang, alokasi, dan nilainya diambil dari faktur yang ditunjuk
     * `purchase_invoice_id`, jadi yang tersisa diisi manusia hanya metode dan
     * nomor buktinya.
     */
    protected function afterFill(): void
    {
        $invoice = PurchaseInvoice::find(request()->integer('purchase_invoice_id'));

        if (! $invoice || $invoice->outstanding <= 0) {
            return;
        }

        $this->data['vendor_id'] = $invoice->vendor_id;
        $this->data['branch_id'] = $invoice->branch_id;
        $this->data['amount'] = $invoice->outstanding;
        $this->data['allocations'] = [
            (string) Str::uuid() => [
                'purchase_invoice_id' => $invoice->id,
                'amount' => $invoice->outstanding,
            ],
        ];
    }

    /**
     * Nomor pembayaran, pemeriksaan alokasi, dan pembaruan status faktur
     * seluruhnya diurus service dalam satu transaksi.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $alokasi = $data['allocations'] ?? [];
        unset($data['allocations']);

        return app(VendorPaymentService::class)->pay($data, $alokasi);
    }
}
