<?php

namespace App\Filament\Resources\AttachmentTypes\Pages;

use App\Filament\Resources\AttachmentTypes\AttachmentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttachmentTypes extends ListRecords
{
    protected static string $resource = AttachmentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
