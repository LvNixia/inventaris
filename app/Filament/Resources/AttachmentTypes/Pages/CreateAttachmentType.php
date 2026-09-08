<?php

namespace App\Filament\Resources\AttachmentTypes\Pages;

use App\Filament\Resources\AttachmentTypes\AttachmentTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttachmentType extends CreateRecord
{
    protected static string $resource = AttachmentTypeResource::class;
}
