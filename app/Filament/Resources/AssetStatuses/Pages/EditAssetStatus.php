<?php

namespace App\Filament\Resources\AssetStatuses\Pages;

use App\Filament\Resources\AssetStatuses\AssetStatusResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssetStatus extends EditRecord
{
    protected static string $resource = AssetStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
