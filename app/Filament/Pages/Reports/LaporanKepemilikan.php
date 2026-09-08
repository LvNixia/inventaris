<?php

namespace App\Filament\Pages\Reports;

use App\Models\Asset;
use App\Models\Branch;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LaporanKepemilikan extends BaseReportPage
{
    protected static ?int $navigationSort = 12;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationLabel(): string
    {
        return 'Kepemilikan Aset';
    }

    public function getTitle(): string
    {
        return 'Laporan Kepemilikan Aset';
    }

    public function getSubheading(): ?string
    {
        return 'Aset yang sedang dipegang karyawan, lengkap dengan divisi dan status karyawannya.';
    }

    public function table(Table $table): Table
    {
        return $this->applyDefaultTableSettings($table)
            ->query(fn (): Builder => $this->scopeToUserBranch(
                Asset::query()->whereNotNull('current_holder_id')
            ))
            ->columns([
                TextColumn::make('currentHolder.name')
                    ->label('Pemegang')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('currentHolder.nik')
                    ->label('NIK')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('currentHolder.position.name')
                    ->label('Jabatan'),
                TextColumn::make('currentHolder.division.name')
                    ->label('Divisi')
                    ->sortable(),
                IconColumn::make('currentHolder.is_active')
                    ->label('Karyawan Aktif')
                    ->boolean(),
                TextColumn::make('asset_code')
                    ->label('Kode Aset')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Kategori'),
                TextColumn::make('barang')
                    ->label('Barang')
                    ->state(fn (Asset $record): string => trim(($record->brand?->name ?? '') . ' ' . ($record->model ?? '')) ?: '—'),
                TextColumn::make('serial_number')
                    ->label('Nomor Seri')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('jumlah_dipegang')
                    ->label('Jumlah Dipegang')
                    ->state(fn (Asset $record): int => (int) $record->qty_out - (int) $record->qty_in)
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('condition.name')
                    ->label('Kondisi'),
                TextColumn::make('branch.name')
                    ->label('Cabang')
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
                SelectFilter::make('division')
                    ->label('Divisi')
                    ->relationship('currentHolder.division', 'name'),
                Filter::make('karyawan_nonaktif')
                    ->label('Karyawan nonaktif yang masih memegang aset')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->whereHas('currentHolder', fn (Builder $q) => $q->where('is_active', false))),
            ])
            ->defaultSort('current_holder_id');
    }

    public function getReportHeadings(): array
    {
        return [
            'Pemegang', 'NIK', 'Jabatan', 'Divisi', 'Status Karyawan',
            'Kode Aset', 'Kategori', 'Barang', 'Nomor Seri', 'Jumlah Dipegang', 'Kondisi', 'Cabang',
        ];
    }

    public function mapReportRow(mixed $record): array
    {
        return [
            $record->currentHolder?->name,
            $record->currentHolder?->nik,
            $record->currentHolder?->position?->name,
            $record->currentHolder?->division?->name,
            $record->currentHolder?->is_active ? 'Aktif' : 'Nonaktif',
            $record->asset_code,
            $record->category?->name,
            trim(($record->brand?->name ?? '') . ' ' . ($record->model ?? '')),
            $record->serial_number,
            (int) $record->qty_out - (int) $record->qty_in,
            $record->condition?->name,
            $record->branch?->name,
        ];
    }

    protected function getReportEagerLoads(): array
    {
        return ['currentHolder.position', 'currentHolder.division', 'category', 'brand', 'condition', 'branch'];
    }

    public function getReportMeta(): array
    {
        return [
            'Cabang' => $this->nameOf(Branch::class, $this->getTableFilterState('branch_id')['value'] ?? null),
            'Saringan' => ($this->getTableFilterState('karyawan_nonaktif')['isActive'] ?? false)
                ? 'Hanya karyawan nonaktif'
                : 'Semua pemegang aktif',
        ];
    }
}
