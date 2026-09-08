<?php

namespace App\Filament\Resources\ServiceKinds\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceKindForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Jenis Servis')
                    ->description('Dipakai saat membuka catatan servis atau upgrade aset.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode')
                            ->placeholder('Contoh: upgrade_ram')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('name')
                            ->label('Nama Jenis Servis')
                            ->placeholder('Contoh: Upgrade RAM')
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make('Pengaturan')
                    ->columns(2)
                    ->schema([
                        Toggle::make('changes_spec')
                            ->label('Mengubah Spesifikasi')
                            ->helperText('Aktifkan bila servis jenis ini mengubah spesifikasi aset.')
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
                            ->required(),
                    ]),
            ]);
    }
}
