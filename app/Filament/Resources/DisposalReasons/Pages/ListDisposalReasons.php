<?php

namespace App\Filament\Resources\DisposalReasons\Pages;

use App\Filament\Resources\DisposalReasons\DisposalReasonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDisposalReasons extends ListRecords
{
    protected static string $resource = DisposalReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
