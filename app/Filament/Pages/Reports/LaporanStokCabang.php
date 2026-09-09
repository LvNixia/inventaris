<?php

namespace App\Filament\Pages\Reports;

use App\Enums\Role;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Category;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rekap jumlah unit per barang per cabang.
 *
 * Angkanya dicacah langsung dari baris aset, bukan dibaca dari tabel saldo yang
 * harus disinkronkan. Karena itu jumlah di sini tidak mungkin berbeda dari
 * daftar unitnya: keduanya sumber yang sama.
 */
class LaporanStokCabang extends BaseReportPage
{
    protected static ?int $navigationSort = 10;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-building-storefront';
    }

    public static function getNavigationLabel(): string
    {
        return 'Stok per Cabang';
    }

    public function getTitle(): string
    {
        return 'Laporan Stok per Cabang';
    }

    public function getSubheading(): ?string
    {
        return 'Jumlah unit tiap barang di tiap cabang, dipilah menurut keadaannya.';
    }

    public function table(Table $table): Table
    {
        return $this->applyDefaultTableSettings($table)
            ->query(fn (): Builder => $this->rekap())
            ->columns([
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable(),
                TextColumn::make('product.category.name')
                    ->label('Kategori')
                    ->sortable(),
                TextColumn::make('barang')
                    ->label('Barang')
                    ->state(fn (Asset $record): string => $record->product?->name ?? '—')
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->whereHas('product', fn (Builder $p) => $p
                            ->where('model', 'like', "%{$search}%")
                            ->orWhereHas('brand', fn (Builder $b) => $b->where('name', 'like', "%{$search}%")))),
                TextColumn::make('total')
                    ->label('Total Unit')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('tersedia')
                    ->label('Tersedia')
                    ->numeric()
                    ->alignEnd()
                    ->color(fn ($state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('dipegang')
                    ->label('Dipegang')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('tidak_tersedia')
                    ->label('Servis / Rusak')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('dilepas')
                    ->label('Dilepas')
                    ->numeric()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->relationship('branch', 'name')
                    ->visible(fn (): bool => auth()->user()?->role === Role::AdminPusat),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->relationship('product.category', 'name'),
                Filter::make('habis')
                    ->label('Stok gudang habis')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->havingRaw('tersedia = 0')),
            ])
            ->defaultSort('total', 'desc');
    }

    /**
     * Satu baris per kombinasi cabang dan barang.
     *
     * MIN(id) dipakai sebagai kunci baris karena Filament butuh nilai unik per
     * baris, sementara hasil pengelompokan tidak punya kunci alaminya sendiri.
     */
    protected function rekap(): Builder
    {
        return $this->scopeToUserBranch(
            Asset::query()
                ->selectRaw('MIN(assets.id) as id, assets.branch_id, assets.product_id')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN assets.retired_at IS NULL AND assets.current_holder_id IS NULL AND asset_statuses.transferable = 1 THEN 1 ELSE 0 END) as tersedia')
                ->selectRaw('SUM(CASE WHEN assets.retired_at IS NULL AND assets.current_holder_id IS NOT NULL THEN 1 ELSE 0 END) as dipegang')
                ->selectRaw('SUM(CASE WHEN assets.retired_at IS NULL AND assets.current_holder_id IS NULL AND COALESCE(asset_statuses.transferable, 0) = 0 THEN 1 ELSE 0 END) as tidak_tersedia')
                ->selectRaw('SUM(CASE WHEN assets.retired_at IS NOT NULL THEN 1 ELSE 0 END) as dilepas')
                ->leftJoin('asset_statuses', 'asset_statuses.id', '=', 'assets.current_status_id')
                ->groupBy('assets.branch_id', 'assets.product_id')
        );
    }

    public function getReportHeadings(): array
    {
        return [
            'Cabang', 'Kategori', 'Barang',
            'Total Unit', 'Tersedia', 'Dipegang', 'Servis / Rusak', 'Dilepas',
        ];
    }

    public function mapReportRow(mixed $record): array
    {
        return [
            $record->branch?->name,
            $record->product?->category?->name,
            $record->product?->name,
            (int) $record->total,
            (int) $record->tersedia,
            (int) $record->dipegang,
            (int) $record->tidak_tersedia,
            (int) $record->dilepas,
        ];
    }

    protected function getReportEagerLoads(): array
    {
        return ['branch', 'product.category', 'product.brand'];
    }

    public function getReportMeta(): array
    {
        return [
            'Cabang' => $this->nameOf(Branch::class, $this->getTableFilterState('branch_id')['value'] ?? null),
            'Kategori' => $this->nameOf(Category::class, $this->getTableFilterState('category')['value'] ?? null),
        ];
    }
}
