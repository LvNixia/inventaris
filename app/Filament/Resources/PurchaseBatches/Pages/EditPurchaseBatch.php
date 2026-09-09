<?php

namespace App\Filament\Resources\PurchaseBatches\Pages;

use App\Filament\Resources\PurchaseBatches\PurchaseBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseBatch extends EditRecord
{
    protected static string $resource = PurchaseBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
