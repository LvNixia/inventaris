<?php

namespace App\Filament\Resources\AssetTransactions\Pages;

use App\Filament\Resources\AssetTransactions\AssetTransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetTransactions extends ListRecords
{
    protected static string $resource = AssetTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
