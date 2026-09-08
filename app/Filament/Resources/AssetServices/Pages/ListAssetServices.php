<?php

namespace App\Filament\Resources\AssetServices\Pages;

use App\Filament\Resources\AssetServices\AssetServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetServices extends ListRecords
{
    protected static string $resource = AssetServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
