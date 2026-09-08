<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class Panduan extends Page
{
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.panduan';

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-book-open';
    }

    public static function getNavigationLabel(): string
    {
        return 'Panduan Penggunaan';
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Panduan Penggunaan';
    }

    public function getSubheading(): ?string
    {
        return 'Alur kerja pencatatan aset, mulai dari pendaftaran barang sampai pelepasan.';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Bantuan';
    }

    /**
     * Contoh format yang dipakai sistem, diambil dari data nyata bila ada,
     * agar panduan tidak menampilkan contoh yang berbeda dengan kenyataan.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function contoh(): array
    {
        return [
            'kodeAset' => \App\Models\Asset::withoutGlobalScopes()->value('asset_code') ?? 'IDS-LAP-001',
            'nomorSurat' => \App\Models\HandoverDocument::withoutGlobalScopes()
                ->whereNotNull('document_number')
                ->value('document_number') ?? 'IDS-IT/JKT/2026/03/01',
        ];
    }
}
