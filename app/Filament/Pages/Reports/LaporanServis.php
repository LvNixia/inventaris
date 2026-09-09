<?php

namespace App\Filament\Pages\Reports;

use App\Models\AssetService;
use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LaporanServis extends BaseReportPage
{
    protected static ?int $navigationSort = 13;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-wrench-screwdriver';
    }

    public static function getNavigationLabel(): string
    {
        return 'Servis & Biaya';
    }

    public function getTitle(): string
    {
        return 'Laporan Servis & Biaya';
    }

    public function getSubheading(): ?string
    {
        return 'Riwayat servis, lama pengerjaan, dan biaya jasa maupun suku cadang.';
    }

    public function table(Table $table): Table
    {
        return $this->applyDefaultTableSettings($table)
            ->query(fn (): Builder => AssetService::query())
            ->columns([
                TextColumn::make('asset.asset_code')
                    ->label('Kode Aset')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('serviceKind.name')
                    ->label('Jenis Servis')
                    ->sortable(),
                TextColumn::make('performed_by')
                    ->label('Pelaksana')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'internal' => 'Internal IT',
                        'vendor' => 'Vendor',
                        default => $state ?? '—',
                    }),
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->sortable(),
                TextColumn::make('started_at')
                    ->label('Masuk')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('expected_at')
                    ->label('Estimasi')
                    ->date('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('finished_at')
                    ->label('Selesai')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('durasi')
                    ->label('Lama')
                    ->state(fn (AssetService $record): string => $this->durasi($record))
                    ->alignEnd(),
                TextColumn::make('cost_service')
                    ->label('Biaya Jasa')
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('IDR', locale: 'id')),
                TextColumn::make('cost_parts')
                    ->label('Biaya Suku Cadang')
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('IDR', locale: 'id')),
                TextColumn::make('total_biaya')
                    ->label('Total Biaya')
                    ->state(fn (AssetService $record): float => (float) $record->cost_service + (float) $record->cost_parts)
                    ->money('IDR', locale: 'id')
                    ->alignEnd(),
                TextColumn::make('serviceResult.name')
                    ->label('Hasil')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Terbuka',
                        'closed' => 'Selesai',
                        default => $state,
                    })
                    ->color(fn (string $state): string => $state === 'open' ? 'warning' : 'success'),
            ])
            ->filters([
                SelectFilter::make('service_kind_id')
                    ->label('Jenis Servis')
                    ->relationship('serviceKind', 'name'),
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name'),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(['open' => 'Terbuka', 'closed' => 'Selesai']),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Masuk dari'),
                        DatePicker::make('sampai')->label('Masuk sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $d) => $q->whereDate('started_at', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $d) => $q->whereDate('started_at', '<=', $d))),
                Filter::make('lewat_estimasi')
                    ->label('Lewat estimasi & belum selesai')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', 'open')
                        ->whereNotNull('expected_at')
                        ->whereDate('expected_at', '<', now())),
            ])
            ->defaultSort('started_at', 'desc');
    }

    public function getReportHeadings(): array
    {
        return [
            'Kode Aset', 'Jenis Servis', 'Pelaksana', 'Vendor',
            'Masuk', 'Estimasi', 'Selesai', 'Lama (hari)',
            'Biaya Jasa', 'Biaya Suku Cadang', 'Total Biaya', 'Hasil', 'Status',
        ];
    }

    public function mapReportRow(mixed $record): array
    {
        return [
            $record->asset?->asset_code,
            $record->serviceKind?->name,
            match ($record->performed_by) {
                'internal' => 'Internal IT',
                'vendor' => 'Vendor',
                default => $record->performed_by,
            },
            $record->vendor?->name,
            $record->started_at?->translatedFormat('d M Y'),
            $record->expected_at?->translatedFormat('d M Y'),
            $record->finished_at?->translatedFormat('d M Y'),
            $this->durasiHari($record),
            (float) $record->cost_service,
            (float) $record->cost_parts,
            (float) $record->cost_service + (float) $record->cost_parts,
            $record->serviceResult?->name,
            $record->status === 'open' ? 'Terbuka' : 'Selesai',
        ];
    }

    protected function getReportEagerLoads(): array
    {
        return ['asset.product', 'serviceKind', 'vendor', 'serviceResult'];
    }

    public function getReportMeta(): array
    {
        $periode = $this->getTableFilterState('periode') ?? [];

        return [
            'Periode masuk' => $this->describePeriod($periode['dari'] ?? null, $periode['sampai'] ?? null),
            'Vendor' => $this->nameOf(Vendor::class, $this->getTableFilterState('vendor_id')['value'] ?? null),
        ];
    }

    /**
     * Servis yang belum selesai dihitung sampai hari ini, supaya
     * pekerjaan yang menggantung tetap terlihat lamanya.
     */
    protected function durasiHari(AssetService $record): ?int
    {
        if (blank($record->started_at)) {
            return null;
        }

        return (int) $record->started_at->diffInDays($record->finished_at ?? now());
    }

    protected function durasi(AssetService $record): string
    {
        $hari = $this->durasiHari($record);

        if ($hari === null) {
            return '—';
        }

        return $hari . ' hari' . (blank($record->finished_at) ? ' (berjalan)' : '');
    }
}
