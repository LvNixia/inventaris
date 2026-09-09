<?php

namespace App\Filament\Resources\PurchaseBatches\Pages;

use App\Filament\Resources\PurchaseBatches\PurchaseBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseBatches extends ListRecords
{
    protected static string $resource = PurchaseBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
