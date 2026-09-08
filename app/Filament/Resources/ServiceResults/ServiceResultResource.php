<?php

namespace App\Filament\Resources\ServiceResults;

use App\Filament\Resources\ServiceResults\Pages\CreateServiceResult;
use App\Filament\Resources\ServiceResults\Pages\EditServiceResult;
use App\Filament\Resources\ServiceResults\Pages\ListServiceResults;
use App\Filament\Resources\ServiceResults\Schemas\ServiceResultForm;
use App\Filament\Resources\ServiceResults\Tables\ServiceResultsTable;
use App\Models\ServiceResult;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ServiceResultResource extends Resource
{
    protected static ?string $model = ServiceResult::class;

    protected static ?string $modelLabel = 'Hasil Servis';
    protected static ?string $pluralModelLabel = 'Hasil Servis';
    protected static \UnitEnum|string|null $navigationGroup = 'Referensi';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';
    protected static ?int $navigationSort = 6;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ServiceResultForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceResultsTable::configure($table);
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
            'index' => ListServiceResults::route('/'),
            'create' => CreateServiceResult::route('/create'),
            'edit' => EditServiceResult::route('/{record}/edit'),
        ];
    }
}
