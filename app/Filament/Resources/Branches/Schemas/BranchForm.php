<?php

namespace App\Filament\Resources\Branches\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas Cabang')
                    ->description('Kode cabang dipakai sebagai penanda lokasi aset.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->label('Kode Cabang')
                            ->placeholder('Contoh: JKT')
                            ->required()
                            ->maxLength(20),
                        TextInput::make('name')
                            ->label('Nama Cabang')
                            ->placeholder('Contoh: Jakarta (Pusat)')
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make('Kontak & Alamat')
                    ->columns(2)
                    ->schema([
                        TextInput::make('phone')
                            ->label('Telepon')
                            ->placeholder('Contoh: 021-1234567')
                            ->tel(),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->helperText('Nonaktifkan bila cabang sudah tidak beroperasi.')
                            ->default(true)
                            ->required(),
                        Textarea::make('address')
                            ->label('Alamat')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
