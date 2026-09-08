<?php

namespace App\Filament\Resources\ServiceResults\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceResultForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Hasil Servis')
                    ->description('Dipakai saat menutup catatan servis aset.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode')
                            ->placeholder('Contoh: berhasil')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('name')
                            ->label('Nama Hasil Servis')
                            ->placeholder('Contoh: Berhasil Diperbaiki')
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make('Pengaturan')
                    ->columns(2)
                    ->schema([
                        Toggle::make('applies_spec')
                            ->label('Menerapkan Spesifikasi Baru')
                            ->helperText('Spesifikasi hasil servis akan disalin ke data aset.')
                            ->required(),
                        Toggle::make('marks_broken')
                            ->label('Menandai Rusak')
                            ->helperText('Aset ditandai rusak setelah servis dengan hasil ini.')
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->required(),
                        Toggle::make('is_system')
                            ->label('Bawaan Sistem')
                            ->helperText('Data bawaan sistem sebaiknya tidak diubah.')
                            ->required(),
                        TextInput::make('sort_order')
                            ->label('Urutan Tampil')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
