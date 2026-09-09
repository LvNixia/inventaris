<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Enums\Role;
use App\Models\Asset;
use App\Models\Condition;
use App\Services\AssetExportService;
use App\Services\AssetService;
use App\Services\BranchTransferService;
use App\Services\Reports\ReportExporter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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
                TextColumn::make('product.category.name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.brand.name')
                    ->label('Merk')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.model')
                    ->label('Tipe / Model')
                    ->searchable(),
                TextColumn::make('serial_number')
                    ->label('S/N')
                    ->searchable()
                    ->toggleable()
                    // Unit yang wajib bernomor seri tetapi belum diisi akan
                    // tertahan saat diserahkan, jadi ditandai sejak di daftar.
                    ->badge(fn (Asset $record): bool => $record->isMissingRequiredSerial())
                    ->color(fn (Asset $record): ?string => $record->isMissingRequiredSerial() ? 'warning' : null)
                    ->formatStateUsing(fn (?string $state, Asset $record): string => $state
                        ?? ($record->isMissingRequiredSerial() ? 'Belum diisi' : '—')),
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable()
                    ->visible(fn () => auth()->user()->role === Role::AdminPusat),
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
                TextColumn::make('purchaseBatch.purchase_date')
                    ->label('Tgl Beli')
                    ->date('d M Y')
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
                    ->visible(fn () => auth()->user()->role === Role::AdminPusat),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->relationship('product.category', 'name'),
                Filter::make('tanpa_serial')
                    ->label('Nomor seri belum diisi')
                    ->query(fn (Builder $query) => $query
                        ->whereNull('serial_number')
                        ->whereHas('product.category', fn (Builder $c) => $c->where('requires_serial', true))),
                SelectFilter::make('current_status_id')
                    ->label('Status')
                    ->relationship('currentStatus', 'name'),
                SelectFilter::make('condition_id')
                    ->label('Kondisi')
                    ->relationship('condition', 'name'),
            ])
            ->recordActions([
                Action::make('konfirmasi_terima')
                    ->label('Terima')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record) => $record->currentStatus?->code === 'in_transit' && (auth()->user()->role === Role::AdminPusat || auth()->user()->branch_id === $record->branch_id))
                    ->form([
                        DatePicker::make('transaction_date')
                            ->label('Tanggal Terima')
                            ->default(now())
                            ->required(),
                        Select::make('condition_id')
                            ->label('Kondisi Saat Diterima')
                            ->options(Condition::pluck('name', 'id'))
                            ->default(fn ($record) => $record->condition_id)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Catatan Penerimaan'),
                    ])
                    ->action(function ($record, array $data) {
                        try {
                            app(BranchTransferService::class)->receive($record, $data);
                            Notification::make()->success()->title('Barang Diterima')->send();
                        } catch (\Exception $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $service = app(AssetService::class);
                            foreach ($records as $record) {
                                try {
                                    $service->delete($record);
                                } catch (\Exception $e) {
                                    Notification::make()
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
                Action::make('export')
                    ->label('Ekspor')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        try {
                            // Diperiksa sebelum unduhan dimulai; XLSX butuh ekstensi zip.
                            ReportExporter::ensureXlsxIsSupported();
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->warning()
                                ->title('Ekspor XLSX belum bisa dipakai')
                                ->body($e->getMessage())
                                ->persistent()
                                ->send();

                            return null;
                        }

                        return response()->streamDownload(function () use ($livewire) {
                            $query = $livewire->getFilteredTableQuery();
                            app(AssetExportService::class)->export($query);
                        }, 'export_assets_'.date('Ymd_His').'.xlsx');
                    }),
            ]);
    }
}
