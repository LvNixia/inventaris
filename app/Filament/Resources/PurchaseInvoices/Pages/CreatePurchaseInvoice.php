<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Services\PurchaseInvoiceService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

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
