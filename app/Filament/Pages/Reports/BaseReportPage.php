<?php

namespace App\Filament\Pages\Reports;

use App\Services\Reports\ReportExporter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kerangka halaman laporan.
 *
 * Tabel di layar, berkas XLSX, dan PDF dibangun dari query yang sama
 * (getFilteredTableQuery), sehingga hasil unduhan selalu mengikuti filter
 * yang sedang dipilih pengguna -- bukan seluruh isi tabel.
 */
abstract class BaseReportPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.report';

    public static function getNavigationGroup(): ?string
    {
        return 'Laporan';
    }

    /**
     * Judul kolom untuk XLSX dan PDF.
     *
     * @return array<int, string>
     */
    abstract public function getReportHeadings(): array;

    /**
     * Satu baris data untuk XLSX dan PDF.
     *
     * @return array<int, mixed>
     */
    abstract public function mapReportRow(mixed $record): array;

    /**
     * Relasi yang perlu dimuat lebih dulu saat mengekspor,
     * agar tidak menembak query per baris.
     *
     * @return array<int, string>
     */
    protected function getReportEagerLoads(): array
    {
        return [];
    }

    /**
     * Ringkasan filter aktif yang ikut dicetak di kepala laporan.
     *
     * @return array<string, string>
     */
    public function getReportMeta(): array
    {
        return [];
    }

    protected function getReportOrientation(): string
    {
        return 'landscape';
    }

    /**
     * Baris laporan diambil per potongan agar data besar tidak menumpuk di memori.
     *
     * @return array<int, array<int, mixed>>
     */
    public function getReportRows(): array
    {
        $query = $this->getFilteredTableQuery();

        if ($eagerLoads = $this->getReportEagerLoads()) {
            $query->with($eagerLoads);
        }

        $rows = [];

        $query->chunk(500, function ($records) use (&$rows): void {
            foreach ($records as $record) {
                $rows[] = $this->mapReportRow($record);
            }
        });

        return $rows;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unduhXlsx')
                ->label('Unduh XLSX')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(function () {
                    try {
                        return app(ReportExporter::class)->xlsx(
                            $this->getTitle(),
                            $this->getReportHeadings(),
                            $this->getReportRows(),
                            $this->getReportMeta(),
                        );
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->warning()
                            ->title('Ekspor XLSX belum bisa dipakai')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();

                        return null;
                    }
                }),

            Action::make('cetakPdf')
                ->label('Cetak PDF')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->action(fn () => app(ReportExporter::class)->pdf(
                    $this->getTitle(),
                    $this->getReportHeadings(),
                    $this->getReportRows(),
                    $this->getReportMeta(),
                    $this->getReportOrientation(),
                )),
        ];
    }

    /**
     * Laporan hanya membaca data; tidak ada aksi ubah atau hapus.
     */
    protected function applyDefaultTableSettings(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return $table
            ->recordActions([])
            ->toolbarActions([])
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(25);
    }

    /**
     * Membantu membaca nilai filter rentang tanggal untuk ringkasan cetak.
     */
    protected function describePeriod(?string $from, ?string $until): string
    {
        return match (true) {
            filled($from) && filled($until) => \Illuminate\Support\Carbon::parse($from)->translatedFormat('d M Y')
                . ' s.d. ' . \Illuminate\Support\Carbon::parse($until)->translatedFormat('d M Y'),
            filled($from) => 'Sejak ' . \Illuminate\Support\Carbon::parse($from)->translatedFormat('d M Y'),
            filled($until) => 'Sampai ' . \Illuminate\Support\Carbon::parse($until)->translatedFormat('d M Y'),
            default => 'Semua periode',
        };
    }

    protected function nameOf(string $model, mixed $id, string $column = 'name'): string
    {
        if (blank($id)) {
            return 'Semua';
        }

        return $model::query()->whereKey($id)->value($column) ?? 'Semua';
    }

    protected function scopeToUserBranch(Builder $query, string $column = 'branch_id'): Builder
    {
        $user = auth()->user();

        if ($user && $user->role !== \App\Enums\Role::AdminPusat && $user->branch_id) {
            $query->where($column, $user->branch_id);
        }

        return $query;
    }
}
