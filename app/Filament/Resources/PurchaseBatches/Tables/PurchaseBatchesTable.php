<?php

namespace App\Filament\Resources\PurchaseBatches\Tables;

use App\Enums\Role;
use App\Models\PurchaseBatch;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('purchase_date')
                    ->label('Tgl Beli')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('product.model')
                    ->label('Barang')
                    ->description(fn (PurchaseBatch $record): ?string => $record->product?->brand?->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_number')
                    ->label('No. Faktur')
                    ->searchable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('assets_count')
                    ->label('Unit')
                    ->counts('assets')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->label('Harga Satuan')
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('nilai_total')
                    ->label('Nilai Total')
                    ->state(fn (PurchaseBatch $record): float => (float) $record->unit_price * $record->assets()->count())
                    ->money('IDR', locale: 'id')
                    ->alignEnd(),
                TextColumn::make('warranty_until')
                    ->label('Garansi Sampai')
                    ->date('d M Y')
                    ->toggleable(),
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable()
                    ->visible(fn () => auth()->user()->role === Role::AdminPusat),
            ])
            ->defaultSort('purchase_date', 'desc')
            ->filters([
                SelectFilter::make('product_id')
                    ->label('Barang')
                    ->relationship('product', 'model')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name'),
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->relationship('branch', 'name')
                    ->visible(fn () => auth()->user()->role === Role::AdminPusat),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
