<?php

namespace App\Filament\Resources\AssetServices;

use App\Filament\Resources\AssetServices\Pages\CreateAssetService;
use App\Filament\Resources\AssetServices\Pages\EditAssetService;
use App\Filament\Resources\AssetServices\Pages\ListAssetServices;
use App\Filament\Resources\AssetServices\Schemas\AssetServiceForm;
use App\Filament\Resources\AssetServices\Tables\AssetServicesTable;
use App\Models\AssetService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AssetServiceResource extends Resource
{
    protected static ?string $model = AssetService::class;

    protected static ?string $modelLabel = 'Servis Aset';

    protected static ?string $pluralModelLabel = 'Servis Aset';

    protected static \UnitEnum|string|null $navigationGroup = 'Manajemen Aset';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return AssetServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetServicesTable::configure($table);
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
            'index' => ListAssetServices::route('/'),
            'create' => CreateAssetService::route('/create'),
            'edit' => EditAssetService::route('/{record}/edit'),
        ];
    }
}
