<?php

namespace App\Filament\Resources\GoodsReceipts\Pages;

use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGoodsReceipt extends CreateRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Penerimaan selalu lahir sebagai draf; unit aset baru dibuat saat
        // penerimaannya disetujui dari halaman daftar.
        $data['status'] = 'draft';
        $data['created_by'] = auth()->id();

        return $data;
    }
}
