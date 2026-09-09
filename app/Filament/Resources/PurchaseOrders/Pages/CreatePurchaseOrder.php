<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Services\PurchaseOrderService;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        $data['created_by'] = auth()->id();

        return $data;
    }

    /**
     * Nilai PO dihitung setelah barisnya tersimpan, bukan dari masukan formulir,
     * supaya angka di basis data selalu berasal dari satu rumus yang sama.
     */
    protected function afterCreate(): void
    {
        app(PurchaseOrderService::class)->recalculate($this->record);
    }
}
