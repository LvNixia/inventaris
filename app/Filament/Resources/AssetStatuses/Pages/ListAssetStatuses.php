<?php

namespace App\Filament\Resources\AssetStatuses\Pages;

use App\Filament\Resources\AssetStatuses\AssetStatusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetStatuses extends ListRecords
{
    protected static string $resource = AssetStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
