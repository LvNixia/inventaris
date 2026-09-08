<?php

namespace App\Filament\Resources\ServiceResults\Pages;

use App\Filament\Resources\ServiceResults\ServiceResultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceResults extends ListRecords
{
    protected static string $resource = ServiceResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
