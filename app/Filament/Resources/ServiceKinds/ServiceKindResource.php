<?php

namespace App\Filament\Resources\ServiceKinds;

use App\Filament\Resources\ServiceKinds\Pages\CreateServiceKind;
use App\Filament\Resources\ServiceKinds\Pages\EditServiceKind;
use App\Filament\Resources\ServiceKinds\Pages\ListServiceKinds;
use App\Filament\Resources\ServiceKinds\Schemas\ServiceKindForm;
use App\Filament\Resources\ServiceKinds\Tables\ServiceKindsTable;
use App\Models\ServiceKind;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ServiceKindResource extends Resource
{
    protected static ?string $model = ServiceKind::class;

    protected static ?string $modelLabel = 'Jenis Servis';

    protected static ?string $pluralModelLabel = 'Jenis Servis';

    protected static \UnitEnum|string|null $navigationGroup = 'Referensi';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ServiceKindForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceKindsTable::configure($table);
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
            'index' => ListServiceKinds::route('/'),
            'create' => CreateServiceKind::route('/create'),
            'edit' => EditServiceKind::route('/{record}/edit'),
        ];
    }
}
