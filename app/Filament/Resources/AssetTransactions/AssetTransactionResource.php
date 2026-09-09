<?php

namespace App\Filament\Resources\AssetTransactions;

use App\Filament\Resources\AssetTransactions\Pages\CreateAssetTransaction;
use App\Filament\Resources\AssetTransactions\Pages\EditAssetTransaction;
use App\Filament\Resources\AssetTransactions\Pages\ListAssetTransactions;
use App\Filament\Resources\AssetTransactions\Schemas\AssetTransactionForm;
use App\Filament\Resources\AssetTransactions\Tables\AssetTransactionsTable;
use App\Models\AssetTransaction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AssetTransactionResource extends Resource
{
    protected static ?string $model = AssetTransaction::class;

    protected static ?string $modelLabel = 'Riwayat Transaksi';

    protected static ?string $pluralModelLabel = 'Riwayat Transaksi';

    protected static \UnitEnum|string|null $navigationGroup = 'Manajemen Aset';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return AssetTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetTransactionsTable::configure($table);
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
            'index' => ListAssetTransactions::route('/'),
            'create' => CreateAssetTransaction::route('/create'),
            'edit' => EditAssetTransaction::route('/{record}/edit'),
        ];
    }
}
