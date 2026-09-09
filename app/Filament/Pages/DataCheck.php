<?php

namespace App\Filament\Pages;

use App\Services\DataCheckService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class DataCheck extends Page
{
    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.data-check';

    public array $issues = [];

    public bool $hasRun = false;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-shield-exclamation';
    }

    public static function getNavigationLabel(): string
    {
        return 'Pemeriksaan Data';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Pemeriksaan Data';
    }

    public function getSubheading(): ?string
    {
        return 'Memverifikasi integritas stok, riwayat transaksi, dan keseimbangan aset.';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Laporan';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runCheck')
                ->label($this->hasRun ? 'Periksa Ulang' : 'Jalankan Pemeriksaan')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->action('runCheck'),
        ];
    }

    public function runCheck(): void
    {
        $this->issues = app(DataCheckService::class)->runChecks();
        $this->hasRun = true;

        count($this->issues) === 0
            ? Notification::make()
                ->success()
                ->title('Semua data valid')
                ->body('Tidak ditemukan masalah pada integritas stok atau transaksi.')
                ->send()
            : Notification::make()
                ->warning()
                ->title('Ditemukan '.count($this->issues).' masalah data')
                ->body('Rincian masalah ditampilkan di halaman ini.')
                ->send();
    }

    /**
     * Masalah dikelompokkan per jenis data agar mudah ditelusuri.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getGroupedIssuesProperty(): array
    {
        $grouped = [];

        foreach ($this->issues as $issue) {
            $grouped[$issue['type'] ?? 'Lainnya'][] = $issue;
        }

        return $grouped;
    }
}
