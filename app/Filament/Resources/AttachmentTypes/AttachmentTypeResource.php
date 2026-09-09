<?php

namespace App\Filament\Resources\AttachmentTypes;

use App\Filament\Resources\AttachmentTypes\Pages\CreateAttachmentType;
use App\Filament\Resources\AttachmentTypes\Pages\EditAttachmentType;
use App\Filament\Resources\AttachmentTypes\Pages\ListAttachmentTypes;
use App\Filament\Resources\AttachmentTypes\Schemas\AttachmentTypeForm;
use App\Filament\Resources\AttachmentTypes\Tables\AttachmentTypesTable;
use App\Models\AttachmentType;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AttachmentTypeResource extends Resource
{
    protected static ?string $model = AttachmentType::class;

    protected static ?string $modelLabel = 'Jenis Lampiran';

    protected static ?string $pluralModelLabel = 'Jenis Lampiran';

    protected static \UnitEnum|string|null $navigationGroup = 'Referensi';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-clip';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AttachmentTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttachmentTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttachmentTypes::route('/'),
            'create' => CreateAttachmentType::route('/create'),
            'edit' => EditAttachmentType::route('/{record}/edit'),
        ];
    }
}
