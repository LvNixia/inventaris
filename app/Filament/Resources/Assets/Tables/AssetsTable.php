<?php

namespace App\Filament\Resources\Assets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('asset_code')
                    ->label('Kode Aset')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->copyable(),
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand.name')
                    ->label('Merk')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('model')
                    ->label('Tipe / Model')
                    ->searchable(),
                TextColumn::make('serial_number')
                    ->label('S/N')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable()
                    ->visible(fn () => auth()->user()->role === \App\Enums\Role::AdminPusat),
                TextColumn::make('currentStatus.name')
                    ->label('Status')
                    ->sortable(),
                TextColumn::make('condition.name')
                    ->label('Kondisi')
                    ->sortable(),
                TextColumn::make('currentHolder.name')
                    ->label('Pemegang')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('qty_available')
                    ->label('Stok Tersedia')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Didaftarkan')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->relationship('branch', 'name')
                    ->visible(fn () => auth()->user()->role === \App\Enums\Role::AdminPusat),
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
                SelectFilter::make('current_status_id')
                    ->label('Status')
                    ->relationship('currentStatus', 'name'),
                SelectFilter::make('condition_id')
                    ->label('Kondisi')
                    ->relationship('condition', 'name'),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('konfirmasi_terima')
                    ->label('Terima')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record) => $record->currentStatus?->code === 'in_transit' && (auth()->user()->role === \App\Enums\Role::AdminPusat || auth()->user()->branch_id === $record->branch_id))
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('transaction_date')
                            ->label('Tanggal Terima')
                            ->default(now())
                            ->required(),
                        \Filament\Forms\Components\Select::make('condition_id')
                            ->label('Kondisi Saat Diterima')
                            ->options(\App\Models\Condition::pluck('name', 'id'))
                            ->default(fn($record) => $record->condition_id)
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Catatan Penerimaan'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(\App\Services\BranchTransferService::class)->receive($record, $data);
                            \Filament\Notifications\Notification::make()->success()->title('Barang Diterima')->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $service = app(\App\Services\AssetService::class);
                            foreach ($records as $record) {
                                try {
                                    $service->delete($record);
                                } catch (\Exception $e) {
                                    \Filament\Notifications\Notification::make()
                                        ->danger()
                                        ->title('Gagal Menghapus')
                                        ->body($e->getMessage())
                                        ->send();
                                }
                            }
                        }),
                ]),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('export')
                    ->label('Ekspor')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        return response()->streamDownload(function () use ($livewire) {
                            $query = $livewire->getFilteredTableQuery();
                            app(\App\Services\AssetExportService::class)->export($query);
                        }, 'export_assets_' . date('Ymd_His') . '.xlsx');
                    }),
            ]);
    }
}
