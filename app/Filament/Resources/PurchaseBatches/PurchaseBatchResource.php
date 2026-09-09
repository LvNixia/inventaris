<?php

namespace App\Filament\Resources\PurchaseBatches;

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

    protected static ?string $modelLabel = 'Arsip Pembelian';

    protected static ?string $pluralModelLabel = 'Arsip Pembelian';

    protected static \UnitEnum|string|null $navigationGroup = 'Pengadaan';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 9;

    /*
     * Jalur pembelian manual ditutup sejak alur Pesanan → Penerimaan aktif.
     *
     * Dua tombol yang sama-sama menerima barang — satu tercatat hutangnya, satu
     * tidak — akan berakhir dengan yang lebih cepat selalu menang. Daftarnya
     * tetap terbuka supaya pembelian lama masih bisa dilihat, dan barisnya masih
     * ditulis sistem: setiap penerimaan barang membuat batch di sini sebagai
     * lapisan biaya unit.
     */
    public static function canCreate(): bool
    {
        return false;
    }

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
            'edit' => EditPurchaseBatch::route('/{record}/edit'),
        ];
    }
}
