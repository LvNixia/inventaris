<?php

namespace App\Filament\Resources\AttachmentTypes\Pages;

use App\Filament\Resources\AttachmentTypes\AttachmentTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttachmentType extends EditRecord
{
    protected static string $resource = AttachmentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
