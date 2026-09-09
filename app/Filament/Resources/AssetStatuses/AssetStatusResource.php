<?php

namespace App\Filament\Resources\AssetStatuses;

use App\Filament\Resources\AssetStatuses\Pages\CreateAssetStatus;
use App\Filament\Resources\AssetStatuses\Pages\EditAssetStatus;
use App\Filament\Resources\AssetStatuses\Pages\ListAssetStatuses;
use App\Filament\Resources\AssetStatuses\Schemas\AssetStatusForm;
use App\Filament\Resources\AssetStatuses\Tables\AssetStatusesTable;
use App\Models\AssetStatus;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AssetStatusResource extends Resource
{
    protected static ?string $model = AssetStatus::class;

    protected static ?string $modelLabel = 'Status Aset';

    protected static ?string $pluralModelLabel = 'Status Aset';

    protected static \UnitEnum|string|null $navigationGroup = 'Referensi';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AssetStatusForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetStatusesTable::configure($table);
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
            'index' => ListAssetStatuses::route('/'),
            'create' => CreateAssetStatus::route('/create'),
            'edit' => EditAssetStatus::route('/{record}/edit'),
        ];
    }
}
