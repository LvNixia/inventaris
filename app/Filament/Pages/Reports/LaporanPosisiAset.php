<?php

namespace App\Filament\Pages\Reports;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Category;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LaporanPosisiAset extends BaseReportPage
{
    protected static ?int $navigationSort = 11;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationLabel(): string
    {
        return 'Posisi & Nilai Aset';
    }

    public function getTitle(): string
    {
        return 'Laporan Posisi & Nilai Aset';
    }

    public function getSubheading(): ?string
    {
        return 'Jumlah unit, sebaran stok, dan nilai aset menurut kategori, cabang, dan kondisi.';
    }

    public function table(Table $table): Table
    {
        return $this->applyDefaultTableSettings($table)
            ->query(fn (): Builder => $this->scopeToUserBranch(Asset::query()))
            ->columns([
                TextColumn::make('asset_code')
                    ->label('Kode Aset')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.category.name')
                    ->label('Kategori')
                    ->sortable(),
                TextColumn::make('product.brand.name')
                    ->label('Merek')
                    ->sortable(),
                TextColumn::make('product.model')
                    ->label('Tipe / Model')
                    ->searchable(),
                TextColumn::make('serial_number')
                    ->label('S/N')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable(),
                TextColumn::make('currentStatus.name')
                    ->label('Status'),
                TextColumn::make('condition.name')
                    ->label('Kondisi'),
                TextColumn::make('keadaan')
                    ->label('Keadaan')
                    ->badge()
                    ->state(fn (Asset $record): string => $this->keadaan($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Tersedia' => 'success',
                        'Dipegang' => 'info',
                        'Dilepas' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('currentHolder.name')
                    ->label('Pemegang')
                    ->toggleable(),
                TextColumn::make('purchaseBatch.unit_price')
                    ->label('Harga Satuan')
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nilai_aktif')
                    ->label('Nilai Aktif')
                    ->state(fn (Asset $record): float => $this->nilaiAktif($record))
                    ->money('IDR', locale: 'id')
                    ->alignEnd(),
                TextColumn::make('purchaseBatch.purchase_date')
                    ->label('Tgl Beli')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('umur')
                    ->label('Umur')
                    ->state(fn (Asset $record): string => $this->umur($record))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchaseBatch.warranty_until')
                    ->label('Garansi Sampai')
                    ->date('d M Y')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        blank($state) => 'gray',
                        $state->isPast() => 'danger',
                        $state->lessThanOrEqualTo(now()->addDays(90)) => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->relationship('branch', 'name')
                    ->visible(fn (): bool => auth()->user()?->role === \App\Enums\Role::AdminPusat),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->relationship('product.category', 'name'),
                SelectFilter::make('condition_id')
                    ->label('Kondisi')
                    ->relationship('condition', 'name'),
                SelectFilter::make('current_status_id')
                    ->label('Status')
                    ->relationship('currentStatus', 'name'),
                Filter::make('menganggur')
                    ->label('Hanya aset menganggur')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->available()
                        ->whereDoesntHave('assetTransactions', fn (Builder $tx) => $tx->where('stock_direction', 'out'))),
                Filter::make('tanpa_serial')
                    ->label('Nomor seri belum diisi')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNull('serial_number')
                        ->whereHas('product.category', fn (Builder $c) => $c->where('requires_serial', true))),
                Filter::make('garansi_segera_habis')
                    ->label('Garansi habis dalam 90 hari')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereHas('purchaseBatch', fn (Builder $b) => $b
                            ->whereNotNull('warranty_until')
                            ->whereBetween('warranty_until', [now(), now()->addDays(90)]))),
                Filter::make('garansi_lewat')
                    ->label('Garansi sudah lewat')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereHas('purchaseBatch', fn (Builder $b) => $b
                            ->whereNotNull('warranty_until')
                            ->whereDate('warranty_until', '<', now()))),
            ])
            ->defaultSort('asset_code');
    }

    public function getReportHeadings(): array
    {
        return [
            'Kode Aset', 'Kategori', 'Merek', 'Tipe / Model', 'S/N', 'Cabang', 'Status', 'Kondisi',
            'Keadaan', 'Pemegang', 'Harga Satuan', 'Nilai Aktif', 'Tgl Beli', 'Umur', 'Garansi Sampai',
        ];
    }

    public function mapReportRow(mixed $record): array
    {
        return [
            $record->asset_code,
            $record->product?->category?->name,
            $record->product?->brand?->name,
            $record->product?->model,
            $record->serial_number,
            $record->branch?->name,
            $record->currentStatus?->name,
            $record->condition?->name,
            $this->keadaan($record),
            $record->currentHolder?->name,
            (float) $record->unit_price,
            $this->nilaiAktif($record),
            $record->purchase_date?->translatedFormat('d M Y'),
            $this->umur($record),
            $record->warranty_until?->translatedFormat('d M Y'),
        ];
    }

    protected function getReportEagerLoads(): array
    {
        return ['product.category', 'product.brand', 'purchaseBatch', 'branch', 'condition', 'currentStatus', 'currentHolder'];
    }

    public function getReportMeta(): array
    {
        $filters = $this->getTableFilterState('branch_id');

        return [
            'Cabang' => $this->nameOf(Branch::class, $filters['value'] ?? null),
            'Kategori' => $this->nameOf(Category::class, $this->getTableFilterState('category')['value'] ?? null),
        ];
    }

    /**
     * Nilai aset yang masih dimiliki: harga satuan dikali unit di luar yang sudah dilepas.
     */
    protected function nilaiAktif(Asset $asset): float
    {
        // Unit yang sudah dilepas tidak lagi bernilai bagi perusahaan.
        return $asset->retired_at ? 0.0 : (float) $asset->unit_price;
    }

    /**
     * Keadaan unit dalam satu kata, menggantikan empat kolom kuantitas lama.
     */
    protected function keadaan(Asset $asset): string
    {
        return match (true) {
            $asset->retired_at !== null => 'Dilepas',
            $asset->current_holder_id !== null => 'Dipegang',
            $asset->isAvailable() => 'Tersedia',
            default => $asset->currentStatus?->name ?? 'Belum berstatus',
        };
    }

    protected function umur(Asset $asset): string
    {
        if (blank($asset->purchase_date)) {
            return '—';
        }

        $bulan = $asset->purchase_date->diffInMonths(now());

        return match (true) {
            $bulan < 12 => $bulan . ' bln',
            default => intdiv($bulan, 12) . ' th ' . ($bulan % 12) . ' bln',
        };
    }
}
