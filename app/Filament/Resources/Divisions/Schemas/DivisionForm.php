<?php

namespace App\Filament\Resources\Divisions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DivisionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Data Divisi')
                    ->description('Divisi dipakai untuk pengelompokan karyawan.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Divisi')
                            ->placeholder('Contoh: IT Support')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->helperText('Nonaktifkan bila divisi sudah tidak digunakan.')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }
}
