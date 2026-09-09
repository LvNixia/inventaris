<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand.name')
                    ->label('Merek')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('model')
                    ->label('Tipe / Model')
                    ->searchable(),
                // Inilah yang dulu mustahil dijawab tanpa mencocokkan teks:
                // berapa total unit satu jenis barang, lintas semua pembelian.
                TextColumn::make('assets_count')
                    ->label('Total Unit')
                    ->counts('assets')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('purchase_batches_count')
                    ->label('Pembelian')
                    ->counts('purchaseBatches')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('is_active')
                    ->label('Aktif')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
                SelectFilter::make('brand_id')
                    ->label('Merek')
                    ->relationship('brand', 'name'),
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
