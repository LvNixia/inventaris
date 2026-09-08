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
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->sortable(),
                TextColumn::make('brand.name')
                    ->label('Merek')
                    ->sortable(),
                TextColumn::make('model')
                    ->label('Tipe / Model')
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable(),
                TextColumn::make('currentStatus.name')
                    ->label('Status'),
                TextColumn::make('condition.name')
                    ->label('Kondisi'),
                TextColumn::make('quantity')
                    ->label('Total Unit')
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('Total')),
                TextColumn::make('qty_available')
                    ->label('Tersedia')
                    ->numeric()
                    ->alignEnd()
                    ->sortable()
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->label('Total')),
                TextColumn::make('qty_dipegang')
                    ->label('Dipegang')
                    ->state(fn (Asset $record): int => (int) $record->qty_out - (int) $record->qty_in)
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('qty_writeoff')
                    ->label('Dilepas')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unit_price')
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
                TextColumn::make('purchase_date')
                    ->label('Tgl Beli')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('umur')
                    ->label('Umur')
                    ->state(fn (Asset $record): string => $this->umur($record))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('warranty_until')
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
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
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
                        ->where('qty_available', '>', 0)
                        ->where('qty_out', 0)),
                Filter::make('garansi_segera_habis')
                    ->label('Garansi habis dalam 90 hari')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('warranty_until')
                        ->whereBetween('warranty_until', [now(), now()->addDays(90)])),
                Filter::make('garansi_lewat')
                    ->label('Garansi sudah lewat')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('warranty_until')
                        ->whereDate('warranty_until', '<', now())),
            ])
            ->defaultSort('asset_code');
    }

    public function getReportHeadings(): array
    {
        return [
            'Kode Aset', 'Kategori', 'Merek', 'Tipe / Model', 'Cabang', 'Status', 'Kondisi',
            'Total Unit', 'Tersedia', 'Dipegang', 'Dilepas',
            'Harga Satuan', 'Nilai Aktif', 'Tgl Beli', 'Umur', 'Garansi Sampai',
        ];
    }

    public function mapReportRow(mixed $record): array
    {
        return [
            $record->asset_code,
            $record->category?->name,
            $record->brand?->name,
            $record->model,
            $record->branch?->name,
            $record->currentStatus?->name,
            $record->condition?->name,
            (int) $record->quantity,
            (int) $record->qty_available,
            (int) $record->qty_out - (int) $record->qty_in,
            (int) $record->qty_writeoff,
            (float) $record->unit_price,
            $this->nilaiAktif($record),
            $record->purchase_date?->translatedFormat('d M Y'),
            $this->umur($record),
            $record->warranty_until?->translatedFormat('d M Y'),
        ];
    }

    protected function getReportEagerLoads(): array
    {
        return ['category', 'brand', 'branch', 'condition', 'currentStatus'];
    }

    public function getReportMeta(): array
    {
        $filters = $this->getTableFilterState('branch_id');

        return [
            'Cabang' => $this->nameOf(Branch::class, $filters['value'] ?? null),
            'Kategori' => $this->nameOf(Category::class, $this->getTableFilterState('category_id')['value'] ?? null),
        ];
    }

    /**
     * Nilai aset yang masih dimiliki: harga satuan dikali unit di luar yang sudah dilepas.
     */
    protected function nilaiAktif(Asset $asset): float
    {
        return (float) $asset->unit_price * max(0, (int) $asset->quantity - (int) $asset->qty_writeoff);
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
