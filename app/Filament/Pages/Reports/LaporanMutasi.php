<?php

namespace App\Filament\Pages\Reports;

use App\Models\AssetTransaction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LaporanMutasi extends BaseReportPage
{
    protected static ?int $navigationSort = 14;

    /**
     * Label transaksi dipakai bersama oleh tabel, XLSX, dan PDF.
     */
    protected const JENIS = [
        'handover' => 'Serah Terima',
        'return' => 'Pengembalian',
        'status_change' => 'Perubahan Status',
        'disposal' => 'Pemusnahan',
        'branch_transfer' => 'Pindah Cabang',
        'cancellation' => 'Pembatalan',
        'correction' => 'Koreksi',
        'legacy' => 'Data Lama',
    ];

    protected const ARAH = [
        'out' => 'Keluar',
        'in' => 'Masuk',
        'neutral' => 'Netral',
        'writeoff' => 'Write-off',
    ];

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-arrows-right-left';
    }

    public static function getNavigationLabel(): string
    {
        return 'Mutasi & Pelepasan';
    }

    public function getTitle(): string
    {
        return 'Laporan Mutasi & Pelepasan Aset';
    }

    public function getSubheading(): ?string
    {
        return 'Seluruh pergerakan aset: serah terima, pengembalian, pindah cabang, hingga penghapusan.';
    }

    public function table(Table $table): Table
    {
        return $this->applyDefaultTableSettings($table)
            ->query(fn (): Builder => AssetTransaction::query())
            ->columns([
                TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('asset.asset_code')
                    ->label('Kode Aset')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::JENIS[$state] ?? $state),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('stock_direction')
                    ->label('Arah Stok')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::ARAH[$state] ?? ($state ?? '—'))
                    ->color(fn (?string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        'writeoff' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('fromEmployee.name')
                    ->label('Dari')
                    ->searchable(),
                TextColumn::make('toEmployee.name')
                    ->label('Ke')
                    ->searchable(),
                TextColumn::make('fromBranch.name')
                    ->label('Dari Cabang')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('toBranch.name')
                    ->label('Ke Cabang')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('disposalReason.name')
                    ->label('Alasan Pelepasan'),
                TextColumn::make('nilai_pelepasan')
                    ->label('Nilai Dilepas')
                    ->state(fn (AssetTransaction $record): ?float => $this->nilaiPelepasan($record))
                    ->money('IDR', locale: 'id')
                    ->alignEnd(),
                TextColumn::make('handoverDocument.document_number')
                    ->label('No. Surat')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis Transaksi')
                    ->options(self::JENIS),
                SelectFilter::make('stock_direction')
                    ->label('Arah Stok')
                    ->options(self::ARAH),
                SelectFilter::make('disposal_reason_id')
                    ->label('Alasan Pelepasan')
                    ->relationship('disposalReason', 'name'),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Tanggal dari'),
                        DatePicker::make('sampai')->label('Tanggal sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $d) => $q->whereDate('transaction_date', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $d) => $q->whereDate('transaction_date', '<=', $d))),
                Filter::make('hanya_pelepasan')
                    ->label('Hanya pelepasan & kehilangan')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('type', 'disposal')),
                Filter::make('kirim_belum_diterima')
                    ->label('Kiriman antar cabang belum diterima')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query
                        ->where('type', 'branch_transfer')
                        ->whereHas('asset.currentStatus', fn (Builder $q) => $q->where('code', 'in_transit'))),
            ])
            ->defaultSort('transaction_date', 'desc');
    }

    public function getReportHeadings(): array
    {
        return [
            'Tanggal', 'Kode Aset', 'Jenis', 'Jumlah', 'Arah Stok',
            'Dari', 'Ke', 'Dari Cabang', 'Ke Cabang',
            'Alasan Pelepasan', 'Nilai Dilepas', 'No. Surat', 'Catatan',
        ];
    }

    public function mapReportRow(mixed $record): array
    {
        return [
            $record->transaction_date?->translatedFormat('d M Y'),
            $record->asset?->asset_code,
            self::JENIS[$record->type] ?? $record->type,
            $record->quantity,
            self::ARAH[$record->stock_direction] ?? $record->stock_direction,
            $record->fromEmployee?->name,
            $record->toEmployee?->name,
            $record->fromBranch?->name,
            $record->toBranch?->name,
            $record->disposalReason?->name,
            $this->nilaiPelepasan($record),
            $record->handoverDocument?->document_number,
            $record->notes,
        ];
    }

    protected function getReportEagerLoads(): array
    {
        return ['asset', 'fromEmployee', 'toEmployee', 'fromBranch', 'toBranch', 'disposalReason', 'handoverDocument'];
    }

    public function getReportMeta(): array
    {
        $periode = $this->getTableFilterState('periode') ?? [];
        $jenis = $this->getTableFilterState('type')['value'] ?? null;

        return [
            'Periode' => $this->describePeriod($periode['dari'] ?? null, $periode['sampai'] ?? null),
            'Jenis transaksi' => $jenis ? (self::JENIS[$jenis] ?? $jenis) : 'Semua jenis',
        ];
    }

    /**
     * Nilai hanya dihitung untuk transaksi pemusnahan, memakai harga
     * satuan aset saat itu dikali jumlah yang dilepas.
     */
    protected function nilaiPelepasan(AssetTransaction $record): ?float
    {
        if ($record->type !== 'disposal') {
            return null;
        }

        return (float) ($record->asset?->unit_price ?? 0) * (int) ($record->quantity ?? 0);
    }
}
