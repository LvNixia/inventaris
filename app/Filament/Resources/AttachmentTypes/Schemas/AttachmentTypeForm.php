<?php

namespace App\Filament\Resources\AttachmentTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttachmentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Jenis Lampiran')
                    ->description('Jenis lampiran dipakai saat mengunggah berkas pendukung aset.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode')
                            ->placeholder('Contoh: invoice')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('name')
                            ->label('Nama Jenis Lampiran')
                            ->placeholder('Contoh: Faktur Pembelian')
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make('Pengaturan')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->required(),
                        Toggle::make('is_system')
                            ->label('Bawaan Sistem')
                            ->helperText('Data bawaan sistem sebaiknya tidak diubah.')
                            ->required(),
                    ]),
            ]);
    }
}
