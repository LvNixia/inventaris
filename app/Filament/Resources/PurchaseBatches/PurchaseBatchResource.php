<?php

namespace App\Filament\Resources\PurchaseBatches;

use App\Filament\Resources\PurchaseBatches\Pages\CreatePurchaseBatch;
use App\Filament\Resources\PurchaseBatches\Pages\EditPurchaseBatch;
use App\Filament\Resources\PurchaseBatches\Pages\ListPurchaseBatches;
use App\Filament\Resources\PurchaseBatches\Schemas\PurchaseBatchForm;
use App\Filament\Resources\PurchaseBatches\Tables\PurchaseBatchesTable;
use App\Models\PurchaseBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PurchaseBatchResource extends Resource
{
    protected static ?string $model = PurchaseBatch::class;

    protected static ?string $modelLabel = 'Pembelian';

    protected static ?string $pluralModelLabel = 'Pembelian';

    protected static \UnitEnum|string|null $navigationGroup = 'Manajemen Aset';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PurchaseBatchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchaseBatchesTable::configure($table);
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
            'index' => ListPurchaseBatches::route('/'),
            'create' => CreatePurchaseBatch::route('/create'),
            'edit' => EditPurchaseBatch::route('/{record}/edit'),
        ];
    }
}
