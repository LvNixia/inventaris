<?php

namespace App\Filament\Resources\AssetTransactions\Pages;

use App\Filament\Resources\AssetTransactions\AssetTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssetTransaction extends EditRecord
{
    protected static string $resource = AssetTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
