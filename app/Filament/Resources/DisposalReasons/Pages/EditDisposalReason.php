<?php

namespace App\Filament\Resources\DisposalReasons\Pages;

use App\Filament\Resources\DisposalReasons\DisposalReasonResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDisposalReason extends EditRecord
{
    protected static string $resource = DisposalReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
