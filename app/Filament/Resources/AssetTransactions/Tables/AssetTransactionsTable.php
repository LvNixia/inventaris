<?php

namespace App\Filament\Resources\AssetTransactions\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                TextColumn::make('asset.asset_code')
                    ->label('Kode Aset')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipe Transaksi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'handover' => 'Serah Terima',
                        'return' => 'Pengembalian',
                        'status_change' => 'Perubahan Status',
                        'disposal' => 'Pemusnahan',
                        'branch_transfer' => 'Pindah Cabang',
                        'cancellation' => 'Pembatalan',
                        'correction' => 'Koreksi',
                        'legacy' => 'Data Lama',
                        default => $state,
                    }),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('fromEmployee.name')
                    ->label('Dari')
                    ->searchable(),
                TextColumn::make('toEmployee.name')
                    ->label('Ke')
                    ->searchable(),
                TextColumn::make('userEmployee.name')
                    ->label('Pemakai')
                    ->searchable(),
                TextColumn::make('status.name')
                    ->label('Status Aset'),
                TextColumn::make('stock_direction')
                    ->label('Arah Stok')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'out' => 'Keluar',
                        'in' => 'Masuk',
                        'neutral' => 'Netral',
                        'writeoff' => 'Write-off',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        'neutral' => 'gray',
                        'writeoff' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('handoverDocument.document_number')
                    ->label('No. Surat')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Waktu Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Ekspor')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        return response()->streamDownload(function () use ($livewire) {
                            $query = $livewire->getFilteredTableQuery();
                            app(\App\Services\AssetTransactionExportService::class)->export($query);
                        }, 'export_transactions_' . date('Ymd_His') . '.xlsx');
                    }),
            ])
            // Riwayat transaksi bersifat permanen: tidak ada aksi massal / hapus.
            ->toolbarActions([]);
    }
}
