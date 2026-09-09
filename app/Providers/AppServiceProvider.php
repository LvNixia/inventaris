<?php

namespace App\Providers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\BasePage;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureFilamentDefaults();
    }

    /**
     * Standar tampilan komponen Filament agar seragam di seluruh halaman,
     * termasuk form yang muncul di dalam modal aksi.
     */
    protected function configureFilamentDefaults(): void
    {
        // Tombol aksi form (Buat / Simpan / Batal) seragam di semua halaman:
        // rata kanan dan menempel di bawah layar saat form panjang.
        BasePage::alignFormActionsEnd();
        BasePage::stickyFormActions();

        // Dropdown memakai panel milik Filament, bukan dropdown bawaan browser.
        Select::configureUsing(fn (Select $select) => $select
            ->native(false)
            ->placeholder('Pilih salah satu'));

        // Tanggal selalu format Indonesia: 08 Sep 2026.
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker
            ->native(false)
            ->displayFormat('d M Y'));

        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker
            ->native(false)
            ->displayFormat('d M Y H:i')
            ->seconds(false));

        // Semua kolom tabel bisa disembunyikan lewat menu "Kolom"; tidak ada
        // kolom yang terkunci. Dipasang di kelas dasar Column agar berlaku untuk
        // setiap turunannya (TextColumn, IconColumn, dan seterusnya). Kolom yang
        // memanggil ->toggleable(...) sendiri tetap menang karena rantai fluent
        // dijalankan setelah konfigurasi global ini.
        Column::configureUsing(fn (Column $column) => $column->toggleable());

        // Kolom tabel: format tanggal seragam dan placeholder untuk nilai kosong.
        TextColumn::configureUsing(fn (TextColumn $column) => $column
            ->placeholder('—'));

        // Tabel: paginasi dan pesan kosong yang seragam.
        Table::configureUsing(fn (Table $table) => $table
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada data')
            ->emptyStateDescription('Data yang ditambahkan akan tampil di sini.')
            ->striped());
    }
}
