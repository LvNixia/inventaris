<?php

namespace App\Filament\Resources\ServiceResults\Pages;

use App\Filament\Resources\ServiceResults\ServiceResultResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceResult extends EditRecord
{
    protected static string $resource = ServiceResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
