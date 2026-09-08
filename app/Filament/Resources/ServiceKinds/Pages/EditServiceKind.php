<?php

namespace App\Filament\Resources\ServiceKinds\Pages;

use App\Filament\Resources\ServiceKinds\ServiceKindResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceKind extends EditRecord
{
    protected static string $resource = ServiceKindResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
