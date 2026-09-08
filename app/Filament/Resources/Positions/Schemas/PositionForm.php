<?php

namespace App\Filament\Resources\Positions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PositionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Data Jabatan')
                    ->description('Jabatan dipakai pada data karyawan dan surat serah terima.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Jabatan')
                            ->placeholder('Contoh: Staff IT')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->helperText('Nonaktifkan bila jabatan sudah tidak digunakan.')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }
}
