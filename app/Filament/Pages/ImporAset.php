<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\ImportBatch;
use App\Services\Import\AssetImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Memasukkan data aset lama dari berkas XLSX atau CSV.
 *
 * Berkas selalu diperiksa lebih dulu dan hasilnya ditampilkan, supaya pengguna
 * tahu persis berapa baris yang akan masuk dan baris mana yang bermasalah
 * sebelum ada satu pun data yang tertulis.
 */
class ImporAset extends Page
{
    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.impor-aset';

    /** @var array<string, mixed> */
    public array $data = [];

    /** Hasil pemeriksaan berkas yang sedang diunggah. */
    public ?array $pratinjau = null;

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-arrow-up-tray';
    }

    public static function getNavigationLabel(): string
    {
        return 'Impor Aset';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Impor Aset';
    }

    public function getSubheading(): ?string
    {
        return 'Memasukkan data aset yang sudah berjalan dari berkas XLSX atau CSV.';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Manajemen Aset';
    }

    /**
     * Impor membuat barang, pembelian, dan unit sekaligus, jadi dibatasi untuk
     * admin pusat.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->role === Role::AdminPusat;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('berkas')
                    ->label('Berkas Impor')
                    ->acceptedFileTypes([
                        'text/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->maxSize(10240)
                    ->directory('imports')
                    ->helperText('XLSX atau CSV, maksimal 10 MB. Unduh berkas contoh di bawah untuk melihat kolom yang dibutuhkan.')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->reset('pratinjau')),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unduh_contoh')
                ->label('Unduh Berkas Contoh')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => response()->streamDownload(
                    fn () => print app(AssetImporter::class)->berkasContoh(),
                    'contoh-impor-aset.csv',
                )),
        ];
    }

    public function periksa(): void
    {
        $path = $this->berkasTersimpan();

        if (! $path) {
            Notification::make()->warning()->title('Unggah berkasnya lebih dulu.')->send();

            return;
        }

        try {
            $this->pratinjau = app(AssetImporter::class)->pratinjau($path);
        } catch (\Throwable $e) {
            $this->pratinjau = null;

            Notification::make()->danger()->title('Berkas tidak terbaca')->body($e->getMessage())->send();
        }
    }

    public function jalankan(): void
    {
        $path = $this->berkasTersimpan();

        if (! $path) {
            Notification::make()->warning()->title('Unggah berkasnya lebih dulu.')->send();

            return;
        }

        try {
            $batch = app(AssetImporter::class)->jalankan($path, basename($path));

            Notification::make()
                ->success()
                ->title($batch->created_count.' unit berhasil diimpor')
                ->body($batch->failed_count > 0
                    ? $batch->failed_count.' baris dilewati karena bermasalah.'
                    : 'Seluruh baris masuk tanpa masalah.')
                ->send();

            $this->reset('pratinjau');
            $this->form->fill();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Impor gagal')->body($e->getMessage())->send();
        }
    }

    /**
     * @return Collection<int, ImportBatch>
     */
    public function getRiwayatProperty()
    {
        return ImportBatch::with('user')
            ->where('type', 'asset')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function batalkan(int $batchId): void
    {
        try {
            $jumlah = app(AssetImporter::class)->batalkan(ImportBatch::findOrFail($batchId));

            Notification::make()->success()->title($jumlah.' unit dihapus')->send();
        } catch (\Throwable $e) {
            Notification::make()->danger()->title('Pembatalan gagal')->body($e->getMessage())->send();
        }
    }

    /**
     * Jalur berkas yang sudah tersimpan di disk, siap dibaca ulang.
     */
    protected function berkasTersimpan(): ?string
    {
        $berkas = $this->data['berkas'] ?? null;
        $path = is_array($berkas) ? reset($berkas) : $berkas;

        if (blank($path)) {
            return null;
        }

        $disk = Storage::disk(config('filesystems.default'));

        return $disk->exists($path) ? $disk->path($path) : null;
    }

    /**
     * @return array<string, string>
     */
    public function getKolomProperty(): array
    {
        return AssetImporter::KOLOM;
    }
}
