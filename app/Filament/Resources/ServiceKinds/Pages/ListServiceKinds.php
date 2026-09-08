<?php

namespace App\Filament\Resources\ServiceKinds\Pages;

use App\Filament\Resources\ServiceKinds\ServiceKindResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceKinds extends ListRecords
{
    protected static string $resource = ServiceKindResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
