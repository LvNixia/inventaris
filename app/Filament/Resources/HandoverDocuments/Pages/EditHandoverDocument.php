<?php

namespace App\Filament\Resources\HandoverDocuments\Pages;

use App\Filament\Resources\HandoverDocuments\HandoverDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHandoverDocument extends EditRecord
{
    protected static string $resource = HandoverDocumentResource::class;


    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
