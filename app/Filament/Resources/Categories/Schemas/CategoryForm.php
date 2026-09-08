<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Kategori')
                    ->description('Awalan kode dipakai untuk membentuk kode aset, contoh: IDS-LPT-001.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Kategori')
                            ->placeholder('Contoh: Laptop')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code_prefix')
                            ->label('Awalan Kode')
                            ->placeholder('Contoh: LPT')
                            ->required()
                            ->maxLength(10),
                    ]),

                Section::make('Pengaturan')
                    ->columns(2)
                    ->schema([
                        Toggle::make('requires_serial')
                            ->label('Wajib Nomor Seri')
                            ->helperText('Aktifkan untuk barang bernomor seri seperti laptop atau ponsel.')
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Urutan Tampil')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        TextInput::make('last_seq')
                            ->label('Nomor Urut Terakhir')
                            ->helperText('Diisi otomatis sistem saat aset baru dibuat.')
                            ->numeric()
                            ->default(0)
                            ->required(),
                    ]),
            ]);
    }
}
