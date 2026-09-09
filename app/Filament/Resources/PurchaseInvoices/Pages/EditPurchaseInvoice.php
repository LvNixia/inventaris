<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Services\PurchaseInvoiceService;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['goods_receipt_ids'] = $this->record->receipts()->pluck('goods_receipt_id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->grIds = $data['goods_receipt_ids'] ?? [];
        unset($data['goods_receipt_ids']);

        return $data;
    }

    /** @var array<int, int> */
    protected array $grIds = [];

    protected function afterSave(): void
    {
        app(PurchaseInvoiceService::class)->tautkanPenerimaan($this->record, $this->grIds);
        app(PurchaseInvoiceService::class)->recalculate($this->record);
    }
}
