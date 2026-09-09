<?php

namespace App\Filament\Pages\Reports;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\PurchaseInvoice;
use App\Models\Vendor;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hutang usaha ke vendor: faktur yang belum lunas beserta umurnya.
 */
class LaporanHutangVendor extends BaseReportPage
{
    protected static ?int $navigationSort = 15;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-banknotes';
    }

    public static function getNavigationLabel(): string
    {
        return 'Hutang Vendor';
    }

    public function getTitle(): string
    {
        return 'Laporan Hutang Vendor';
    }

    public function getSubheading(): ?string
    {
        return 'Tagihan yang belum lunas, umur hutang, dan yang sudah lewat jatuh tempo.';
    }

    public function table(Table $table): Table
    {
        return $this->applyDefaultTableSettings($table)
            ->query(fn (): Builder => $this->scopeToUserBranch(
                PurchaseInvoice::query()->where('status', '!=', 'cancelled')
            ))
            ->columns([
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_number')
                    ->label('Nomor Faktur')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date('d M Y')
                    ->sortable()
                    ->color(fn (PurchaseInvoice $record): ?string => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('umur')
                    ->label('Umur Hutang')
                    ->state(fn (PurchaseInvoice $record): string => $this->umur($record))
                    ->badge()
                    ->color(fn (PurchaseInvoice $record): string => match (true) {
                        $record->status === 'paid' => 'success',
                        $record->isOverdue() => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR', locale: 'id')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('paid_amount')
                    ->label('Terbayar')
                    ->money('IDR', locale: 'id')
                    ->alignEnd(),
                TextColumn::make('outstanding')
                    ->label('Sisa')
                    ->state(fn (PurchaseInvoice $record): float => $record->outstanding)
                    ->money('IDR', locale: 'id')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'unpaid' => 'Belum Dibayar',
                        'partial' => 'Dibayar Sebagian',
                        'paid' => 'Lunas',
                        default => $state,
                    }),
                TextColumn::make('branch.name')
                    ->label('Cabang')
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->role === Role::AdminPusat),
            ])
            ->defaultSort('due_date')
            ->filters([
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('branch_id')
                    ->label('Cabang')
                    ->relationship('branch', 'name')
                    ->visible(fn (): bool => auth()->user()?->role === Role::AdminPusat),
                Filter::make('belum_lunas')
                    ->label('Hanya yang belum lunas')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->outstanding()),
                Filter::make('lewat_tempo')
                    ->label('Hanya yang lewat jatuh tempo')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->overdue()),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Faktur dari'),
                        DatePicker::make('sampai')->label('Faktur sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, $d) => $q->whereDate('invoice_date', '>=', $d))
                        ->when($data['sampai'] ?? null, fn (Builder $q, $d) => $q->whereDate('invoice_date', '<=', $d))),
            ]);
    }

    public function getReportHeadings(): array
    {
        return [
            'Vendor', 'Nomor Faktur', 'Tanggal', 'Jatuh Tempo', 'Umur Hutang',
            'Total', 'Terbayar', 'Sisa', 'Status', 'Cabang',
        ];
    }

    public function mapReportRow(mixed $record): array
    {
        return [
            $record->vendor?->name,
            $record->invoice_number,
            $record->invoice_date?->translatedFormat('d M Y'),
            $record->due_date?->translatedFormat('d M Y'),
            $this->umur($record),
            (float) $record->total_amount,
            (float) $record->paid_amount,
            $record->outstanding,
            match ($record->status) {
                'unpaid' => 'Belum Dibayar',
                'partial' => 'Dibayar Sebagian',
                'paid' => 'Lunas',
                default => $record->status,
            },
            $record->branch?->name,
        ];
    }

    protected function getReportEagerLoads(): array
    {
        return ['vendor', 'branch', 'paymentTerm'];
    }

    public function getReportMeta(): array
    {
        return [
            'Vendor' => $this->nameOf(Vendor::class, $this->getTableFilterState('vendor_id')['value'] ?? null),
            'Cabang' => $this->nameOf(Branch::class, $this->getTableFilterState('branch_id')['value'] ?? null),
        ];
    }

    /**
     * Umur hutang dihitung dari jatuh tempo: positif berarti sudah lewat.
     */
    protected function umur(PurchaseInvoice $invoice): string
    {
        if ($invoice->status === 'paid') {
            return 'Lunas';
        }

        $hari = $invoice->age_in_days;

        return match (true) {
            $hari > 0 => 'Lewat '.$hari.' hari',
            $hari === 0 => 'Jatuh tempo hari ini',
            default => abs($hari).' hari lagi',
        };
    }
}
