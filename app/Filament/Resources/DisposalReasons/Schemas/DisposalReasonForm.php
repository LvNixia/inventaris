<?php

namespace App\Filament\Resources\DisposalReasons\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DisposalReasonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Alasan Pelepasan')
                    ->description('Dipakai saat aset dilepas, dijual, atau dihapusbukukan.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode')
                            ->placeholder('Contoh: dijual')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('name')
                            ->label('Nama Alasan')
                            ->placeholder('Contoh: Dijual ke pihak ketiga')
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make('Pengaturan')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_lost')
                            ->label('Termasuk Kehilangan')
                            ->helperText('Aktifkan bila alasan ini dihitung sebagai aset hilang.')
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
