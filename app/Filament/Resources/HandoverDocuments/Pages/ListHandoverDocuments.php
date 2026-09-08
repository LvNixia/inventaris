<?php

namespace App\Filament\Resources\HandoverDocuments\Pages;

use App\Filament\Resources\HandoverDocuments\HandoverDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHandoverDocuments extends ListRecords
{
    protected static string $resource = HandoverDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
