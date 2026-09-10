<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseInvoices\Schemas\PurchaseInvoiceForm;
use App\Models\GoodsReceipt;
use App\Services\PurchaseInvoiceService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    /**
     * Faktur bisa dibuka langsung dari daftar penerimaan.
     *
     * Vendor, cabang, syarat pembayaran, nilai, dan PPN-nya diambil dari
     * penerimaan yang ditunjuk `goods_receipt_id` — dan lewat penerimaan itu
     * dari pesanannya. Yang tersisa diketik manusia hanya nomor fakturnya.
     */
    protected function afterFill(): void
    {
        $gr = GoodsReceipt::find(request()->integer('goods_receipt_id'));

        if (! $gr) {
            return;
        }

        $warisan = PurchaseInvoiceForm::warisanPenerimaan([$gr->id]);

        $this->data['vendor_id'] = $gr->vendor_id;
        $this->data['goods_receipt_ids'] = [$gr->id];
        $this->data['branch_id'] = $warisan['branch_id'] ?? $gr->branch_id;
        $this->data['payment_term_id'] = $warisan['payment_term_id'];
        $this->data['subtotal'] = $warisan['subtotal'];
        $this->data['tax'] = $warisan['tax'];
        $this->data['total_amount'] = $warisan['subtotal'] + $warisan['tax'];
    }

    /**
     * Jatuh tempo, status, dan penautan penerimaan diurus service supaya
     * aturannya sama baik lewat antarmuka maupun lewat kode.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $grIds = $data['goods_receipt_ids'] ?? [];
        unset($data['goods_receipt_ids']);

        return app(PurchaseInvoiceService::class)->create($data, $grIds);
    }
}
