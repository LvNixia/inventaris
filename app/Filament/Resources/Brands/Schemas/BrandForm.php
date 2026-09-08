<?php

namespace App\Filament\Resources\Brands\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Data Merek')
                    ->description('Merek digunakan saat pendataan aset baru.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Merek')
                            ->placeholder('Contoh: Lenovo')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->helperText('Nonaktifkan bila merek tidak dipakai lagi.')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }
}
