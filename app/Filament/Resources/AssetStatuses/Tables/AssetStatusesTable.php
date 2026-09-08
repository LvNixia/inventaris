<?php

namespace App\Filament\Resources\AssetStatuses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetStatusesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('stock_direction')
                    ->label('Arah Stok')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'out' => 'Keluar',
                        'in' => 'Masuk',
                        'neutral' => 'Netral',
                        'writeoff' => 'Write-off',
                        default => $state,
                    }),
                IconColumn::make('transferable')
                    ->label('Dapat Dipindahkan')
                    ->boolean(),
                IconColumn::make('requires_qty')
                    ->label('Butuh Jumlah')
                    ->boolean(),
                IconColumn::make('clears_holder')
                    ->label('Mengosongkan Pemegang')
                    ->boolean(),
                IconColumn::make('is_system')
                    ->label('Bawaan Sistem')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
