<?php

namespace App\Filament\Resources\AssetServices\Pages;

use App\Filament\Resources\AssetServices\AssetServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssetService extends EditRecord
{
    protected static string $resource = AssetServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
